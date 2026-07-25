# Copy prod → dev, one button — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add one admin button that dumps prod's live content DB and restores it into dev, no manual download/upload.

**Architecture:** Reuse the existing pure-PDO dumper and the existing restore pipeline. Point the dumper at a second Laravel connection (`source`) wired to prod's MySQL (same cPanel account, same server). A new gated controller action dumps `source` to a temp file, feeds it to `DatabaseRestoreService::restore()` (which snapshots dev, validates, replays, rewrites media URLs), then deletes the temp file.

**Tech Stack:** Laravel 11, PHPUnit, SQLite (tests) / MySQL (servers), gzip over PDO.

**Spec:** [docs/superpowers/specs/2026-07-25-copy-prod-to-dev-one-button-design.md](../specs/2026-07-25-copy-prod-to-dev-one-button-design.md)

## Global Constraints

- TDD, red first. Scope each test with `php artisan test --filter=`; run the **full** `php artisan test` before every commit (cross-cutting state — see CLAUDE.md).
- PHP toolchain: use the local PATH prefix / sqlite setup from memory `local-php-toolchain`.
- Feature is **dev-only**, fail-closed: gated on `config('database_admin.restore_enabled')` (= `DB_RESTORE_ENABLED`, unset on prod → 404). Never emit `PROD_DB_*` on the prod deploy.
- Content tables only: `posts`, `post_translations`, `media`, `site_settings`. Prod is read-only (SELECT).
- Docs must ship in the same change (CLAUDE.md): README, `.env.example`, `.github/scripts/make-env.sh`.
- The prod dump is throwaway: temp file, deleted in `finally`, never added to any backup list.

---

### Task 1: `DatabaseBackupService::dumpTo()` — connection-targeted dump

Extract the dump body so it targets an arbitrary path and DB connection. `create()` becomes a thin wrapper — no behavior change for existing callers.

**Files:**
- Modify: `app/Services/Database/DatabaseBackupService.php`
- Test: `tests/Unit/DatabaseBackupTest.php` (add cases; existing cases must stay green)

**Interfaces:**
- Produces: `DatabaseBackupService::dumpTo(string $absolutePath, ?string $connection = null): void` — gzips a dump of `$connection` (default connection when null) to `$absolutePath`.
- Produces (unchanged signature): `create(string $origin = 'manual'): string`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/DatabaseBackupTest.php`. It registers a second sqlite connection as `source`, builds the content tables on it, seeds a row, dumps it via `dumpTo`, and asserts the dump reflects the *source* connection's data.

```php
public function test_dump_to_targets_a_named_connection_and_path(): void
{
    // A separate sqlite file acts as the stand-in "prod" source.
    $sourceDb = tempnam(sys_get_temp_dir(), 'srcdb_');
    config(['database.connections.source' => [
        'driver' => 'sqlite',
        'database' => $sourceDb,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
    Artisan::call('migrate', ['--database' => 'source', '--force' => true]);

    Post::on('source')->create(['status' => 'published']);
    PostTranslation::on('source')->create([
        'post_id' => 1, 'locale' => 'en',
        'title' => 'Source Row', 'slug' => 'source-row', 'body' => '<p>x</p>',
    ]);

    $out = tempnam(sys_get_temp_dir(), 'dump_');
    app(DatabaseBackupService::class)->dumpTo($out, 'source');
    $sql = (string) gzdecode(file_get_contents($out));

    @unlink($out);
    @unlink($sourceDb);

    $this->assertStringContainsString('INSERT INTO `posts`', $sql);
    $this->assertStringContainsString('Source Row', $sql);
    $this->assertStringContainsString('DELETE FROM `post_translations`;', $sql);
}
```

Add the imports at the top of the file: `use App\Models\Post;` and `use App\Models\PostTranslation;` are already present; add `use Illuminate\Support\Facades\Artisan;`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_dump_to_targets_a_named_connection_and_path`
Expected: FAIL — `Call to undefined method ...::dumpTo()`.

- [ ] **Step 3: Write minimal implementation**

In `app/Services/Database/DatabaseBackupService.php`, refactor. Replace `create()` and `writeDump()`/`writeTable()` so the connection threads through:

```php
/**
 * @param  'manual'|'auto'  $origin
 * @return string the backup filename
 */
public function create(string $origin = 'manual'): string
{
    Storage::disk('backups')->makeDirectory(BackupRepository::DIRECTORY);

    $name = $this->backups->filename($origin);
    $this->dumpTo($this->backups->path($name));

    return $name;
}

/** Gzip a content dump of $connection (default when null) to $path. */
public function dumpTo(string $path, ?string $connection = null): void
{
    $handle = gzopen($path, 'wb6');

    if ($handle === false) {
        throw new RuntimeException('Could not open the backup file for writing.');
    }

    try {
        $this->writeDump($handle, $connection);
    } finally {
        gzclose($handle);
    }
}

/** @param  resource  $handle */
private function writeDump($handle, ?string $connection): void
{
    $db = DB::connection($connection);
    $tables = (array) config('database_admin.tables');
    $quoter = QuoterFactory::for($db->getDriverName());

    $this->line($handle, '-- Content backup');
    $this->line($handle, '-- source: '.config('app.url'));
    $this->line($handle, '-- created: '.now()->toIso8601String());
    $this->line($handle, '-- tables: '.implode(', ', $tables));

    foreach (array_reverse($tables) as $table) {
        $this->line($handle, 'DELETE FROM `'.$table.'`;');
    }

    foreach ($tables as $table) {
        $this->writeTable($handle, $db, $table, $quoter);
    }
}

/** @param  resource  $handle */
private function writeTable($handle, $db, string $table, SqlQuoter $quoter): void
{
    $columns = Schema::connection($db->getName())->getColumnListing($table);

    if ($columns === []) {
        return;
    }

    $columnList = implode(', ', array_map(fn (string $c) => '`'.$c.'`', $columns));

    $db->table($table)->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($handle, $table, $columns, $columnList, $quoter) {
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
```

Note: `$db->getName()` returns the connection name so `Schema::connection(...)` reads the same connection's columns.

- [ ] **Step 4: Run the new test and the full suite**

Run: `php artisan test --filter=DatabaseBackupTest`
Expected: PASS (new case + all 5 existing cases — `create()` behavior unchanged).
Then: `php artisan test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Database/DatabaseBackupService.php tests/Unit/DatabaseBackupTest.php
git commit -m "feat: add DatabaseBackupService::dumpTo for connection-targeted dumps"
```

---

### Task 2: `source` connection config + `.env.example`

Add the static `source` connection so production wiring reads `PROD_DB_*`. Tests override it at runtime, so this task's own test just asserts the entry resolves.

**Files:**
- Modify: `config/database.php` (add `source` to `connections`)
- Modify: `.env.example`
- Test: `tests/Unit/SourceConnectionConfigTest.php` (create)

**Interfaces:**
- Produces: connection `source` with `database` = `env('PROD_DB_DATABASE')` (null when unset → controller guards on it in Task 3).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SourceConnectionConfigTest.php`:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class SourceConnectionConfigTest extends TestCase
{
    public function test_the_source_connection_is_defined_and_reads_prod_env(): void
    {
        $conn = config('database.connections.source');

        $this->assertIsArray($conn);
        $this->assertSame('mysql', $conn['driver']);
        // database is null until PROD_DB_DATABASE is set (dev only).
        $this->assertArrayHasKey('database', $conn);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SourceConnectionConfigTest`
Expected: FAIL — `assertIsArray` fails (config is null).

- [ ] **Step 3: Add the connection**

In `config/database.php`, inside `'connections' => [ ... ]`, after the `mysql` entry add:

```php
/*
 * Read-only view of the PROD database for the "Copy prod → dev" button.
 * Populated from PROD_DB_* on the dev deploy only; unset elsewhere, so
 * `database` is null and DatabaseController::pull() refuses to run.
 * Same cPanel account as prod, so this reaches prod's MySQL directly.
 */
'source' => [
    'driver' => 'mysql',
    'host' => env('PROD_DB_HOST', '127.0.0.1'),
    'port' => env('PROD_DB_PORT', '3306'),
    'database' => env('PROD_DB_DATABASE'),
    'username' => env('PROD_DB_USERNAME'),
    'password' => env('PROD_DB_PASSWORD'),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],
```

In `.env.example`, under the `MEDIA_FALLBACK_URL=` line, add:

```
# Copy prod -> dev button. Read-only prod DB credentials, from prod's .env.
# Only set on dev (same cPanel account as prod). Unset here = button hidden.
# PROD_DB_HOST=127.0.0.1
# PROD_DB_PORT=3306
# PROD_DB_DATABASE=
# PROD_DB_USERNAME=
# PROD_DB_PASSWORD=
```

- [ ] **Step 4: Run test + full suite**

Run: `php artisan test --filter=SourceConnectionConfigTest`
Expected: PASS.
Then: `php artisan test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/database.php .env.example tests/Unit/SourceConnectionConfigTest.php
git commit -m "feat: add read-only prod source DB connection for prod->dev copy"
```

---

### Task 3: `pull` route + controller action

The button's endpoint: gate, dump `source` to temp, restore into dev, unlink temp.

**Files:**
- Modify: `routes/web.php` (add route inside the `admin` group)
- Modify: `app/Http/Controllers/Admin/DatabaseController.php` (add `pull()`)
- Test: `tests/Feature/AdminDatabasePullTest.php` (create)

**Interfaces:**
- Consumes: `DatabaseBackupService::dumpTo(string, ?string)` (Task 1); `DatabaseRestoreService::restore(string): array{snapshot,rows}` (existing); `source` connection (Task 2).
- Produces: route `POST admin/database/pull` name `admin.database.pull` → `DatabaseController::pull()`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AdminDatabasePullTest.php`. The helper `configureSource()` wires a separate sqlite file as `source` and migrates the content tables onto it.

```php
<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDatabasePullTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sourceDb = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        config(['database_admin.restore_enabled' => true]);
        config(['database_admin.media_fallback_url' => 'https://astrotherapia.com']);
    }

    protected function tearDown(): void
    {
        if ($this->sourceDb) {
            @unlink($this->sourceDb);
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** Wire a separate sqlite DB as the `source` connection and build tables on it. */
    private function configureSource(): void
    {
        $this->sourceDb = tempnam(sys_get_temp_dir(), 'srcdb_');
        config(['database.connections.source' => [
            'driver' => 'sqlite',
            'database' => $this->sourceDb,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        Artisan::call('migrate', ['--database' => 'source', '--force' => true]);
    }

    public function test_pull_copies_source_content_into_this_database(): void
    {
        $this->configureSource();
        $post = Post::on('source')->create(['status' => 'published']);
        PostTranslation::on('source')->create([
            'post_id' => $post->id, 'locale' => 'en',
            'title' => 'Prod Title', 'slug' => 'prod-title', 'body' => '<p>Body</p>',
        ]);

        // Local dev starts with different content.
        $local = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $local->id, 'locale' => 'en',
            'title' => 'Dev Draft', 'slug' => 'dev-draft', 'body' => '<p>Draft</p>',
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertRedirect('/admin/database');

        $this->assertSame('Prod Title', PostTranslation::first()->title);
        $this->assertSame(1, PostTranslation::count());
    }

    public function test_pull_takes_a_pre_restore_snapshot_of_dev(): void
    {
        $this->configureSource();

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertSessionHas('status', fn (string $s) => str_contains($s, '-auto.sql.gz'));
    }

    public function test_pull_leaves_no_temp_dump_behind(): void
    {
        $this->configureSource();
        $before = glob(sys_get_temp_dir().'/proddump_*');

        $this->actingAs($this->admin())->post('/admin/database/pull');

        $after = glob(sys_get_temp_dir().'/proddump_*');
        $this->assertSame($before, $after);
    }

    public function test_pull_returns_404_when_restore_disabled(): void
    {
        config(['database_admin.restore_enabled' => false]);

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertNotFound();
    }

    public function test_pull_errors_when_source_is_not_configured(): void
    {
        config(['database.connections.source.database' => null]);
        $this->seedLocal();

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertRedirect()
            ->assertSessionHasErrors('pull');

        // Dev content untouched.
        $this->assertSame('Dev Draft', PostTranslation::first()->title);
    }

    public function test_guests_cannot_pull(): void
    {
        $this->post('/admin/database/pull')->assertRedirect('/admin/login');
    }

    private function seedLocal(): void
    {
        $local = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $local->id, 'locale' => 'en',
            'title' => 'Dev Draft', 'slug' => 'dev-draft', 'body' => '<p>Draft</p>',
        ]);
    }
}
```

(If `App\Models\Media` is unused after you finalize, drop the import — it's listed for parity with the content tables.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AdminDatabasePullTest`
Expected: FAIL — 404 / route `admin.database.pull` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `admin` middleware group, after the `restore` route:

```php
Route::post('database/pull', [DatabaseController::class, 'pull'])->name('admin.database.pull');
```

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/Admin/DatabaseController.php`, add:

```php
/**
 * One-click prod -> dev copy. Dumps the live prod database (the read-only
 * `source` connection) to a throwaway temp file, then runs it through the
 * same restore pipeline the upload form uses: dev is snapshotted first,
 * statements are allow-listed, media URLs are rewritten to prod's origin.
 */
public function pull()
{
    abort_unless((bool) config('database_admin.restore_enabled'), 404);

    if (! config('database.connections.source.database')) {
        return back()->withErrors(['pull' => 'The prod source database is not configured.']);
    }

    // tempnam() creates the file; use its path as-is (restore checks gzip
    // magic bytes, not the extension) so nothing is left behind.
    $temp = tempnam(sys_get_temp_dir(), 'proddump_');

    try {
        $this->backupService->dumpTo($temp, 'source');
        $result = $this->restoreService->restore($temp);
    } catch (InvalidBackupException $e) {
        return back()->withErrors(['pull' => $e->getMessage()]);
    } finally {
        @unlink($temp);
    }

    return redirect()->route('admin.database.index')->with(
        'status',
        "Copied prod → dev: {$result['rows']} rows. Dev snapshot before overwrite: {$result['snapshot']}",
    );
}
```

`InvalidBackupException` is already imported in this controller.

- [ ] **Step 5: Run tests + full suite**

Run: `php artisan test --filter=AdminDatabasePullTest`
Expected: PASS (all 6 cases).
Then: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/DatabaseController.php tests/Feature/AdminDatabasePullTest.php
git commit -m "feat: add gated one-click prod->dev copy endpoint"
```

---

### Task 4: View — Copy prod → dev button

Add the button, shown only when restore is enabled **and** `source` is configured. Distinct label from the existing upload block.

**Files:**
- Modify: `resources/views/admin/database/index.blade.php`
- Modify: `app/Http/Controllers/Admin/DatabaseController.php` (pass `sourceConfigured` to `index`)
- Test: `tests/Feature/AdminDatabasePullTest.php` (add view cases)

**Interfaces:**
- Consumes: route `admin.database.pull` (Task 3).
- Produces: `index` view var `sourceConfigured` (bool).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/AdminDatabasePullTest.php`:

```php
public function test_the_pull_button_shows_when_enabled_and_source_configured(): void
{
    $this->configureSource();

    $this->actingAs($this->admin())
        ->get('/admin/database')
        ->assertOk()
        ->assertSee('Copy live prod into this site');
}

public function test_the_pull_button_is_hidden_when_source_missing(): void
{
    config(['database.connections.source.database' => null]);

    $this->actingAs($this->admin())
        ->get('/admin/database')
        ->assertOk()
        ->assertDontSee('Copy live prod into this site');
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=test_the_pull_button`
Expected: FAIL — text not found.

- [ ] **Step 3: Pass the flag from the controller**

In `DatabaseController::index()`, add the var:

```php
public function index()
{
    return view('admin.database.index', [
        'backups' => $this->backups->all(),
        'restoreEnabled' => (bool) config('database_admin.restore_enabled'),
        'sourceConfigured' => (bool) config('database.connections.source.database'),
    ]);
}
```

- [ ] **Step 4: Add the button to the view**

In `resources/views/admin/database/index.blade.php`, inside the existing `@if($restoreEnabled)` block (before its closing `@endif`), after the upload `</form>`, add:

```blade
@if($sourceConfigured)
    <h2>Copy live prod into this site</h2>

    <p class="muted">
        Pulls the current production content and replaces posts, translations,
        media records and site settings on this site. A snapshot of the current
        content is taken automatically first.
    </p>

    @error('pull')
        <p class="muted">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('admin.database.pull') }}"
          onsubmit="return confirm('Replace this site\'s content with live production content?')">
        @csrf
        <button class="btn btn-primary" type="submit">Copy prod → dev now</button>
    </form>
@endif
```

- [ ] **Step 5: Run tests + full suite**

Run: `php artisan test --filter=AdminDatabasePullTest`
Expected: PASS (all 8 cases).
Then: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/database/index.blade.php app/Http/Controllers/Admin/DatabaseController.php tests/Feature/AdminDatabasePullTest.php
git commit -m "feat: add Copy prod -> dev button to admin Database page"
```

---

### Task 5: Docs + deploy plumbing

Sync README and the deploy env script (CLAUDE.md rule). Docs-only — no test; run the full suite once to confirm nothing regressed.

**Files:**
- Modify: `README.md`
- Modify: `.github/scripts/make-env.sh`

- [ ] **Step 1: `.github/scripts/make-env.sh`**

After the existing `MEDIA_FALLBACK_URL="${MEDIA_FALLBACK_URL:-}"` line, append the prod-source vars (emitted only when the GitHub Environment provides them — the dev environment):

```sh
# Copy prod -> dev button (dev environment only). Read-only prod DB creds so
# dev can dump live prod content. Absent on prod => the button 404s/hides.
PROD_DB_HOST="${PROD_DB_HOST:-127.0.0.1}"
PROD_DB_PORT="${PROD_DB_PORT:-3306}"
PROD_DB_DATABASE="${PROD_DB_DATABASE:-}"
PROD_DB_USERNAME="${PROD_DB_USERNAME:-}"
PROD_DB_PASSWORD="${PROD_DB_PASSWORD:-}"
```

- [ ] **Step 2: README.md**

Find the admin Database / restore section and add: the `POST admin/database/pull` route (`admin.database.pull`); the `source` connection and `PROD_DB_*` env vars; a one-line note that the button is dev-only (gated by `DB_RESTORE_ENABLED`) and reads prod directly because dev and prod share one cPanel account. If a routes table or env-var table exists, add rows there to match the existing format.

- [ ] **Step 3: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add README.md .github/scripts/make-env.sh
git commit -m "docs: document one-click prod->dev copy route, env, and deploy plumbing"
```

---

## Self-Review

**Spec coverage:**
- `source` connection → Task 2. `dumpTo` → Task 1. `pull` action + gates → Task 3. Throwaway temp + `finally` unlink → Task 3. Dev pre-restore snapshot + media rewrite → reused, asserted in Task 3. View button (gated on restore + source) → Task 4. Docs/env/make-env → Task 5. All spec sections mapped.

**Placeholder scan:** No TBD/TODO; every code step is concrete.

**Type consistency:** `dumpTo(string, ?string): void` defined in Task 1, consumed identically in Task 3. `restore(): array{snapshot,rows}` matches existing service. View var `sourceConfigured` defined in Task 4 controller change and consumed in the same task's Blade. Route name `admin.database.pull` consistent across Tasks 3–5.

**Test caveat:** `source` is real MySQL in production; tests substitute a second SQLite connection, so cross-dialect issues stay uncatchable in CI — same limitation as the existing backup/restore tests.
