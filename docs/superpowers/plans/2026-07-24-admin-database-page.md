# Admin Database Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `Database` page to the admin area that backs up the site's content tables and, on the dev subdomain only, restores a backup taken from prod.

**Architecture:** A pure-PHP dumper writes the four content tables to a gzipped `.sql` file on the private disk, one SQL statement per physical line so restore can split on newlines without parsing SQL. Restore validates that every statement targets an allow-listed table, snapshots the current database, then replays the file inside one transaction and rewrites media paths to absolute prod URLs. Restore is gated on a fail-closed `DB_RESTORE_ENABLED` flag, because both boxes deploy the same artifact with `APP_ENV=production` and the framework cannot otherwise tell them apart.

**Tech Stack:** PHP 8.4, Laravel 12, MySQL (servers), SQLite in-memory (tests), `ext-zlib` (`gzopen`/`gzgets`/`gzwrite`), Blade, PHPUnit, Laravel Pint.

**Design spec:** [`docs/superpowers/specs/2026-07-24-admin-database-page-design.md`](../specs/2026-07-24-admin-database-page-design.md)

## Global Constraints

These apply to every task below.

- **TDD is mandatory** per [`CLAUDE.md`](../../../CLAUDE.md). Write the failing test, run it scoped, confirm it fails for the right reason, then write the minimal code. No production code without a failing test first.
- **Run the full suite (`php artisan test`) before every commit**, not just the scoped test. This repo has cross-cutting state (`SiteSetting.sections`, the active theme, locale) that unrelated tests assert against.
- **Run `vendor/bin/pint` before every commit.** CI fails on style (`.github/workflows/cicd.yml`, the `lint` job runs `vendor/bin/pint --test`).
- **Content tables are exactly:** `posts`, `post_translations`, `media`, `site_settings`. Never `users`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.
- **Table order is significant.** Config lists parents first: `['posts', 'post_translations', 'media', 'site_settings']`. Inserts follow that order; deletes use `array_reverse` of it. `post_translations.post_id` has a cascading foreign key onto `posts` (`database/migrations/2026_06_26_000003_create_post_translations_table.php:16`).
- **Every generated SQL statement occupies exactly one physical line.** CKEditor bodies contain semicolons (`&nbsp;`) and newlines, so a dump can only be split safely on `\n`.
- **Restore never runs on prod.** `config('database_admin.restore_enabled')` defaults to `false`; only the dev GitHub environment sets it true.
- **Backups live on the `local` disk**, whose root is `storage_path('app/private')` (`config/filesystems.php:33-36`). Never the `public` disk.
- **Test helper used throughout:** `User::factory()->create(['is_admin' => true])`, matching `tests/Feature/AdminThemesTest.php:14-17`.
- **Doc upkeep is part of the change, not a follow-up** (`CLAUDE.md`). Handled in Task 9.

### Deviation from the spec, decided during planning

The spec said the restore *route* would only be registered when the flag is on. This plan instead always registers the route and has the controller `abort_unless(config('database_admin.restore_enabled'), 404)`, with the view hiding the form on the same condition.

Reason: routes are cached on the server (`public/deploy.php` runs `route:cache`), so conditional registration bakes the flag into the route cache and makes the behaviour depend on deploy ordering. It is also untestable without booting a second application instance. The externally visible behaviour is identical — prod returns **404** for `POST admin/database/restore` and renders no form. Task 9 updates the spec to match.

---

### Task 1: Config, dashboard link, and the empty Database page

Delivers a reachable admin page with the config that every later task reads.

**Files:**
- Create: `config/database_admin.php`
- Create: `resources/views/admin/database/index.blade.php`
- Create: `app/Http/Controllers/Admin/DatabaseController.php`
- Modify: `config/filesystems.php:31-70` (register the `backups` disk)
- Modify: `public/deploy.php:61-69` (add `database/backups` to the ensure-loop)
- Modify: `.gitignore` (ignore `/database/backups`)
- Modify: `routes/web.php:9` (imports), `routes/web.php:24-27` (route group)
- Modify: `resources/views/admin/dashboard.blade.php:15-17`
- Test: `tests/Feature/AdminDatabasePageTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `config('database_admin.tables')` → `array<int,string>`; `config('database_admin.retention')` → `int`; `config('database_admin.restore_enabled')` → `bool`; `config('database_admin.media_fallback_url')` → `?string`; `config('database_admin.max_upload_kilobytes')` → `int`. Route names `admin.database.index`. Controller class `App\Http\Controllers\Admin\DatabaseController` with method `index()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminDatabasePageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDatabasePageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_dashboard_links_to_the_database_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Database');
    }

    public function test_database_page_renders_for_an_admin(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Backups');
    }

    public function test_guests_cannot_access_the_database_page(): void
    {
        $this->get('/admin/database')->assertRedirect('/admin/login');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AdminDatabasePageTest`

Expected: FAIL. `test_database_page_renders_for_an_admin` returns 404 because the route does not exist.

- [ ] **Step 3: Create the config file and register the backups disk**

Create `config/database_admin.php`:

```php
<?php

// Admin Database page: content backups, and prod -> dev restore on the dev
// subdomain. See docs/superpowers/specs/2026-07-24-admin-database-page-design.md.
return [

    /*
     * Fail-closed restore gate. Both boxes deploy the same artifact with
     * APP_ENV=production (.github/scripts/make-env.sh:14), so the framework
     * cannot tell dev from prod. This flag is the only thing that can, and a
     * missing value means disabled.
     */
    'restore_enabled' => (bool) env('DB_RESTORE_ENABLED', false),

    /*
     * Absolute origin that dev rewrites media paths to, e.g.
     * https://astrotherapia.com. Media URLs are stored root-relative
     * (AttachmentController.php:38-41), so prod content copied to dev would
     * otherwise point at files dev does not have. Null means no rewrite.
     */
    'media_fallback_url' => env('MEDIA_FALLBACK_URL'),

    // Backups kept on disk; older files are pruned after each new backup.
    'retention' => 10,

    // Largest accepted restore upload, in kilobytes (Laravel's `max:` unit).
    'max_upload_kilobytes' => 20480,

    /*
     * Content tables only. users/sessions/cache/jobs are deliberately excluded
     * so a mistaken restore cannot damage accounts or log anyone out.
     *
     * Order matters: parents first. Inserts follow this order; deletes use the
     * reverse, so post_translations goes before its parent posts.
     */
    'tables' => ['posts', 'post_translations', 'media', 'site_settings'],
];
```

Register a dedicated private disk for the backups. In `config/filesystems.php`,
add to the `'disks'` array (after the `'local'` disk):

```php
        // Content backups written by the admin Database page. Rooted at the
        // project's database/ dir; every access is scoped to the backups/
        // subfolder via BackupRepository::DIRECTORY, so migrations and the dev
        // sqlite file are never touched. No `serve`/`url`: these dumps are
        // content exports and must never be web-reachable — downloads go
        // through DatabaseController::download() on a private disk.
        'backups' => [
            'driver' => 'local',
            'root' => database_path(),
            'throw' => false,
            'report' => false,
        ],
```

The location survives deploys. `public/extract.php` unzips the CI archive with
`extractTo()`, which *overlays* — it never wipes the tree — and `make-archive.php`
never includes a `database/backups/` (the folder is created at runtime, not in
the repo). So a deploy writes the archive on top and leaves existing backups
untouched, exactly as `storage/` is preserved.

Two consequences of putting runtime-writable files under `database/` (which,
unlike `storage/`, *does* ship in the archive):

1. The FTP sync skips empty dirs, so the folder must be created server-side. In
   `public/deploy.php`, add `database/backups` to the ensure-loop
   (`public/deploy.php:61-69`):

   ```php
       'storage/logs', 'bootstrap/cache',
       'database/backups',
   ```

2. Backup files must never be committed. In `.gitignore`, add:

   ```
   /database/backups
   ```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/DatabaseController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DatabaseController extends Controller
{
    public function index()
    {
        return view('admin.database.index', [
            'backups' => collect(),
            'restoreEnabled' => (bool) config('database_admin.restore_enabled'),
        ]);
    }
}
```

- [ ] **Step 5: Create the view**

Create `resources/views/admin/database/index.blade.php`. Markup follows `resources/views/admin/themes/index.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Database')

@section('content')
    <div class="container">
        <h1>Database</h1>

        @if(session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <h2>Backups</h2>

        @if($backups->isEmpty())
            <p class="muted">No backups yet.</p>
        @endif
    </div>
@endsection
```

- [ ] **Step 6: Register the route**

In `routes/web.php`, add the import next to the other `Admin\` imports (they are alphabetical, so this goes after `AuthController`):

```php
use App\Http\Controllers\Admin\DatabaseController;
```

Inside the `Route::prefix('admin')->middleware('admin')` group, after the themes routes:

```php
    Route::get('database', [DatabaseController::class, 'index'])->name('admin.database.index');
```

- [ ] **Step 7: Add the dashboard link**

In `resources/views/admin/dashboard.blade.php`, after the Themes `@if` block:

```blade
            @if (Route::has('admin.database.index'))
                <li><a href="{{ route('admin.database.index') }}">Database</a></li>
            @endif
```

- [ ] **Step 8: Run the scoped test**

Run: `php artisan test --filter=AdminDatabasePageTest`

Expected: PASS, 3 tests.

- [ ] **Step 9: Run the full suite and lint**

Run: `php artisan test` — expected: all green.
Run: `vendor/bin/pint` — expected: files formatted, no errors.

- [ ] **Step 10: Commit**

```bash
git add config/database_admin.php config/filesystems.php public/deploy.php .gitignore app/Http/Controllers/Admin/DatabaseController.php resources/views/admin/database/index.blade.php resources/views/admin/dashboard.blade.php routes/web.php tests/Feature/AdminDatabasePageTest.php
git commit -m "feat: add the admin Database page shell, config and backups disk"
```

---

### Task 2: BackupRepository

Lists, prunes, deletes and resolves backup files. Pure filesystem work, no database.

**Files:**
- Create: `app/Services/Database/BackupRepository.php`
- Test: `tests/Unit/BackupRepositoryTest.php`

**Interfaces:**
- Consumes: `config('database_admin.retention')` from Task 1.
- Produces:
  - `BackupRepository::DIRECTORY` → `string` (`'backups'`)
  - `BackupRepository::FILENAME_PATTERN` → `string` (a PCRE pattern including delimiters)
  - `BackupRepository::filename(string $origin): string` — builds `backup-{YmdHis}-{host}-{origin}.sql.gz`
  - `BackupRepository::all(): \Illuminate\Support\Collection` of `array{name: string, size: int, origin: string}`, newest first
  - `BackupRepository::prune(int $keep): void`
  - `BackupRepository::delete(string $name): void`
  - `BackupRepository::path(string $name): string` — absolute path; throws `InvalidArgumentException` for a non-matching name

Note on the filename: the **timestamp comes before the host**, so a plain descending lexical sort orders newest first. Host-first would sort by hostname and interleave prod dumps with dev snapshots.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/BackupRepositoryTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Database\BackupRepository;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class BackupRepositoryTest extends TestCase
{
    private BackupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        $this->repository = new BackupRepository;
    }

    private function putBackup(string $name): void
    {
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'x');
    }

    public function test_it_lists_backups_newest_first(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');
        $this->putBackup('backup-20260301-000000-example.com-manual.sql.gz');
        $this->putBackup('backup-20260201-000000-example.com-auto.sql.gz');

        $names = $this->repository->all()->pluck('name')->all();

        $this->assertSame([
            'backup-20260301-000000-example.com-manual.sql.gz',
            'backup-20260201-000000-example.com-auto.sql.gz',
            'backup-20260101-000000-example.com-manual.sql.gz',
        ], $names);
    }

    public function test_it_labels_the_origin(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-auto.sql.gz');

        $this->assertSame('auto (pre-restore)', $this->repository->all()->first()['origin']);
    }

    public function test_it_ignores_files_that_are_not_backups(): void
    {
        $this->putBackup('notes.txt');
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->assertCount(1, $this->repository->all());
    }

    public function test_prune_keeps_only_the_newest_n(): void
    {
        foreach (['20260101', '20260102', '20260103', '20260104'] as $day) {
            $this->putBackup("backup-{$day}-000000-example.com-manual.sql.gz");
        }

        $this->repository->prune(2);

        $this->assertSame([
            'backup-20260104-000000-example.com-manual.sql.gz',
            'backup-20260103-000000-example.com-manual.sql.gz',
        ], $this->repository->all()->pluck('name')->all());
    }

    public function test_delete_removes_a_backup(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->repository->delete('backup-20260101-000000-example.com-manual.sql.gz');

        $this->assertCount(0, $this->repository->all());
    }

    public function test_delete_rejects_a_traversal_attempt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->delete('../../.env');
    }

    public function test_path_rejects_a_traversal_attempt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->path('../../.env');
    }

    public function test_filename_encodes_origin_and_is_pattern_valid(): void
    {
        $name = $this->repository->filename('auto');

        $this->assertMatchesRegularExpression(BackupRepository::FILENAME_PATTERN, $name);
        $this->assertStringEndsWith('-auto.sql.gz', $name);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=BackupRepositoryTest`

Expected: FAIL with `Class "App\Services\Database\BackupRepository" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Services/Database/BackupRepository.php`:

```php
<?php

namespace App\Services\Database;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Backup files live on the private `backups` disk, rooted at the project's
 * database/ directory, under database/backups/. Never the public disk: these
 * dumps are content exports and must not be web-reachable. Downloads go through
 * DatabaseController::download().
 *
 * The location survives deploys: public/extract.php overlays the CI archive
 * (it never wipes the tree) and database/backups/ is not an archive entry, so
 * existing backups are left untouched — the same guarantee storage/ gets.
 */
class BackupRepository
{
    public const DIRECTORY = 'backups';

    /**
     * backup-{YmdHis}-{host}-{origin}.sql.gz
     *
     * The timestamp leads so a descending lexical sort is newest-first; a
     * host-first name would sort by hostname and interleave prod dumps with
     * dev's pre-restore snapshots.
     */
    public const FILENAME_PATTERN = '/^backup-\d{8}-\d{6}-[A-Za-z0-9.-]+-(manual|auto)\.sql\.gz$/';

    /** @param  'manual'|'auto'  $origin */
    public function filename(string $origin): string
    {
        $host = preg_replace('/[^A-Za-z0-9.-]/', '-', (string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return sprintf('backup-%s-%s-%s.sql.gz', now()->format('Ymd-His'), $host ?: 'local', $origin);
    }

    /** @return Collection<int, array{name: string, size: int, origin: string}> */
    public function all(): Collection
    {
        $disk = Storage::disk('backups');

        return collect($disk->files(self::DIRECTORY))
            ->map(fn (string $path) => basename($path))
            ->filter(fn (string $name) => (bool) preg_match(self::FILENAME_PATTERN, $name))
            ->sortDesc()
            ->values()
            ->map(fn (string $name) => [
                'name' => $name,
                'size' => $disk->size(self::DIRECTORY.'/'.$name),
                'origin' => str_ends_with($name, '-auto.sql.gz') ? 'auto (pre-restore)' : 'manual',
            ]);
    }

    public function prune(int $keep): void
    {
        $this->all()->slice($keep)->each(fn (array $backup) => $this->delete($backup['name']));
    }

    public function delete(string $name): void
    {
        Storage::disk('backups')->delete(self::DIRECTORY.'/'.$this->assertValid($name));
    }

    /** Absolute filesystem path to a backup. */
    public function path(string $name): string
    {
        return Storage::disk('backups')->path(self::DIRECTORY.'/'.$this->assertValid($name));
    }

    /** The only place a caller-supplied filename is trusted. */
    private function assertValid(string $name): string
    {
        if (! preg_match(self::FILENAME_PATTERN, $name)) {
            throw new InvalidArgumentException('Not a backup filename.');
        }

        return $name;
    }
}
```

- [ ] **Step 4: Run the scoped test**

Run: `php artisan test --filter=BackupRepositoryTest`

Expected: PASS, 8 tests.

- [ ] **Step 5: Run the full suite and lint**

Run: `php artisan test` then `vendor/bin/pint`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Database/BackupRepository.php tests/Unit/BackupRepositoryTest.php
git commit -m "feat: add BackupRepository for listing and pruning backup files"
```

---

### Task 3: SQL value quoter

The one-statement-per-line rule needs newlines encoded inside string literals. MySQL and SQLite disagree on how, so this is a driver-selected seam. Without it, a dump generated under SQLite in tests replays as literal `\n` text, and the round-trip test in Task 8 cannot pass.

**Files:**
- Create: `app/Services/Database/SqlQuoter.php` (interface)
- Create: `app/Services/Database/MySqlQuoter.php`
- Create: `app/Services/Database/SqliteQuoter.php`
- Create: `app/Services/Database/QuoterFactory.php`
- Test: `tests/Unit/SqlQuoterTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `interface SqlQuoter { public function quote(mixed $value): string; }`
  - `MySqlQuoter implements SqlQuoter`, `SqliteQuoter implements SqlQuoter`
  - `QuoterFactory::for(string $driver): SqlQuoter` — accepts `'mysql'`, `'mariadb'`, `'sqlite'`; throws `InvalidArgumentException` otherwise

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SqlQuoterTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Database\MySqlQuoter;
use App\Services\Database\QuoterFactory;
use App\Services\Database\SqliteQuoter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SqlQuoterTest extends TestCase
{
    public function test_mysql_quoter_escapes_quotes_backslashes_and_newlines(): void
    {
        $quoter = new MySqlQuoter;

        $this->assertSame("'plain'", $quoter->quote('plain'));
        $this->assertSame("'O\\'Brien'", $quoter->quote("O'Brien"));
        $this->assertSame("'a\\\\b'", $quoter->quote('a\\b'));
        $this->assertSame("'a\\nb'", $quoter->quote("a\nb"));
    }

    public function test_mysql_quoter_handles_non_strings(): void
    {
        $quoter = new MySqlQuoter;

        $this->assertSame('NULL', $quoter->quote(null));
        $this->assertSame('42', $quoter->quote(42));
        $this->assertSame('1', $quoter->quote(true));
        $this->assertSame('0', $quoter->quote(false));
    }

    public function test_sqlite_quoter_doubles_quotes_and_splits_newlines(): void
    {
        $quoter = new SqliteQuoter;

        $this->assertSame("'plain'", $quoter->quote('plain'));
        $this->assertSame("'O''Brien'", $quoter->quote("O'Brien"));
        $this->assertSame("'a'||char(10)||'b'", $quoter->quote("a\nb"));
        $this->assertSame("''", $quoter->quote(''));
    }

    public function test_every_quoted_value_stays_on_one_line(): void
    {
        foreach ([new MySqlQuoter, new SqliteQuoter] as $quoter) {
            $this->assertStringNotContainsString("\n", $quoter->quote("a\nb\r\nc"));
            $this->assertStringNotContainsString("\r", $quoter->quote("a\nb\r\nc"));
        }
    }

    public function test_sqlite_quoted_value_round_trips_through_the_database(): void
    {
        $quoter = new SqliteQuoter;
        $value = "line one\nline two 'quoted' & \\slashed\\";

        $row = DB::selectOne('SELECT '.$quoter->quote($value).' AS v');

        $this->assertSame($value, $row->v);
    }

    public function test_factory_selects_by_driver(): void
    {
        $this->assertInstanceOf(SqliteQuoter::class, QuoterFactory::for('sqlite'));
        $this->assertInstanceOf(MySqlQuoter::class, QuoterFactory::for('mysql'));
        $this->assertInstanceOf(MySqlQuoter::class, QuoterFactory::for('mariadb'));
    }

    public function test_factory_rejects_an_unsupported_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QuoterFactory::for('pgsql');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=SqlQuoterTest`

Expected: FAIL with `Class "App\Services\Database\MySqlQuoter" not found`.

- [ ] **Step 3: Write the interface**

Create `app/Services/Database/SqlQuoter.php`:

```php
<?php

namespace App\Services\Database;

/**
 * Renders a PHP value as a SQL literal that never contains a raw newline.
 *
 * Dumps put one statement per physical line so restore can split on "\n"
 * without parsing SQL — CKEditor bodies are full of semicolons, so splitting
 * on ";" is not an option. Encoding newlines inside a string literal is
 * dialect-specific, which is why this is an interface.
 */
interface SqlQuoter
{
    public function quote(mixed $value): string;
}
```

- [ ] **Step 4: Write the MySQL quoter**

Create `app/Services/Database/MySqlQuoter.php`:

```php
<?php

namespace App\Services\Database;

class MySqlQuoter implements SqlQuoter
{
    public function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Backslash first: escaping it after the others would double-escape
        // the backslashes this very call introduces.
        return "'".str_replace(
            ['\\', "'", "\n", "\r", "\0", "\x1a"],
            ['\\\\', "\\'", '\\n', '\\r', '\\0', '\\Z'],
            (string) $value
        )."'";
    }
}
```

- [ ] **Step 5: Write the SQLite quoter**

Create `app/Services/Database/SqliteQuoter.php`:

```php
<?php

namespace App\Services\Database;

class SqliteQuoter implements SqlQuoter
{
    public function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // SQLite has no backslash escapes: a quote is doubled, and a newline
        // can only stay off the line by being concatenated in as char(10).
        $escaped = str_replace("'", "''", (string) $value);
        $segments = preg_split('/(\r\n|\n|\r)/', $escaped, -1, PREG_SPLIT_DELIM_CAPTURE);

        $parts = [];

        foreach ($segments as $index => $segment) {
            if ($index % 2 === 1) {
                $parts[] = match ($segment) {
                    "\r\n" => 'char(13)||char(10)',
                    "\r" => 'char(13)',
                    default => 'char(10)',
                };
            } elseif ($segment !== '') {
                $parts[] = "'".$segment."'";
            }
        }

        return $parts === [] ? "''" : implode('||', $parts);
    }
}
```

- [ ] **Step 6: Write the factory**

Create `app/Services/Database/QuoterFactory.php`:

```php
<?php

namespace App\Services\Database;

use InvalidArgumentException;

class QuoterFactory
{
    public static function for(string $driver): SqlQuoter
    {
        return match ($driver) {
            'mysql', 'mariadb' => new MySqlQuoter,
            'sqlite' => new SqliteQuoter,
            default => throw new InvalidArgumentException("No SQL quoter for driver [{$driver}]."),
        };
    }
}
```

- [ ] **Step 7: Run the scoped test**

Run: `php artisan test --filter=SqlQuoterTest`

Expected: PASS, 7 tests.

- [ ] **Step 8: Run the full suite and lint**

Run: `php artisan test` then `vendor/bin/pint`

- [ ] **Step 9: Commit**

```bash
git add app/Services/Database/SqlQuoter.php app/Services/Database/MySqlQuoter.php app/Services/Database/SqliteQuoter.php app/Services/Database/QuoterFactory.php tests/Unit/SqlQuoterTest.php
git commit -m "feat: add driver-specific SQL quoters that keep statements on one line"
```

---

### Task 4: DatabaseBackupService

Writes the gzipped dump.

**Files:**
- Create: `app/Services/Database/DatabaseBackupService.php`
- Test: `tests/Feature/DatabaseBackupTest.php`

**Interfaces:**
- Consumes: `BackupRepository::filename()`, `BackupRepository::path()`, `BackupRepository::DIRECTORY` (Task 2); `QuoterFactory::for()` (Task 3); `config('database_admin.tables')` (Task 1).
- Produces: `DatabaseBackupService::create(string $origin = 'manual'): string` — returns the backup **filename** (not a path).

The dump contains no `SET FOREIGN_KEY_CHECKS` statements. Deletes run children-first and inserts parents-first, so foreign keys are never violated and the file stays portable.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DatabaseBackupTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function contents(string $name): string
    {
        return (string) gzdecode(Storage::disk('backups')->get(BackupRepository::DIRECTORY.'/'.$name));
    }

    public function test_it_writes_a_gzipped_backup_containing_seeded_rows(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'body' => '<p>Body</p>',
        ]);

        $name = app(DatabaseBackupService::class)->create('manual');
        $sql = $this->contents($name);

        $this->assertStringContainsString('INSERT INTO `posts`', $sql);
        $this->assertStringContainsString('Hello World', $sql);
        $this->assertStringContainsString('DELETE FROM `post_translations`;', $sql);
    }

    public function test_deletes_run_children_before_parents(): void
    {
        $name = app(DatabaseBackupService::class)->create('manual');
        $sql = $this->contents($name);

        $this->assertLessThan(
            strpos($sql, 'DELETE FROM `posts`;'),
            strpos($sql, 'DELETE FROM `post_translations`;'),
        );
    }

    public function test_every_statement_stays_on_one_line(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => "Multi\nLine; Title 'quoted'",
            'slug' => 'multi-line',
            'body' => "<p>one</p>\n<p>two; three</p>\r\n<p>four</p>",
        ]);

        $sql = $this->contents(app(DatabaseBackupService::class)->create('manual'));

        foreach (explode("\n", trim($sql)) as $line) {
            if ($line === '' || str_starts_with($line, '--')) {
                continue;
            }
            $this->assertStringEndsWith(';', $line, "Statement was split across lines: {$line}");
        }
    }

    public function test_it_never_dumps_excluded_tables(): void
    {
        $sql = $this->contents(app(DatabaseBackupService::class)->create('manual'));

        foreach (['users', 'sessions', 'cache', 'jobs'] as $table) {
            $this->assertStringNotContainsString('`'.$table.'`', $sql);
        }
    }

    public function test_the_origin_is_recorded_in_the_filename(): void
    {
        $this->assertStringEndsWith('-auto.sql.gz', app(DatabaseBackupService::class)->create('auto'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DatabaseBackupTest`

Expected: FAIL with `Target class [App\Services\Database\DatabaseBackupService] does not exist` or `Class ... not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Services/Database/DatabaseBackupService.php`:

```php
<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pure-PHP dumper. The host disables exec() (.github/scripts/make-env.sh:45),
 * so mysqldump is unavailable and every byte here is written over PDO.
 *
 * Rows are read in chunks and written straight into the gzip stream, so memory
 * stays flat regardless of table size.
 */
class DatabaseBackupService
{
    private const CHUNK = 500;

    public function __construct(private BackupRepository $backups) {}

    /**
     * @param  'manual'|'auto'  $origin
     * @return string the backup filename
     */
    public function create(string $origin = 'manual'): string
    {
        Storage::disk('backups')->makeDirectory(BackupRepository::DIRECTORY);

        $name = $this->backups->filename($origin);
        $handle = gzopen($this->backups->path($name), 'wb6');

        if ($handle === false) {
            throw new RuntimeException('Could not open the backup file for writing.');
        }

        try {
            $this->writeDump($handle);
        } finally {
            gzclose($handle);
        }

        return $name;
    }

    /** @param  resource  $handle */
    private function writeDump($handle): void
    {
        $tables = (array) config('database_admin.tables');
        $quoter = QuoterFactory::for(DB::connection()->getDriverName());

        $this->line($handle, '-- Content backup');
        $this->line($handle, '-- source: '.config('app.url'));
        $this->line($handle, '-- created: '.now()->toIso8601String());
        $this->line($handle, '-- tables: '.implode(', ', $tables));

        // Children first so the cascading foreign key on post_translations is
        // never the thing doing the deleting.
        foreach (array_reverse($tables) as $table) {
            $this->line($handle, 'DELETE FROM `'.$table.'`;');
        }

        // Parents first, so every child row has its parent present.
        foreach ($tables as $table) {
            $this->writeTable($handle, $table, $quoter);
        }
    }

    /** @param  resource  $handle */
    private function writeTable($handle, string $table, SqlQuoter $quoter): void
    {
        $columns = Schema::getColumnListing($table);

        if ($columns === []) {
            return;
        }

        $columnList = implode(', ', array_map(fn (string $c) => '`'.$c.'`', $columns));

        DB::table($table)->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($handle, $table, $columns, $columnList, $quoter) {
            $tuples = [];

            foreach ($rows as $row) {
                $values = array_map(
                    fn (string $column) => $quoter->quote(((array) $row)[$column] ?? null),
                    $columns
                );
                $tuples[] = '('.implode(',', $values).')';
            }

            $this->line($handle, 'INSERT INTO `'.$table.'` ('.$columnList.') VALUES '.implode(',', $tuples).';');
        });
    }

    /** @param  resource  $handle */
    private function line($handle, string $text): void
    {
        gzwrite($handle, $text."\n");
    }
}
```

- [ ] **Step 4: Run the scoped test**

Run: `php artisan test --filter=DatabaseBackupTest`

Expected: PASS, 5 tests.

If `test_every_statement_stays_on_one_line` fails, the quoter from Task 3 is letting a raw newline through — fix the quoter, not this service.

- [ ] **Step 5: Run the full suite and lint**

Run: `php artisan test` then `vendor/bin/pint`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Database/DatabaseBackupService.php tests/Feature/DatabaseBackupTest.php
git commit -m "feat: add a pure-PHP gzipped content dumper"
```

---

### Task 5: Wire up backup, download and delete

Makes the page do something. Restore is still absent.

**Files:**
- Modify: `app/Http/Controllers/Admin/DatabaseController.php`
- Modify: `resources/views/admin/database/index.blade.php`
- Modify: `routes/web.php` (the admin group)
- Test: `tests/Feature/AdminDatabasePageTest.php` (extend)

**Interfaces:**
- Consumes: `DatabaseBackupService::create()` (Task 4); `BackupRepository::all()`, `prune()`, `delete()`, `path()` (Task 2).
- Produces: routes `admin.database.backup`, `admin.database.download`, `admin.database.destroy`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/AdminDatabasePageTest.php` (add `use Illuminate\Support\Facades\Storage;` and `use App\Services\Database\BackupRepository;` to the imports):

```php
    public function test_admin_can_create_a_backup(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->post('/admin/database/backup')
            ->assertRedirect('/admin/database');

        $this->assertCount(1, Storage::disk('backups')->files(BackupRepository::DIRECTORY));
    }

    public function test_the_page_lists_existing_backups(): void
    {
        Storage::fake('local');
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/backup-20260101-000000-example.com-manual.sql.gz', 'x');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('backup-20260101-000000-example.com-manual.sql.gz');
    }

    public function test_admin_can_download_a_backup(): void
    {
        Storage::fake('local');
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'x');

        $this->actingAs($this->admin())
            ->get('/admin/database/backup/'.$name)
            ->assertOk()
            ->assertDownload($name);
    }

    public function test_download_rejects_a_path_traversal_attempt(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database/backup/..%2F..%2F.env')
            ->assertNotFound();
    }

    public function test_admin_can_delete_a_backup(): void
    {
        Storage::fake('local');
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'x');

        $this->actingAs($this->admin())
            ->delete('/admin/database/backup/'.$name)
            ->assertRedirect('/admin/database');

        $this->assertCount(0, Storage::disk('backups')->files(BackupRepository::DIRECTORY));
    }

    public function test_creating_a_backup_prunes_beyond_the_retention_limit(): void
    {
        Storage::fake('local');
        config(['database_admin.retention' => 2]);

        foreach (['20260101', '20260102', '20260103'] as $day) {
            Storage::disk('backups')->put(BackupRepository::DIRECTORY."/backup-{$day}-000000-example.com-manual.sql.gz", 'x');
        }

        $this->actingAs($this->admin())->post('/admin/database/backup');

        $this->assertCount(2, Storage::disk('backups')->files(BackupRepository::DIRECTORY));
    }

    public function test_guests_cannot_create_a_backup(): void
    {
        $this->post('/admin/database/backup')->assertRedirect('/admin/login');
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=AdminDatabasePageTest`

Expected: FAIL — the new routes return 404.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, replace the single database line from Task 1 with:

```php
    Route::get('database', [DatabaseController::class, 'index'])->name('admin.database.index');
    Route::post('database/backup', [DatabaseController::class, 'backup'])->name('admin.database.backup');
    Route::get('database/backup/{file}', [DatabaseController::class, 'download'])
        ->where('file', '[A-Za-z0-9.\-]+\.sql\.gz')
        ->name('admin.database.download');
    Route::delete('database/backup/{file}', [DatabaseController::class, 'destroy'])
        ->where('file', '[A-Za-z0-9.\-]+\.sql\.gz')
        ->name('admin.database.destroy');
```

The `where` constraint is the first of two gates on the filename; `BackupRepository::assertValid()` is the second.

- [ ] **Step 4: Fill in the controller**

Replace `app/Http/Controllers/Admin/DatabaseController.php` with:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;

class DatabaseController extends Controller
{
    public function __construct(
        private BackupRepository $backups,
        private DatabaseBackupService $backupService,
    ) {}

    public function index()
    {
        return view('admin.database.index', [
            'backups' => $this->backups->all(),
            'restoreEnabled' => (bool) config('database_admin.restore_enabled'),
        ]);
    }

    public function backup()
    {
        $name = $this->backupService->create('manual');
        $this->backups->prune((int) config('database_admin.retention'));

        return redirect()->route('admin.database.index')->with('status', "Backup created: {$name}");
    }

    public function download(string $file)
    {
        return response()->download($this->backups->path($file));
    }

    public function destroy(string $file)
    {
        $this->backups->delete($file);

        return redirect()->route('admin.database.index')->with('status', 'Backup deleted.');
    }
}
```

- [ ] **Step 5: Convert the repository's exception into a 404**

`BackupRepository` throws `InvalidArgumentException` for a bad filename, which would surface as a 500. Register a render rule in `bootstrap/app.php` inside the existing `->withExceptions(function (Exceptions $exceptions) { ... })` closure:

```php
        $exceptions->render(function (\InvalidArgumentException $e, \Illuminate\Http\Request $request) {
            if ($request->is('admin/database/*')) {
                abort(404);
            }
        });
```

If `withExceptions` currently takes an empty closure, add the body inside it.

- [ ] **Step 6: Fill in the view**

Replace `resources/views/admin/database/index.blade.php` with:

```blade
@extends('layouts.app')

@section('title', 'Database')

@section('content')
    <div class="container">
        <h1>Database</h1>

        @if(session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <h2>Backups</h2>

        <form method="POST" action="{{ route('admin.database.backup') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Back up now</button>
        </form>

        @if($backups->isEmpty())
            <p class="muted">No backups yet.</p>
        @else
            <ul>
                @foreach($backups as $backup)
                    <li>
                        <a href="{{ route('admin.database.download', $backup['name']) }}">{{ $backup['name'] }}</a>
                        <span class="muted">{{ $backup['origin'] }} &middot; {{ number_format($backup['size'] / 1024, 1) }} KB</span>
                        <form method="POST" action="{{ route('admin.database.destroy', $backup['name']) }}"
                              onsubmit="return confirm('Delete this backup?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn" type="submit">Delete</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
```

- [ ] **Step 7: Run the scoped test**

Run: `php artisan test --filter=AdminDatabasePageTest`

Expected: PASS, 10 tests.

- [ ] **Step 8: Run the full suite and lint**

Run: `php artisan test` then `vendor/bin/pint`

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/DatabaseController.php resources/views/admin/database/index.blade.php routes/web.php bootstrap/app.php tests/Feature/AdminDatabasePageTest.php
git commit -m "feat: create, list, download and delete backups from the admin Database page"
```

---

### Task 6: MediaPathRewriter

Pure string transform, no I/O and no database. Kept separate so it is trivially testable.

**Files:**
- Create: `app/Services/Database/MediaPathRewriter.php`
- Test: `tests/Unit/MediaPathRewriterTest.php`

**Interfaces:**
- Consumes: nothing (the base URL is a constructor argument, not a config read).
- Produces:
  - `new MediaPathRewriter(?string $base)`
  - `rewriteUrl(?string $value): ?string` — for `media.url`, a bare root-relative path
  - `rewriteBody(?string $value): ?string` — for `post_translations.body`, HTML with quoted attributes

Two methods because the two columns hold different shapes: `media.url` is exactly `/storage/media/uuid.jpg`, while a body holds `src="/storage/media/uuid.jpg"`. Matching on the opening quote in bodies keeps the replacement precise.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/MediaPathRewriterTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Database\MediaPathRewriter;
use Tests\TestCase;

class MediaPathRewriterTest extends TestCase
{
    public function test_it_prefixes_a_bare_media_url(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');

        $this->assertSame(
            'https://example.com/storage/media/abc.jpg',
            $rewriter->rewriteUrl('/storage/media/abc.jpg'),
        );
    }

    public function test_it_strips_a_trailing_slash_from_the_base(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com/');

        $this->assertSame(
            'https://example.com/storage/media/abc.jpg',
            $rewriter->rewriteUrl('/storage/media/abc.jpg'),
        );
    }

    public function test_it_leaves_unrelated_urls_alone(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');

        $this->assertSame('/en/journal', $rewriter->rewriteUrl('/en/journal'));
        $this->assertSame('https://other.test/x.jpg', $rewriter->rewriteUrl('https://other.test/x.jpg'));
    }

    public function test_it_rewrites_quoted_attributes_in_a_body(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');

        $this->assertSame(
            '<img src="https://example.com/storage/media/a.jpg"><img src=\'https://example.com/storage/media/b.jpg\'>',
            $rewriter->rewriteBody('<img src="/storage/media/a.jpg"><img src=\'/storage/media/b.jpg\'>'),
        );
    }

    public function test_it_leaves_other_links_in_a_body_alone(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');
        $body = '<a href="/en/journal">Read</a>';

        $this->assertSame($body, $rewriter->rewriteBody($body));
    }

    public function test_it_is_a_no_op_without_a_base_url(): void
    {
        foreach ([null, ''] as $base) {
            $rewriter = new MediaPathRewriter($base);

            $this->assertSame('/storage/media/a.jpg', $rewriter->rewriteUrl('/storage/media/a.jpg'));
            $this->assertSame('<img src="/storage/media/a.jpg">', $rewriter->rewriteBody('<img src="/storage/media/a.jpg">'));
        }
    }

    public function test_it_handles_nulls(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');

        $this->assertNull($rewriter->rewriteUrl(null));
        $this->assertNull($rewriter->rewriteBody(null));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=MediaPathRewriterTest`

Expected: FAIL with `Class "App\Services\Database\MediaPathRewriter" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Services/Database/MediaPathRewriter.php`:

```php
<?php

namespace App\Services\Database;

/**
 * Points copied content at the source site's media.
 *
 * Uploads store root-relative URLs on purpose so they resolve on any host
 * (AttachmentController.php:38-41). That is exactly why prod content restored
 * onto dev breaks: /storage/media/x.jpg resolves against dev's docroot, and
 * the file only exists on prod's disk. Rewriting to an absolute prod URL makes
 * dev render prod's images without transferring a single file.
 */
class MediaPathRewriter
{
    private const PREFIX = '/storage/media/';

    private string $base;

    public function __construct(?string $base)
    {
        $this->base = rtrim((string) $base, '/');
    }

    /** For media.url, which holds a bare root-relative path. */
    public function rewriteUrl(?string $value): ?string
    {
        if ($value === null || $this->base === '') {
            return $value;
        }

        return str_starts_with($value, self::PREFIX) ? $this->base.$value : $value;
    }

    /** For post_translations.body, where paths sit inside quoted attributes. */
    public function rewriteBody(?string $value): ?string
    {
        if ($value === null || $this->base === '') {
            return $value;
        }

        return str_replace(
            ['"'.self::PREFIX, "'".self::PREFIX],
            ['"'.$this->base.self::PREFIX, "'".$this->base.self::PREFIX],
            $value
        );
    }
}
```

- [ ] **Step 4: Run the scoped test**

Run: `php artisan test --filter=MediaPathRewriterTest`

Expected: PASS, 7 tests.

- [ ] **Step 5: Run the full suite and lint**

Run: `php artisan test` then `vendor/bin/pint`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Database/MediaPathRewriter.php tests/Unit/MediaPathRewriterTest.php
git commit -m "feat: add MediaPathRewriter for pointing copied content at prod media"
```

---

### Task 7: DatabaseRestoreService

Validation, pre-restore snapshot, transactional replay, media rewrite.

**Files:**
- Create: `app/Services/Database/DatabaseRestoreService.php`
- Create: `app/Services/Database/InvalidBackupException.php`
- Test: `tests/Feature/DatabaseRestoreServiceTest.php`

**Interfaces:**
- Consumes: `DatabaseBackupService::create('auto')` (Task 4); `BackupRepository::path()` (Task 2); `MediaPathRewriter` (Task 6); `config('database_admin.tables')`, `config('database_admin.media_fallback_url')` (Task 1).
- Produces:
  - `DatabaseRestoreService::restore(string $archivePath): array{snapshot: string, rows: int}`
  - `InvalidBackupException extends \RuntimeException`

Takes a filesystem path rather than an `UploadedFile` so it is testable without faking an upload. Task 8 passes `$request->file('backup')->getRealPath()`.

Two passes over the archive: pass one validates every statement, pass two executes. Nothing is mutated until the whole file is known to be acceptable, and neither pass holds the file in memory.

Statements execute through `DB::unprepared()`, not `DB::statement()`. `statement()` treats `?` as a parameter placeholder and a CKEditor body will contain question marks.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DatabaseRestoreServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use App\Services\Database\DatabaseRestoreService;
use App\Services\Database\InvalidBackupException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseRestoreServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function seedPost(string $title, string $body = '<p>Body</p>'): Post
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'body' => $body,
        ]);

        return $post;
    }

    private function backupPath(string $name): string
    {
        return app(BackupRepository::class)->path($name);
    }

    /** Writes an arbitrary gzipped file into the backups directory. */
    private function fakeArchive(string $contents): string
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode($contents));

        return $this->backupPath($name);
    }

    public function test_a_dump_round_trips_through_a_restore(): void
    {
        $this->seedPost('Original', "<p>one; two</p>\n<p>three 'quoted'</p>");
        $name = app(DatabaseBackupService::class)->create('manual');

        PostTranslation::query()->delete();
        Post::query()->delete();
        $this->seedPost('Replaced Later');

        app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        $this->assertSame(1, PostTranslation::count());
        $translation = PostTranslation::first();
        $this->assertSame('Original', $translation->title);
        $this->assertSame("<p>one; two</p>\n<p>three 'quoted'</p>", $translation->body);
    }

    public function test_it_snapshots_before_mutating(): void
    {
        $this->seedPost('Original');
        $name = app(DatabaseBackupService::class)->create('manual');

        $result = app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        $this->assertStringEndsWith('-auto.sql.gz', $result['snapshot']);
        $this->assertTrue(Storage::disk('backups')->exists(BackupRepository::DIRECTORY.'/'.$result['snapshot']));
    }

    public function test_it_reports_the_number_of_restored_rows(): void
    {
        // site_settings is created lazily, so materialise it explicitly rather
        // than depending on whether something else in the test touched it.
        \App\Models\SiteSetting::current();
        $this->seedPost('One');
        $this->seedPost('Two');
        $name = app(DatabaseBackupService::class)->create('manual');

        $result = app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        // 2 posts + 2 translations + 1 site_settings row + 0 media.
        $this->assertSame(5, $result['rows']);
    }

    public function test_it_rewrites_media_paths_when_a_fallback_url_is_configured(): void
    {
        config(['database_admin.media_fallback_url' => 'https://prod.example.com']);

        Media::create(['path' => 'media/a.jpg', 'url' => '/storage/media/a.jpg']);
        $this->seedPost('With Image', '<img src="/storage/media/a.jpg">');
        $name = app(DatabaseBackupService::class)->create('manual');

        app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        $this->assertSame('https://prod.example.com/storage/media/a.jpg', Media::first()->url);
        $this->assertSame('<img src="https://prod.example.com/storage/media/a.jpg">', PostTranslation::first()->body);
    }

    public function test_it_leaves_media_paths_alone_without_a_fallback_url(): void
    {
        config(['database_admin.media_fallback_url' => null]);

        Media::create(['path' => 'media/a.jpg', 'url' => '/storage/media/a.jpg']);
        $name = app(DatabaseBackupService::class)->create('manual');

        app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        $this->assertSame('/storage/media/a.jpg', Media::first()->url);
    }

    public function test_it_rejects_a_file_that_is_not_gzipped(): void
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'not gzip at all');

        $this->expectException(InvalidBackupException::class);

        app(DatabaseRestoreService::class)->restore($this->backupPath($name));
    }

    public function test_it_rejects_a_statement_against_an_unlisted_table(): void
    {
        $path = $this->fakeArchive("DELETE FROM `users`;\n");

        $this->expectException(InvalidBackupException::class);

        app(DatabaseRestoreService::class)->restore($path);
    }

    public function test_it_rejects_a_statement_that_is_not_an_insert_or_delete(): void
    {
        $path = $this->fakeArchive("DROP TABLE `posts`;\n");

        $this->expectException(InvalidBackupException::class);

        app(DatabaseRestoreService::class)->restore($path);
    }

    public function test_a_rejected_file_leaves_existing_rows_untouched(): void
    {
        $this->seedPost('Keep Me');
        $path = $this->fakeArchive("DROP TABLE `posts`;\n");

        try {
            app(DatabaseRestoreService::class)->restore($path);
        } catch (InvalidBackupException) {
            // expected
        }

        $this->assertSame('Keep Me', PostTranslation::first()->title);
    }

    public function test_a_failure_mid_restore_rolls_back(): void
    {
        $this->seedPost('Keep Me');

        // Valid shape, but the column does not exist, so execution fails after
        // validation has already passed.
        $path = $this->fakeArchive("DELETE FROM `posts`;\nINSERT INTO `posts` (`nope`) VALUES (1);\n");

        try {
            app(DatabaseRestoreService::class)->restore($path);
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame('Keep Me', PostTranslation::first()->title);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DatabaseRestoreServiceTest`

Expected: FAIL with `Class "App\Services\Database\DatabaseRestoreService" not found`.

- [ ] **Step 3: Write the exception**

Create `app/Services/Database/InvalidBackupException.php`:

```php
<?php

namespace App\Services\Database;

use RuntimeException;

/** Thrown before anything is mutated, so the database is always untouched. */
class InvalidBackupException extends RuntimeException {}
```

- [ ] **Step 4: Write the implementation**

Create `app/Services/Database/DatabaseRestoreService.php`:

```php
<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;

/**
 * Replays a content backup into the current database.
 *
 * Only ever reached on the dev subdomain: DatabaseController gates it on
 * config('database_admin.restore_enabled'), which is false everywhere else.
 *
 * Two passes over the archive. The first validates every statement against a
 * strict allow-list; the second executes. Nothing is mutated until the whole
 * file has been accepted, and neither pass loads the file into memory.
 */
class DatabaseRestoreService
{
    /** A statement is only ever a DELETE or an INSERT against a listed table. */
    private const STATEMENT_PATTERN = '/^(?:INSERT INTO|DELETE FROM) `([A-Za-z0-9_]+)`/';

    public function __construct(
        private DatabaseBackupService $backups,
    ) {}

    /** @return array{snapshot: string, rows: int} */
    public function restore(string $archivePath): array
    {
        $this->assertGzip($archivePath);
        $this->assertStatementsAllowed($archivePath);

        $snapshot = $this->backups->create('auto');

        DB::transaction(function () use ($archivePath) {
            $this->eachStatement($archivePath, fn (string $statement) => DB::unprepared($statement));
            $this->rewriteMediaPaths();
        });

        return ['snapshot' => $snapshot, 'rows' => $this->countRows()];
    }

    private function assertGzip(string $path): void
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidBackupException('The backup file could not be read.');
        }

        $magic = fread($handle, 2);
        fclose($handle);

        if ($magic !== "\x1f\x8b") {
            throw new InvalidBackupException('That file is not a gzipped backup.');
        }
    }

    private function assertStatementsAllowed(string $path): void
    {
        $tables = (array) config('database_admin.tables');

        $this->eachStatement($path, function (string $statement) use ($tables) {
            preg_match(self::STATEMENT_PATTERN, $statement, $matches);

            if (! isset($matches[1]) || ! in_array($matches[1], $tables, true)) {
                throw new InvalidBackupException('The backup contains a statement this app will not run.');
            }
        });
    }

    /**
     * Streams the archive one statement per line. The dumper guarantees no
     * statement ever spans two physical lines, which is why splitting on "\n"
     * is safe where splitting on ";" would not be.
     */
    private function eachStatement(string $path, callable $callback): void
    {
        $handle = gzopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidBackupException('The backup file could not be opened.');
        }

        try {
            while (($line = gzgets($handle)) !== false) {
                $statement = rtrim($line, "\r\n");

                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }

                $callback($statement);
            }
        } finally {
            gzclose($handle);
        }
    }

    private function rewriteMediaPaths(): void
    {
        $rewriter = new MediaPathRewriter(config('database_admin.media_fallback_url'));

        DB::table('media')->orderBy('id')->chunkById(200, function ($rows) use ($rewriter) {
            foreach ($rows as $row) {
                DB::table('media')->where('id', $row->id)
                    ->update(['url' => $rewriter->rewriteUrl($row->url)]);
            }
        });

        DB::table('post_translations')->orderBy('id')->chunkById(200, function ($rows) use ($rewriter) {
            foreach ($rows as $row) {
                DB::table('post_translations')->where('id', $row->id)
                    ->update(['body' => $rewriter->rewriteBody($row->body)]);
            }
        });
    }

    private function countRows(): int
    {
        return collect((array) config('database_admin.tables'))
            ->sum(fn (string $table) => DB::table($table)->count());
    }
}
```

- [ ] **Step 5: Run the scoped test**

Run: `php artisan test --filter=DatabaseRestoreServiceTest`

Expected: PASS, 10 tests.

- [ ] **Step 6: Run the full suite and lint**

Run: `php artisan test` then `vendor/bin/pint`

- [ ] **Step 7: Commit**

```bash
git add app/Services/Database/DatabaseRestoreService.php app/Services/Database/InvalidBackupException.php tests/Feature/DatabaseRestoreServiceTest.php
git commit -m "feat: add a validating, transactional content restore service"
```

---

### Task 8: Restore route, gate and form

Exposes restore in the UI, gated so prod returns 404.

**Files:**
- Modify: `app/Http/Controllers/Admin/DatabaseController.php`
- Modify: `resources/views/admin/database/index.blade.php`
- Modify: `routes/web.php` (the admin group)
- Test: `tests/Feature/AdminDatabaseRestoreTest.php`

**Interfaces:**
- Consumes: `DatabaseRestoreService::restore()` (Task 7); `config('database_admin.restore_enabled')`, `config('database_admin.max_upload_kilobytes')` (Task 1).
- Produces: route `admin.database.restore`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminDatabaseRestoreTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDatabaseRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['database_admin.restore_enabled' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function seedPost(string $title): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'body' => '<p>Body</p>',
        ]);
    }

    /** Produces a real dump and hands it back as an upload. */
    private function dumpAsUpload(): UploadedFile
    {
        $name = app(DatabaseBackupService::class)->create('manual');
        $path = app(BackupRepository::class)->path($name);

        return new UploadedFile($path, $name, 'application/gzip', null, true);
    }

    public function test_the_restore_form_is_visible_when_enabled(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Copy prod content into dev');
    }

    public function test_the_restore_form_is_hidden_when_disabled(): void
    {
        config(['database_admin.restore_enabled' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertDontSee('Copy prod content into dev');
    }

    public function test_restore_returns_404_when_disabled(): void
    {
        config(['database_admin.restore_enabled' => false]);

        $this->actingAs($this->admin())
            ->post('/admin/database/restore', ['backup' => $this->dumpAsUpload()])
            ->assertNotFound();
    }

    public function test_an_admin_can_restore_an_uploaded_backup(): void
    {
        $this->seedPost('From Prod');
        $upload = $this->dumpAsUpload();

        PostTranslation::query()->delete();
        Post::query()->delete();
        $this->seedPost('Local Draft');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore', ['backup' => $upload])
            ->assertRedirect('/admin/database');

        $this->assertSame('From Prod', PostTranslation::first()->title);
    }

    public function test_restoring_reports_the_snapshot_filename(): void
    {
        $this->seedPost('From Prod');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore', ['backup' => $this->dumpAsUpload()])
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '-auto.sql.gz'));
    }

    public function test_a_non_gzip_upload_is_rejected_with_an_error(): void
    {
        $this->seedPost('Keep Me');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore', [
                'backup' => UploadedFile::fake()->createWithContent('junk.sql.gz', 'not gzip'),
            ])
            ->assertSessionHasErrors('backup');

        $this->assertSame('Keep Me', PostTranslation::first()->title);
    }

    public function test_a_missing_upload_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/database/restore', [])
            ->assertSessionHasErrors('backup');
    }

    public function test_guests_cannot_restore(): void
    {
        $this->post('/admin/database/restore', [])->assertRedirect('/admin/login');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AdminDatabaseRestoreTest`

Expected: FAIL — `POST /admin/database/restore` returns 404 because the route does not exist.

- [ ] **Step 3: Add the route**

In `routes/web.php`, after the `admin.database.destroy` route:

```php
    Route::post('database/restore', [DatabaseController::class, 'restore'])->name('admin.database.restore');
```

The route is always registered; the controller applies the gate. Conditional registration would bake the flag into the cached route table (`public/deploy.php` runs `route:cache`) and make behaviour depend on deploy ordering.

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/Admin/DatabaseController.php`, add the imports:

```php
use App\Services\Database\DatabaseRestoreService;
use App\Services\Database\InvalidBackupException;
use Illuminate\Http\Request;
```

Add `DatabaseRestoreService` to the constructor:

```php
    public function __construct(
        private BackupRepository $backups,
        private DatabaseBackupService $backupService,
        private DatabaseRestoreService $restoreService,
    ) {}
```

Add the action:

```php
    public function restore(Request $request)
    {
        // The gate. Prod's .env leaves DB_RESTORE_ENABLED unset, so this is a
        // 404 there and prod content can never be overwritten by this feature.
        abort_unless((bool) config('database_admin.restore_enabled'), 404);

        $request->validate([
            'backup' => ['required', 'file', 'max:'.(int) config('database_admin.max_upload_kilobytes')],
        ]);

        try {
            $result = $this->restoreService->restore($request->file('backup')->getRealPath());
        } catch (InvalidBackupException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return redirect()->route('admin.database.index')->with(
            'status',
            "Restored {$result['rows']} rows. Snapshot taken before the restore: {$result['snapshot']}",
        );
    }
```

- [ ] **Step 5: Add the form to the view**

Append inside the `container` div in `resources/views/admin/database/index.blade.php`, after the backups list:

```blade
        @if($restoreEnabled)
            <h2>Copy prod content into dev</h2>

            <p class="muted">
                Upload a backup taken on production. This replaces posts, translations,
                media records and site settings on this site. A snapshot of the current
                content is taken automatically first.
            </p>

            @error('backup')
                <p class="muted">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('admin.database.restore') }}" enctype="multipart/form-data"
                  onsubmit="return confirm('Replace this site\'s content with the uploaded backup?')">
                @csrf
                <input type="file" name="backup" accept=".gz" required>
                <button class="btn btn-primary" type="submit">Restore</button>
            </form>
        @endif
```

- [ ] **Step 6: Run the scoped test**

Run: `php artisan test --filter=AdminDatabaseRestoreTest`

Expected: PASS, 8 tests.

- [ ] **Step 7: Run the full suite and lint**

Run: `php artisan test` then `vendor/bin/pint`

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/DatabaseController.php resources/views/admin/database/index.blade.php routes/web.php tests/Feature/AdminDatabaseRestoreTest.php
git commit -m "feat: gate and expose prod-to-dev restore on the admin Database page"
```

---

### Task 9: Environment plumbing and documentation

Without this the flag is never set on dev, so the feature ships inert. Docs are part of the change per `CLAUDE.md`, not a follow-up.

**Files:**
- Modify: `.github/scripts/make-env.sh`
- Modify: `.env.example`
- Modify: `README.md`
- Modify: `docs/DEPLOY-CPANEL.md`
- Modify: `docs/superpowers/specs/2026-07-24-admin-database-page-design.md`
- Test: `tests/Feature/DeployEnvTemplateTest.php`

**Interfaces:**
- Consumes: the config keys from Task 1.
- Produces: `DB_RESTORE_ENABLED` and `MEDIA_FALLBACK_URL` in generated `.env` files.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DeployEnvTemplateTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployEnvTemplateTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(base_path('.github/scripts/make-env.sh'));
    }

    public function test_the_deploy_template_emits_the_restore_flag_defaulting_to_false(): void
    {
        $this->assertStringContainsString('DB_RESTORE_ENABLED=${DB_RESTORE_ENABLED:-false}', $this->script());
    }

    public function test_the_deploy_template_emits_the_media_fallback_url_defaulting_to_empty(): void
    {
        $this->assertStringContainsString('MEDIA_FALLBACK_URL="${MEDIA_FALLBACK_URL:-}"', $this->script());
    }

    public function test_the_example_env_documents_both_variables(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('DB_RESTORE_ENABLED=false', $example);
        $this->assertStringContainsString('MEDIA_FALLBACK_URL=', $example);
    }
}
```

This is a real regression guard, not ceremony: a missing default in `make-env.sh` combined with `set -u` would break every deploy, and a flag silently dropped from the template would leave dev unable to restore with no visible error.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DeployEnvTemplateTest`

Expected: FAIL — all three assertions, the strings are absent.

- [ ] **Step 3: Update `make-env.sh`**

In `.github/scripts/make-env.sh`, after the `DEPLOY_TOKEN` block (around line 48), add:

```
# Admin Database page. Restore is fail-closed: only the dev GitHub environment
# sets DB_RESTORE_ENABLED=true, so prod returns 404 for the restore route.
# MEDIA_FALLBACK_URL is prod's origin, so restored content on dev loads prod's
# images (media URLs are stored root-relative and the files stay on prod).
DB_RESTORE_ENABLED=${DB_RESTORE_ENABLED:-false}
MEDIA_FALLBACK_URL="${MEDIA_FALLBACK_URL:-}"
```

- [ ] **Step 4: Update `.env.example`**

After the `DB_*` block in `.env.example`:

```
# Admin Database page. Leave false everywhere except the dev subdomain.
DB_RESTORE_ENABLED=false
# Prod's origin, e.g. https://astrotherapia.com. Only set on dev.
MEDIA_FALLBACK_URL=
```

- [ ] **Step 5: Pass the environment variables through both deploy jobs**

In `.github/workflows/cicd.yml`, in the `Generate .env` step of **both** `deploy_dev` and `deploy_prd`, add to the `env:` block alongside the existing entries:

```yaml
          DB_RESTORE_ENABLED: ${{ vars.DB_RESTORE_ENABLED }}
          MEDIA_FALLBACK_URL: ${{ vars.MEDIA_FALLBACK_URL }}
```

Both are GitHub *variables*, not secrets — neither is sensitive, and variables are visible in the UI, which is what you want for a safety flag. Only the `dev` environment defines them; on `production` they resolve to empty and `make-env.sh` falls back to `false` and `""`.

- [ ] **Step 6: Update `README.md`**

Add the new routes to the routes table, and a short section covering: the `Database` admin page, `config/database_admin.php` and its keys, the `backups` filesystem disk and where backups live (`database/backups/`, retention 10, preserved across deploys), and that restore is dev-only via `DB_RESTORE_ENABLED`.

- [ ] **Step 7: Update `docs/DEPLOY-CPANEL.md`**

Add both variables to the GitHub environment table (around line 85, next to `APP_URL` and `DB_HOST`), marked as *variable* and dev-only. Add a short "Copying prod content to dev" section describing the workflow: back up on prod, download the `.sql.gz`, upload it on dev's Database page. Note two shared-host ceilings — `upload_max_filesize`/`post_max_size` limit the upload, and `max_execution_time` limits a large restore — with cPanel's phpMyAdmin as the fallback for an oversized dump, since the file is plain SQL.

- [ ] **Step 8: Reconcile the spec with the implementation**

In `docs/superpowers/specs/2026-07-24-admin-database-page-design.md`, update the routes table so the restore row reads "always registered; controller returns 404 when the flag is off" instead of "only when `restore_enabled`", and adjust the "Restore gating" section to match. Add `max_upload_kilobytes` to the config example. Reason: route caching on the server, as recorded in this plan's header.

- [ ] **Step 9: Run the scoped test**

Run: `php artisan test --filter=DeployEnvTemplateTest`

Expected: PASS, 3 tests.

- [ ] **Step 10: Run the full suite and lint**

Run: `php artisan test` — expected: all green, including every test added in Tasks 1-8.
Run: `vendor/bin/pint` — expected: no errors.

- [ ] **Step 11: Commit**

```bash
git add .github/scripts/make-env.sh .github/workflows/cicd.yml .env.example README.md docs/DEPLOY-CPANEL.md docs/superpowers/specs/2026-07-24-admin-database-page-design.md tests/Feature/DeployEnvTemplateTest.php
git commit -m "feat: plumb DB_RESTORE_ENABLED and MEDIA_FALLBACK_URL through deploys, update docs"
```

---

## Post-implementation: manual verification

The automated suite runs on SQLite; the real path is MySQL. These steps are not optional — they cover exactly what CI cannot.

- [ ] Set `DB_RESTORE_ENABLED=true` and `MEDIA_FALLBACK_URL=https://<prod-domain>` as variables on the **dev** GitHub environment only. Leave the `production` environment without them.
- [ ] Push to `master` and let the pipeline deploy dev.
- [ ] On **prod**, open `/admin/database` and confirm the page renders, the backups list works, and **no restore form is present**.
- [ ] On prod, `POST /admin/database/restore` directly (e.g. `curl -X POST`) and confirm **404**.
- [ ] On prod, click *Back up now*, then download the resulting `.sql.gz`.
- [ ] Open the downloaded file and confirm it is valid MySQL: one statement per line, `DELETE FROM \`post_translations\`;` appearing before `DELETE FROM \`posts\`;`, and no `users`/`sessions`/`cache`/`jobs` statements anywhere.
- [ ] On **dev**, upload that file on `/admin/database`, confirm the restore succeeds and the status message names the auto snapshot.
- [ ] Browse dev's journal and confirm posts match prod **and images render** (they are being served from prod's origin — check the `src` in devtools).
- [ ] Confirm dev's admin login still works: `users` was never in scope, so your dev account must be untouched.
- [ ] Download the auto snapshot from dev and confirm it contains the pre-restore content.

## Things intentionally not built

Listed so a later reader does not mistake them for oversights: restoring into local SQLite, transferring media files themselves, copying `users`/sessions/cache/queue tables, scheduled backups, and any dev-to-prod direction.
