# Database Restore Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin restore the site to one of its own backups from `/admin/database`, on production as well as dev.

**Architecture:** `App\Services\Database\DatabaseRestoreService` already validates, snapshots and replays a dump — its only caller is the prod → dev pull. This adds a second caller: two routes (`GET`/`POST admin/database/restore/{file}`), a confirmation page, and a gate in `BackupRepository` that compares the host segment of the filename against the host of `config('app.url')`. No environment flag; `DB_RESTORE_ENABLED` keeps gating only `pull()`.

**Tech Stack:** Laravel 11, PHP 8.3 local / 8.4 on CI and host, PHPUnit, Blade, Pint. Tests run against in-memory SQLite; both servers run MySQL.

Spec: [`docs/superpowers/specs/2026-08-02-database-restore-design.md`](../specs/2026-08-02-database-restore-design.md)

## Global Constraints

- **TDD is mandatory** (`CLAUDE.md`). Every step writes a failing test first, confirms it fails for the right reason, then writes the minimal code.
- **Run the full CI gate before every commit**, in this order, and do not commit on a red one:
  ```
  vendor/bin/pint --test
  php artisan test
  composer audit --no-dev
  ```
  Pint reformats new test files (import order, `!` spacing) far more often than application code. If `--test` fails, run `vendor/bin/pint` and re-run `--test`.
- **Never connect to the live host.** No FTP, SSH, cPanel or HTTP request to `astrotherapia.com` / `dev.astrotherapia.com`. This work is committed locally; the owner deploys.
- **Content tables are `config('database_admin.tables')`**, currently `['authors', 'posts', 'post_translations', 'media', 'site_settings']`, in that order. Never hardcode the list — read the config.
- **The backup disk is `backups`**, rooted at `database_path()`, files under `BackupRepository::DIRECTORY` (`'backups'`). Tests fake it with `Storage::fake('backups')`.
- **Docs are updated in the same change, not as a follow-up** (`CLAUDE.md`). Task 8 covers this and is not optional.
- Existing test `AdminDatabasePullTest::test_the_manual_restore_upload_route_is_removed` asserts `POST /admin/database/restore` (no file segment) returns 404. The new routes all carry a `{file}` segment, so that test must still pass unchanged. Do not add a fileless restore route.

---

### Task 1: Missing backups 404 instead of 500

Today `download()` on a vanished file throws (the `backups` disk is configured `'throw' => false`, so `path()` happily returns a path to nothing and `response()->download()` fails), and `destroy()` reports "Backup deleted." for a file that never existed.

**Files:**
- Modify: `app/Services/Database/BackupRepository.php`
- Modify: `app/Http/Controllers/Admin/DatabaseController.php:36-46`
- Test: `tests/Unit/BackupRepositoryTest.php`, `tests/Feature/AdminDatabasePageTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `BackupRepository::exists(string $name): bool` — false for a name that fails `FILENAME_PATTERN` *and* for a valid name with no file. Never throws. Task 2 builds on it.

- [ ] **Step 1: Write the failing unit tests**

Append to `tests/Unit/BackupRepositoryTest.php`:

```php
    public function test_exists_distinguishes_a_present_backup_from_a_missing_one(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->assertTrue($this->repository->exists('backup-20260101-000000-example.com-manual.sql.gz'));
        $this->assertFalse($this->repository->exists('backup-20260102-000000-example.com-manual.sql.gz'));
    }

    public function test_exists_returns_false_for_a_traversal_attempt_without_throwing(): void
    {
        $this->assertFalse($this->repository->exists('../../.env'));
    }
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=BackupRepositoryTest`
Expected: FAIL with `Call to undefined method App\Services\Database\BackupRepository::exists()`

- [ ] **Step 3: Add `exists()`**

In `app/Services/Database/BackupRepository.php`, add above `path()`:

```php
    /**
     * Unlike path() and delete(), this never throws: callers use it to decide
     * whether to 404, and an invalid name is a "no" rather than an error.
     */
    public function exists(string $name): bool
    {
        return (bool) preg_match(self::FILENAME_PATTERN, $name)
            && Storage::disk('backups')->exists(self::DIRECTORY.'/'.$name);
    }
```

- [ ] **Step 4: Run them to verify they pass**

Run: `php artisan test --filter=BackupRepositoryTest`
Expected: PASS

- [ ] **Step 5: Write the failing feature tests**

Append to `tests/Feature/AdminDatabasePageTest.php`:

```php
    public function test_downloading_a_backup_that_is_gone_is_not_found(): void
    {
        Storage::fake('backups');

        $this->actingAs($this->admin())
            ->get('/admin/database/backup/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertNotFound();
    }

    public function test_deleting_a_backup_that_is_gone_is_not_found(): void
    {
        Storage::fake('backups');

        $this->actingAs($this->admin())
            ->delete('/admin/database/backup/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertNotFound();
    }
```

- [ ] **Step 6: Run them to verify they fail**

Run: `php artisan test --filter=AdminDatabasePageTest`
Expected: FAIL — the download test errors instead of 404ing, the delete test gets a 302 redirect.

- [ ] **Step 7: Guard both controller actions**

In `app/Http/Controllers/Admin/DatabaseController.php`, replace `download()` and `destroy()`:

```php
    public function download(string $file)
    {
        abort_unless($this->backups->exists($file), 404);

        return response()->download($this->backups->path($file));
    }

    public function destroy(string $file)
    {
        abort_unless($this->backups->exists($file), 404);

        $this->backups->delete($file);

        return redirect()->route('admin.database.index')->with('status', 'Backup deleted.');
    }
```

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS, no regressions.

- [ ] **Step 9: Run the CI gate and commit**

```bash
vendor/bin/pint --test
php artisan test
composer audit --no-dev
git add app/Services/Database/BackupRepository.php app/Http/Controllers/Admin/DatabaseController.php tests/Unit/BackupRepositoryTest.php tests/Feature/AdminDatabasePageTest.php
git commit -m "fix: 404 on a backup that is no longer on disk

download() threw on a missing file and destroy() reported success for one
that was never there — the backups disk is configured throw => false, so
neither noticed. BackupRepository::exists() answers without throwing."
```

---

### Task 2: The host-match rule

**Files:**
- Modify: `app/Services/Database/BackupRepository.php`
- Test: `tests/Unit/BackupRepositoryTest.php`

**Interfaces:**
- Consumes: `BackupRepository::exists()` from Task 1.
- Produces:
  - `BackupRepository::hostOf(string $name): string` — the host segment, `''` when the name is not a backup filename.
  - `BackupRepository::isRestorable(string $name): bool` — `exists()` and `hostOf()` equals this site's host.
  - `FILENAME_PATTERN` gains one capture group around the host; the existing `(manual|auto)` group shifts from `$1` to `$2`. Nothing reads those groups today, so this is safe.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/BackupRepositoryTest.php`:

```php
    public function test_it_restores_a_backup_written_by_this_host(): void
    {
        config(['app.url' => 'https://example.com']);
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->assertTrue($this->repository->isRestorable('backup-20260101-000000-example.com-manual.sql.gz'));
    }

    public function test_it_refuses_a_backup_written_by_another_host(): void
    {
        config(['app.url' => 'https://example.com']);
        $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->assertFalse($this->repository->isRestorable('backup-20260101-000000-dev.example.com-manual.sql.gz'));
    }

    public function test_it_refuses_an_own_host_backup_that_is_missing(): void
    {
        config(['app.url' => 'https://example.com']);

        $this->assertFalse($this->repository->isRestorable('backup-20260101-000000-example.com-manual.sql.gz'));
    }

    public function test_a_hyphenated_host_survives_the_filename_round_trip(): void
    {
        config(['app.url' => 'https://dev-2.example.com']);

        $this->assertSame('dev-2.example.com', $this->repository->hostOf($this->repository->filename('manual')));
    }

    public function test_host_of_returns_empty_for_a_name_that_is_not_a_backup(): void
    {
        $this->assertSame('', $this->repository->hostOf('notes.txt'));
    }
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=BackupRepositoryTest`
Expected: FAIL with `Call to undefined method ...::isRestorable()`

- [ ] **Step 3: Add the capture group, `hostOf()` and `isRestorable()`**

In `app/Services/Database/BackupRepository.php`, change the constant:

```php
    /**
     * backup-{YmdHis}-{host}-{origin}.sql.gz
     *
     * The timestamp leads so a descending lexical sort is newest-first; a
     * host-first name would sort by hostname and interleave prod dumps with
     * dev's pre-restore snapshots.
     *
     * Group 1 is the host. It is captured rather than re-derived by splitting
     * on "-", because hosts legitimately contain hyphens (dev-2.example.com);
     * the greedy segment backtracks to leave the trailing -manual/-auto.
     */
    public const FILENAME_PATTERN = '/^backup-\d{8}-\d{6}-([A-Za-z0-9.-]+)-(manual|auto)\.sql\.gz$/';
```

Replace `filename()` so it and `hostOf()` cannot drift apart, and add the two new methods after it:

```php
    /** @param  'manual'|'auto'  $origin */
    public function filename(string $origin): string
    {
        return sprintf('backup-%s-%s-%s.sql.gz', now()->format('Ymd-His'), $this->currentHost(), $origin);
    }

    /** The host segment of a backup filename; '' when the name is not one. */
    public function hostOf(string $name): string
    {
        preg_match(self::FILENAME_PATTERN, $name, $matches);

        return $matches[1] ?? '';
    }

    /**
     * The gate on restore. Prod restores prod's backups, dev restores dev's,
     * and neither can be pointed at the other's data. This is deliberately in
     * code rather than an env flag: the primary case is rolling back
     * production, where DB_RESTORE_ENABLED is false by design.
     */
    public function isRestorable(string $name): bool
    {
        return $this->exists($name) && $this->hostOf($name) === $this->currentHost();
    }

    /** The host this site answers on, sanitised the same way for both uses. */
    private function currentHost(): string
    {
        $host = preg_replace('/[^A-Za-z0-9.-]/', '-', (string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host ?: 'local';
    }
```

- [ ] **Step 4: Run them to verify they pass**

Run: `php artisan test --filter=BackupRepositoryTest`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS. The capture-group change affects only `preg_match` calls that read groups, and nothing did before this task.

- [ ] **Step 6: Run the CI gate and commit**

```bash
vendor/bin/pint --test
php artisan test
composer audit --no-dev
git add app/Services/Database/BackupRepository.php tests/Unit/BackupRepositoryTest.php
git commit -m "feat: identify which backups this host may restore

hostOf() reads the host out of the filename via a capture group rather than
splitting on '-', which would truncate a host like dev-2.example.com.
filename() now shares the same host derivation so the two cannot drift."
```

---

### Task 3: Row counts in the dump header, under one transaction

**Files:**
- Modify: `app/Services/Database/DatabaseBackupService.php:54-75`
- Test: `tests/Feature/DatabaseBackupTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: dumps carry a header line `-- rows: authors=0, posts=1, post_translations=1, media=0, site_settings=1` — table order matches `config('database_admin.tables')`, separator is `, `, pairs are `table=count`. Task 4 parses exactly this format.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/DatabaseBackupTest.php`:

```php
    public function test_the_header_records_a_row_count_per_table(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Counted',
            'slug' => 'counted',
            'body' => '<p>Body</p>',
        ]);

        $sql = $this->contents(app(DatabaseBackupService::class)->create('manual'));

        $rows = collect(explode("\n", $sql))->first(fn (string $line) => str_starts_with($line, '-- rows: '));

        $this->assertNotNull($rows, 'The dump has no "-- rows:" header line.');
        $this->assertStringContainsString('authors=0', $rows);
        $this->assertStringContainsString('posts=1', $rows);
        $this->assertStringContainsString('post_translations=1', $rows);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=test_the_header_records_a_row_count_per_table`
Expected: FAIL with `The dump has no "-- rows:" header line.`

- [ ] **Step 3: Count inside a transaction that also covers the reads**

In `app/Services/Database/DatabaseBackupService.php`, replace `writeDump()` entirely:

```php
    /** @param  resource  $handle */
    private function writeDump($handle, ?string $connection): void
    {
        $db = DB::connection($connection);
        $tables = (array) config('database_admin.tables');
        $quoter = QuoterFactory::for($db->getDriverName());

        // One transaction over the counts and the reads. The header has to
        // describe the file, and without it a write landing between two tables
        // could put a child row in the dump whose parent was never read.
        // Read-only, so on InnoDB this is a REPEATABLE READ snapshot and costs
        // nothing.
        $db->transaction(function () use ($handle, $db, $tables, $quoter) {
            $counts = [];

            foreach ($tables as $table) {
                $counts[] = $table.'='.$db->table($table)->count();
            }

            $this->line($handle, '-- Content backup');
            $this->line($handle, '-- source: '.config('app.url'));
            $this->line($handle, '-- created: '.now()->toIso8601String());
            $this->line($handle, '-- tables: '.implode(', ', $tables));
            $this->line($handle, '-- rows: '.implode(', ', $counts));

            // Children first so the cascading foreign key on post_translations
            // is never the thing doing the deleting.
            foreach (array_reverse($tables) as $table) {
                $this->line($handle, 'DELETE FROM `'.$table.'`;');
            }

            // Parents first, so every child row has its parent present.
            foreach ($tables as $table) {
                $this->writeTable($handle, $db, $table, $quoter);
            }
        });
    }
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=test_the_header_records_a_row_count_per_table`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS. Two things to watch: `DatabaseBackupTest::test_it_never_dumps_excluded_tables` asserts `users`/`sessions`/`cache`/`jobs` never appear, and the new header line only lists configured tables, so it stays green. `DatabaseRestoreService::restore()` calls `create('auto')` before opening its own transaction, so nothing nests.

- [ ] **Step 6: Run the CI gate and commit**

```bash
vendor/bin/pint --test
php artisan test
composer audit --no-dev
git add app/Services/Database/DatabaseBackupService.php tests/Feature/DatabaseBackupTest.php
git commit -m "feat: record row counts in the dump header

The restore confirmation needs to show what is in a backup before it
replaces anything. Counting inside the same transaction as the reads makes
the numbers describe the file, and closes the gap where a write between two
tables could dump a child row whose parent was never read."
```

---

### Task 4: Read the header back

**Files:**
- Modify: `app/Services/Database/BackupRepository.php`
- Test: `tests/Unit/BackupRepositoryTest.php`

**Interfaces:**
- Consumes: `path()` (existing), the header format from Task 3.
- Produces: `BackupRepository::header(string $name): array{created: ?CarbonImmutable, rows: ?array<string,int>}`. Both keys are always present; either may be null. `rows` is null for a backup written before Task 3.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/BackupRepositoryTest.php`:

```php
    public function test_header_reads_the_created_date_and_row_counts(): void
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode(
            "-- Content backup\n-- created: 2026-01-01T09:30:00+00:00\n-- rows: posts=41, media=210\nDELETE FROM `posts`;\n"
        ));

        $header = $this->repository->header($name);

        $this->assertSame('2026-01-01 09:30', $header['created']->format('Y-m-d H:i'));
        $this->assertSame(['posts' => 41, 'media' => 210], $header['rows']);
    }

    public function test_header_returns_null_counts_for_a_backup_written_before_they_existed(): void
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode(
            "-- Content backup\nDELETE FROM `posts`;\n"
        ));

        $header = $this->repository->header($name);

        $this->assertNull($header['rows']);
        $this->assertNull($header['created']);
    }

    public function test_header_stops_at_the_first_statement(): void
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode(
            "-- rows: posts=1\nINSERT INTO `posts` (`body`) VALUES ('-- rows: posts=999');\n"
        ));

        $this->assertSame(['posts' => 1], $this->repository->header($name)['rows']);
    }
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=BackupRepositoryTest`
Expected: FAIL with `Call to undefined method ...::header()`

- [ ] **Step 3: Add `header()`**

In `app/Services/Database/BackupRepository.php`, add the imports `use Carbon\CarbonImmutable;` and `use Throwable;`, then add after `isRestorable()`:

```php
    /**
     * The leading comment block of a dump. Reads only until the first
     * statement, so it costs one gzip block regardless of archive size.
     *
     * Backups written before the `rows:` line existed return null counts
     * rather than failing — refusing to restore them would break the exact
     * rollback case this exists for.
     *
     * @return array{created: ?CarbonImmutable, rows: ?array<string, int>}
     */
    public function header(string $name): array
    {
        $handle = @gzopen($this->path($name), 'rb');

        if ($handle === false) {
            return ['created' => null, 'rows' => null];
        }

        $created = null;
        $rows = null;

        try {
            while (($line = gzgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");

                if (! str_starts_with($line, '--')) {
                    break;
                }

                if (str_starts_with($line, '-- created: ')) {
                    $created = $this->parseDate(substr($line, strlen('-- created: ')));
                }

                if (str_starts_with($line, '-- rows: ')) {
                    $rows = $this->parseCounts(substr($line, strlen('-- rows: ')));
                }
            }
        } finally {
            gzclose($handle);
        }

        return ['created' => $created, 'rows' => $rows];
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, int> */
    private function parseCounts(string $list): array
    {
        $counts = [];

        foreach (explode(', ', $list) as $pair) {
            [$table, $count] = array_pad(explode('=', $pair, 2), 2, null);

            if ($count !== null) {
                $counts[$table] = (int) $count;
            }
        }

        return $counts;
    }
```

- [ ] **Step 4: Run them to verify they pass**

Run: `php artisan test --filter=BackupRepositoryTest`
Expected: PASS

- [ ] **Step 5: Run the full suite and the CI gate, then commit**

```bash
vendor/bin/pint --test
php artisan test
composer audit --no-dev
git add app/Services/Database/BackupRepository.php tests/Unit/BackupRepositoryTest.php
git commit -m "feat: read a backup's header without opening the whole archive"
```

---

### Task 5: The media rewrite becomes the caller's decision

`restore()` reads `database_admin.media_fallback_url` itself. On dev that points at production, so restoring a dev-native backup on dev would rewrite its `/storage/media/` paths to production URLs for files production has never seen. The parameter is required, not defaulted, so every caller states its intent.

**Files:**
- Modify: `app/Services/Database/DatabaseRestoreService.php:27-40,99-101`
- Modify: `app/Http/Controllers/Admin/DatabaseController.php:68`
- Create: `tests/Feature/DatabaseRestoreServiceTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `DatabaseRestoreService::restore(string $archivePath, ?string $mediaFallbackUrl): array{snapshot: string, rows: int}`. Tasks 6 and 7 call it with `null`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DatabaseRestoreServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\Database\DatabaseBackupService;
use App\Services\Database\DatabaseRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseRestoreServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
    }

    public function test_a_null_fallback_leaves_media_paths_alone(): void
    {
        // Configured, and deliberately ignored: this backup came from this
        // host, so its paths already resolve here.
        config(['database_admin.media_fallback_url' => 'https://astrotherapia.com']);
        Media::create(['path' => 'media/photo.jpg', 'url' => '/storage/media/photo.jpg']);

        $archive = tempnam(sys_get_temp_dir(), 'dump_');
        app(DatabaseBackupService::class)->dumpTo($archive);
        app(DatabaseRestoreService::class)->restore($archive, null);
        @unlink($archive);

        $this->assertSame('/storage/media/photo.jpg', Media::first()->url);
    }

    public function test_a_fallback_url_rewrites_media_paths(): void
    {
        config(['database_admin.media_fallback_url' => null]);
        Media::create(['path' => 'media/photo.jpg', 'url' => '/storage/media/photo.jpg']);

        $archive = tempnam(sys_get_temp_dir(), 'dump_');
        app(DatabaseBackupService::class)->dumpTo($archive);
        app(DatabaseRestoreService::class)->restore($archive, 'https://astrotherapia.com');
        @unlink($archive);

        $this->assertSame('https://astrotherapia.com/storage/media/photo.jpg', Media::first()->url);
    }
}
```

The second test sets the config to null and passes a URL, so it fails if the service still reads config instead of the argument.

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=DatabaseRestoreServiceTest`
Expected: FAIL — `restore()` takes one argument, so PHP raises `ArgumentCountError`-adjacent errors on the extra parameter.

- [ ] **Step 3: Thread the URL through**

In `app/Services/Database/DatabaseRestoreService.php`, change the signature and the rewrite call:

```php
    /**
     * @param  ?string  $mediaFallbackUrl  absolute origin to point media at, or
     *                                     null to leave paths untouched. The
     *                                     caller decides: rewriting is a
     *                                     cross-host correction, and a backup
     *                                     this host wrote needs none.
     * @return array{snapshot: string, rows: int}
     */
    public function restore(string $archivePath, ?string $mediaFallbackUrl): array
    {
        $this->assertGzip($archivePath);
        $this->assertStatementsAllowed($archivePath);

        $snapshot = $this->backups->create('auto');

        DB::transaction(function () use ($archivePath, $mediaFallbackUrl) {
            $this->eachStatement($archivePath, fn (string $statement) => DB::unprepared($statement));
            $this->rewriteMediaPaths($mediaFallbackUrl);
        });

        return ['snapshot' => $snapshot, 'rows' => $this->countRows()];
    }
```

and change `rewriteMediaPaths()`'s first two lines:

```php
    private function rewriteMediaPaths(?string $mediaFallbackUrl): void
    {
        $rewriter = new MediaPathRewriter($mediaFallbackUrl);
```

In `app/Http/Controllers/Admin/DatabaseController.php`, `pull()` now states its intent:

```php
            $result = $this->restoreService->restore($temp, config('database_admin.media_fallback_url'));
```

- [ ] **Step 4: Run them to verify they pass**

Run: `php artisan test --filter=DatabaseRestoreServiceTest`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS. `AdminDatabasePullTest::test_pull_rewrites_media_urls_to_the_fallback_origin` is the regression guard that `pull()` still rewrites.

- [ ] **Step 6: Run the CI gate and commit**

```bash
vendor/bin/pint --test
php artisan test
composer audit --no-dev
git add app/Services/Database/DatabaseRestoreService.php app/Http/Controllers/Admin/DatabaseController.php tests/Feature/DatabaseRestoreServiceTest.php
git commit -m "refactor: let the caller decide whether to rewrite media paths

Rewriting is a cross-host correction. Reading the config inside restore()
meant a dev-native backup restored on dev would have its paths rewritten to
prod, for files prod has never seen."
```

---

### Task 6: The confirmation page

**Files:**
- Modify: `routes/web.php:43`
- Modify: `app/Http/Controllers/Admin/DatabaseController.php`
- Modify: `app/Services/Database/BackupRepository.php` (`all()` gains `restorable`)
- Create: `resources/views/admin/database/confirm.blade.php`
- Modify: `resources/views/admin/database/index.blade.php:50-57`
- Create: `tests/Feature/AdminDatabaseRestoreTest.php`

**Interfaces:**
- Consumes: `isRestorable()`, `hostOf()` (Task 2), `header()` (Task 4).
- Produces:
  - Route `admin.database.restore.confirm` at `GET admin/database/restore/{file}`.
  - `BackupRepository::all()` rows gain `'restorable' => bool` alongside `name`, `size`, `origin`.
  - View data contract for `admin.database.confirm`: `file` (string), `header` (Task 4's array), `live` (`array<string,int>`), `host` (string), `tables` (`array<int,string>`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AdminDatabaseRestoreTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Database\BackupRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDatabaseRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        config(['app.url' => 'https://example.com']);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @param  array<string, int>|null  $rows */
    private function putBackup(string $name, ?array $rows = ['authors' => 0, 'posts' => 41, 'post_translations' => 82, 'media' => 210, 'site_settings' => 1]): string
    {
        $header = "-- Content backup\n-- created: 2026-01-01T09:30:00+00:00\n";

        if ($rows !== null) {
            $header .= '-- rows: '.collect($rows)->map(fn (int $n, string $t) => "{$t}={$n}")->implode(', ')."\n";
        }

        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode($header));

        return $name;
    }

    public function test_the_confirmation_page_shows_backup_counts_beside_live_ones(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee($name)
            ->assertSeeInOrder(['Table', 'In backup', 'Live now'])
            ->assertSeeInOrder(['posts', '41']);
    }

    public function test_the_confirmation_page_asks_for_the_host(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee('to confirm');
    }

    public function test_a_backup_from_another_host_cannot_be_confirmed(): void
    {
        $name = $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertNotFound();
    }

    public function test_a_missing_backup_cannot_be_confirmed(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database/restore/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertNotFound();
    }

    public function test_the_confirmation_page_rejects_a_traversal_attempt(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database/restore/..%2F..%2F.env')
            ->assertNotFound();
    }

    public function test_guests_cannot_reach_the_confirmation_page(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->get('/admin/database/restore/'.$name)->assertRedirect('/admin/login');
    }

    public function test_a_backup_without_row_counts_says_so_and_stays_restorable(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz', null);

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee('Row counts are unavailable');
    }

    public function test_the_listing_offers_restore_for_an_own_host_backup(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Restore');
    }

    public function test_the_listing_does_not_offer_restore_for_a_foreign_backup(): void
    {
        $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertDontSee('Restore');
    }
}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=AdminDatabaseRestoreTest`
Expected: FAIL — the confirmation tests 404 (no route), the listing test does not see "Restore".

- [ ] **Step 3: Register both routes and stub the POST action**

The confirmation view's form posts to `admin.database.restore`, and Blade resolves
`route()` at render time — so that route must exist before this task's tests can
render the page. Register both now; Task 7 fills in the POST action's body.

In `routes/web.php`, after the existing `admin.database.destroy` route and before `admin.database.pull`:

```php
    Route::get('database/restore/{file}', [DatabaseController::class, 'confirm'])
        ->where('file', '[A-Za-z0-9.\-]+\.sql\.gz')
        ->name('admin.database.restore.confirm');
    Route::post('database/restore/{file}', [DatabaseController::class, 'restore'])
        ->where('file', '[A-Za-z0-9.\-]+\.sql\.gz')
        ->name('admin.database.restore');
```

In `app/Http/Controllers/Admin/DatabaseController.php`, add the stub. Task 7's
failing tests are what replace it:

```php
    public function restore(string $file)
    {
        abort(404);
    }
```

- [ ] **Step 4: Mark restorable rows in the listing data**

In `app/Services/Database/BackupRepository.php`, extend the `all()` map:

```php
            ->map(fn (string $name) => [
                'name' => $name,
                'size' => $disk->size(self::DIRECTORY.'/'.$name),
                'origin' => str_ends_with($name, '-auto.sql.gz') ? 'auto (pre-restore)' : 'manual',
                'restorable' => $this->isRestorable($name),
            ]);
```

- [ ] **Step 5: Add the controller action**

In `app/Http/Controllers/Admin/DatabaseController.php`, add `use Illuminate\Support\Facades\DB;` to the imports and these methods after `destroy()`:

```php
    /**
     * The screen that stands between a click and overwriting live content.
     * Row counts are the part that catches a wrong file — a filename does not.
     */
    public function confirm(string $file)
    {
        abort_unless($this->backups->isRestorable($file), 404);

        return view('admin.database.confirm', [
            'file' => $file,
            'header' => $this->backups->header($file),
            'live' => $this->liveCounts(),
            'host' => $this->backups->hostOf($file),
            'tables' => (array) config('database_admin.tables'),
        ]);
    }

    /** @return array<string, int> */
    private function liveCounts(): array
    {
        $counts = [];

        foreach ((array) config('database_admin.tables') as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
```

- [ ] **Step 6: Create the view**

Create `resources/views/admin/database/confirm.blade.php`:

```blade
@extends('layouts.admin')

@section('title', 'Restore backup')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">Restore backup</h2>
        </div>

        <div class="adm-stack">
            <div class="adm-panel">
                <div class="adm-panel__head">
                    <h3>{{ $file }}</h3>
                    <span class="adm-panel__grow"></span>
                    <span class="adm-note">created {{ $header['created']?->format('j M Y, H:i') ?? 'unknown' }}</span>
                </div>

                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>In backup</th>
                            <th>Live now</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tables as $table)
                            <tr>
                                <td>{{ $table }}</td>
                                <td class="is-data">{{ $header['rows'][$table] ?? '—' }}</td>
                                <td class="is-data">{{ $live[$table] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @unless ($header['rows'])
                    <div class="adm-panel__body">
                        <p class="adm-note">Row counts are unavailable — this backup was written before they were recorded. It can still be restored.</p>
                    </div>
                @endunless
            </div>

            <div class="adm-panel adm-panel--danger">
                <div class="adm-panel__head"><h3>Replace all content on this site</h3></div>
                <div class="adm-panel__body">
                    <p class="adm-note" style="margin-bottom:12px">
                        Replaces {{ implode(', ', $tables) }}. A snapshot of the current content is
                        taken automatically first. Accounts and sessions are not touched.
                    </p>

                    @error('restore')
                        <p class="adm-err">{{ $message }}</p>
                    @enderror

                    <form method="POST" action="{{ route('admin.database.restore', $file) }}">
                        @csrf
                        <div class="adm-field">
                            <label for="confirm-host">Type {{ $host }} to confirm</label>
                            <input id="confirm-host" type="text" autocomplete="off" data-confirm="{{ $host }}">
                        </div>
                        <div class="adm-actions">
                            <a class="adm-btn" href="{{ route('admin.database.index') }}">Cancel</a>
                            <button class="adm-btn adm-btn--danger" type="submit" disabled>Restore</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
<script>
    // Friction for a human, not a control: the server gates on the host in the
    // filename, not on this field.
    (function () {
        var input = document.getElementById('confirm-host');
        var button = input.form.querySelector('button[type=submit]');

        input.addEventListener('input', function () {
            button.disabled = input.value.trim() !== input.dataset.confirm;
        });
    })();
</script>
@endpush
```

- [ ] **Step 7: Add the Restore link to the listing**

In `resources/views/admin/database/index.blade.php`, replace the actions cell (the `<td>` holding the delete form):

```blade
                                    <td>
                                        <div class="adm-actions">
                                            @if ($backup['restorable'])
                                                <a class="adm-btn adm-btn--sm" href="{{ route('admin.database.restore.confirm', $backup['name']) }}">Restore</a>
                                            @endif
                                            <form method="POST" action="{{ route('admin.database.destroy', $backup['name']) }}"
                                                  onsubmit="return confirm('Delete this backup?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="adm-btn adm-btn--sm adm-btn--danger" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --filter=AdminDatabaseRestoreTest`
Expected: PASS

- [ ] **Step 9: Run the full suite**

Run: `php artisan test`
Expected: PASS. `AdminDatabasePullTest::test_the_manual_restore_upload_route_is_removed` posts to `/admin/database/restore` with no file segment and must still get 404 — the new routes all require `{file}`.

- [ ] **Step 10: Run the CI gate and commit**

```bash
vendor/bin/pint --test
php artisan test
composer audit --no-dev
git add routes/web.php app/Http/Controllers/Admin/DatabaseController.php app/Services/Database/BackupRepository.php resources/views/admin/database/ tests/Feature/AdminDatabaseRestoreTest.php
git commit -m "feat: confirmation screen for restoring a backup

Shows the backup's per-table row counts beside the live ones and asks for
the site host to be typed. Seeing posts 41 -> 12 is what catches a wrong
file; a filename does not."
```

---

### Task 7: Execute the restore

**Files:**
- Modify: `app/Http/Controllers/Admin/DatabaseController.php` (replace the Task 6 placeholder `restore()`)
- Test: `tests/Feature/AdminDatabaseRestoreTest.php`

**Interfaces:**
- Consumes: `isRestorable()`, `path()`, `DatabaseRestoreService::restore($path, null)` from Task 5.
- Produces: route `admin.database.restore` at `POST admin/database/restore/{file}`, redirecting to `admin.database.index` with a `status` naming the rows restored and the snapshot filename; failures come back with an error under the `restore` key.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/AdminDatabaseRestoreTest.php` (add `use App\Models\Post;`, `use App\Models\PostTranslation;` and `use App\Services\Database\DatabaseBackupService;` to the imports):

```php
    /** A real dump of current content, written through the production path. */
    private function backupCurrentContent(): string
    {
        return app(DatabaseBackupService::class)->create('manual');
    }

    public function test_restoring_brings_back_the_content_in_the_backup(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id, 'locale' => 'en',
            'title' => 'Original', 'slug' => 'original', 'body' => '<p>Body</p>',
        ]);

        $name = $this->backupCurrentContent();

        PostTranslation::query()->update(['title' => 'Wrecked']);

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertRedirect('/admin/database');

        $this->assertSame('Original', PostTranslation::first()->title);
    }

    public function test_restoring_snapshots_the_current_content_first(): void
    {
        $name = $this->backupCurrentContent();

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '-auto.sql.gz'));
    }

    public function test_restoring_a_backup_from_another_host_is_not_found(): void
    {
        $name = $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertNotFound();
    }

    public function test_restoring_a_missing_backup_is_not_found(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/database/restore/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertNotFound();
    }

    public function test_a_corrupt_backup_is_refused_and_changes_nothing(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id, 'locale' => 'en',
            'title' => 'Untouched', 'slug' => 'untouched', 'body' => '<p>Body</p>',
        ]);

        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'not gzip at all');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertRedirect()
            ->assertSessionHasErrors('restore');

        $this->assertSame('Untouched', PostTranslation::first()->title);
    }

    public function test_a_backup_naming_a_table_outside_the_allow_list_is_refused(): void
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode(
            "-- Content backup\nDELETE FROM `users`;\n"
        ));

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertRedirect()
            ->assertSessionHasErrors('restore');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_restoring_leaves_media_paths_alone(): void
    {
        config(['database_admin.media_fallback_url' => 'https://astrotherapia.com']);
        \App\Models\Media::create(['path' => 'media/photo.jpg', 'url' => '/storage/media/photo.jpg']);

        $name = $this->backupCurrentContent();

        $this->actingAs($this->admin())->post('/admin/database/restore/'.$name);

        $this->assertSame('/storage/media/photo.jpg', \App\Models\Media::first()->url);
    }

    public function test_guests_cannot_restore(): void
    {
        $this->post('/admin/database/restore/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertRedirect('/admin/login');
    }
```

Note on `test_a_backup_naming_a_table_outside_the_allow_list_is_refused`: the acting admin is the one row in `users`, and the assertion proves the rejected statement never ran.

Coverage limit, stated rather than papered over: both refusal tests fail during
validation, which runs *before* the snapshot is written — so they prove "nothing
was mutated", not "the snapshot is retained". A failure partway through the
transaction is the case that would prove the latter, and it is not reachable
without fault injection, so it stays untested. The transaction itself is
Laravel's; the snapshot is written outside it and cannot be rolled back by it.

Note on `test_restoring_leaves_media_paths_alone`: import `App\Models\Media` at the top and drop the inline `\App\Models\` prefixes when you write it — the prefixes here only keep this snippet self-contained.

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=AdminDatabaseRestoreTest`
Expected: FAIL — every POST 404s, because Task 6's placeholder `restore()` is `abort(404)`.

- [ ] **Step 3: Replace the placeholder with the real action**

In `app/Http/Controllers/Admin/DatabaseController.php`:

```php
    /**
     * Roll this site back to one of its own backups. The gate is the host in
     * the filename (BackupRepository::isRestorable), not an env flag: prod is
     * where a rollback is most needed and DB_RESTORE_ENABLED is false there.
     *
     * Media paths are deliberately not rewritten — this backup came from this
     * host, so they already resolve.
     */
    public function restore(string $file)
    {
        abort_unless($this->backups->isRestorable($file), 404);

        try {
            $result = $this->restoreService->restore($this->backups->path($file), null);
        } catch (InvalidBackupException $e) {
            return back()->withErrors(['restore' => $e->getMessage()]);
        }

        return redirect()->route('admin.database.index')->with(
            'status',
            "Restored {$file}: {$result['rows']} rows. Snapshot before overwrite: {$result['snapshot']}",
        );
    }
```

`InvalidBackupException` is already imported for `pull()`.

- [ ] **Step 4: Run them to verify they pass**

Run: `php artisan test --filter=AdminDatabaseRestoreTest`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Run the CI gate and commit**

```bash
vendor/bin/pint --test
php artisan test
composer audit --no-dev
git add app/Http/Controllers/Admin/DatabaseController.php tests/Feature/AdminDatabaseRestoreTest.php
git commit -m "feat: restore the site from one of its own backups

DatabaseRestoreService already validated, snapshotted and replayed a dump;
its only caller was the prod -> dev pull, so a listed backup could be made
and downloaded but never put back."
```

---

### Task 8: Documentation

`CLAUDE.md` requires docs to move with the change, not after it.

**Files:**
- Modify: `README.md:68` (the Database controller route list) and the "Database backups and prod → dev copy" section at `README.md:89`
- Modify: `docs/OPERATIONS.md`
- Modify: `docs/superpowers/specs/2026-07-24-admin-database-page-design.md`

- [ ] **Step 1: Update the README route list**

In the `README.md:68` bullet for `Admin\DatabaseController`, add the two routes after the delete route:

```
`GET /admin/database/restore/{file}` (confirm a restore), `POST /admin/database/restore/{file}` (restore it),
```

- [ ] **Step 2: Add a restore paragraph to the README section**

In the "Database backups and prod → dev copy" section, after the storage paragraph:

```markdown
- **Restore:** `GET /admin/database/restore/{file}` shows the backup's per-table
  row counts beside the live ones and asks for the site host to be typed;
  `POST` to the same URI replays it through `DatabaseRestoreService` (automatic
  pre-restore snapshot, statement allow-list, one transaction). Media paths are
  **not** rewritten on this path — the backup came from this host, so they
  already resolve.

  There is no environment flag. A backup is restorable when the host segment of
  its filename matches the host of `APP_URL`, so production restores
  production's backups and dev restores dev's. That rule is what makes rolling
  back live content possible while `DB_RESTORE_ENABLED` stays false on prod —
  that flag now gates only the prod → dev pull.
```

- [ ] **Step 3: Add the rollback runbook to OPERATIONS.md**

Append a section:

```markdown
## Rolling back site content

Content only — `authors`, `posts`, `post_translations`, `media`,
`site_settings`. Accounts, sessions and uploaded files are never touched, so a
rollback cannot log anyone out and cannot bring back a deleted image file.

1. Admin → **Database**. Pick the backup from before the mistake. Backups are
   listed newest first; `auto (pre-restore)` ones were written automatically
   just before an earlier restore or pull.
2. **Restore** opens a confirmation page. Check the row counts against what is
   live now — that is what catches a wrong file.
3. Type the site host and confirm. A snapshot of current content is written
   first; its filename is in the message afterwards, so the restore itself is
   reversible.

Only backups this site wrote are offered. A dev backup is not restorable on
production and vice versa.

If the admin page is unreachable, a backup is plain gzipped SQL and can be
imported through cPanel's phpMyAdmin. It carries data only — no `CREATE TABLE`
— so the schema must already exist.
```

- [ ] **Step 4: Mark the old spec superseded**

At the top of `docs/superpowers/specs/2026-07-24-admin-database-page-design.md`, under the date line:

```markdown
> **Restore superseded** by
> [`2026-08-02-database-restore-design.md`](2026-08-02-database-restore-design.md).
> The upload-based transfer described below never shipped: prod → dev became a
> direct pull over the `source` connection, and restore is now gated by the host
> in the backup filename rather than by `DB_RESTORE_ENABLED`. The backup half of
> this document still describes what ships.
```

- [ ] **Step 5: Verify no other doc contradicts the change**

Run: `grep -rn "DB_RESTORE_ENABLED" README.md docs/`
Expected: every remaining mention describes it as gating the prod → dev pull only. Fix any that still imply it gates all restore.

- [ ] **Step 6: Run the CI gate and commit**

```bash
vendor/bin/pint --test
php artisan test
composer audit --no-dev
git add README.md docs/
git commit -m "docs: document restoring a backup and the host-match rule"
```

---

## Verification

After Task 8, confirm the whole feature from a clean state:

- [ ] `vendor/bin/pint --test` — clean
- [ ] `php artisan test` — green, and the count has grown by roughly 25 tests
- [ ] `composer audit --no-dev` — no advisories
- [ ] `php artisan route:list --path=database` shows six routes: index, backup, download, destroy, restore.confirm, restore, plus pull
- [ ] `git log --oneline -8` shows one commit per task

Do not deploy. Committing is where this work stops; the owner pushes to the host.
