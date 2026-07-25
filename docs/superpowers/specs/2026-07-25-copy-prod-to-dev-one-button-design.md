# Copy prod → dev, one button — Design

Date: 2026-07-25

## Goal

Replace the manual prod → dev handoff with a single button on the admin
Database page. One press:

1. Dumps prod's **live** content database to a throwaway snapshot.
2. Restores that snapshot into dev, overwriting dev content.

No download, no re-upload. The existing upload-restore path stays as a manual
fallback; this only adds the one-click path.

## Revision of a prior assumption

The earlier design
([2026-07-24-admin-database-page-design.md](2026-07-24-admin-database-page-design.md))
stated: *"Dev and prod are separate cPanel apps with separate databases … Dev
holds no credentials for the prod database."* That was the reason it rejected
automation and settled on a manual file handoff.

Direct inspection of the host's File Manager disproves it. Both apps live under
**one** cPanel account:

```
/home/martinis/
  astrotherapia.com/       # prod  (full Laravel tree)
  astrotherapia_dev/       # dev   (full Laravel tree)
```

Same account, same shared filesystem, same MySQL server. Dev can read prod's
`.env` at `/home/martinis/astrotherapia.com/.env` and can open a PDO connection
to prod's database. This spec builds on that corrected topology; it supersedes
the transfer-mechanism decision of the prior spec for the automated path only.

## Context and constraints

These hold from the prior deployment and still bind the design.

| Constraint | Source | Consequence |
| --- | --- | --- |
| Host is shell-less cPanel; `exec()` disabled | `.github/scripts/make-env.sh:45` | No `mysqldump`. Dump stays pure PHP over PDO. Reuse `DatabaseBackupService`. |
| Same `app.zip` promoted dev → prod; `APP_ENV=production` on both | `.github/workflows/cicd.yml`, `.github/scripts/make-env.sh:14` | Code cannot tell dev from prod. Only `.env` can. The feature must be `.env`-gated, fail-closed. |
| Media URLs stored root-relative | `app/Http/Controllers/Admin/AttachmentController.php:38-41` | Prod media copied to dev must be rewritten to prod's origin (`MEDIA_FALLBACK_URL`). Already handled by restore. |
| `post_translations.post_id` cascades on delete | `database/migrations/2026_06_26_000003_create_post_translations_table.php:16` | Delete/insert ordering matters. Already handled by the dumper (children-first delete, parents-first insert). |
| Tests run in-memory SQLite; servers run MySQL | `phpunit.xml`, `.env.example` | Dialect bugs not catchable in CI. Tests use a second SQLite connection as the stand-in `source`. |

New topology facts this spec relies on:

| Fact | Source | Consequence |
| --- | --- | --- |
| Prod + dev share one cPanel account and MySQL server | Host File Manager (`/home/martinis`) | Dev can open a read-only PDO connection to prod's schema. |
| Prod's `.env` is readable from dev's path | Same filesystem | `PROD_DB_*` values are available to copy into dev's `.env`. |

## Decisions

### Credential source: a second Laravel connection `source`

A standard `mysql` connection named `source` in `config/database.php`, populated
from **dev-only** env vars `PROD_DB_HOST`, `PROD_DB_PORT`, `PROD_DB_DATABASE`,
`PROD_DB_USERNAME`, `PROD_DB_PASSWORD`. Values are copied from prod's `.env`.

Chosen over:

- **Reading prod's `.env` at runtime** (`PROD_ENV_PATH` + parse) — single source
  of truth, no duplicated secret, but `.env` parsing is non-idiomatic and harder
  to test.
- **`GRANT SELECT` to dev's MySQL user on prod schema** — no prod password
  anywhere, but needs a manual cPanel/phpMyAdmin step out of band.

Tradeoff accepted: prod DB credentials are duplicated into dev's `.env`. That
`.env` is local to the dev app on an account that already owns the prod
database, so the marginal exposure is low. Prod is only ever **read** (SELECT).

### Fresh snapshot is throwaway

The prod dump is written to a temp path, restored, then deleted in a `finally`.
It never enters prod's or dev's backup list. Rationale: the button's contract is
"dev ends up with current prod data"; a retained prod file is a separate concern
and was declined.

Dev's own safety net is unchanged: `DatabaseRestoreService` takes an **auto
pre-restore snapshot of dev** before overwriting, so a bad pull is recoverable.

### Gate: identical to the existing restore

`abort_unless(config('database_admin.restore_enabled'), 404)`. `restore_enabled`
is `DB_RESTORE_ENABLED`, unset on prod → the route 404s there. Prod can never
pull. A second, softer guard: if `PROD_DB_DATABASE` is unset, redirect back with
an error rather than 404 (misconfiguration, not a security boundary).

## Components

### 1. `config/database.php` — `source` connection

New entry mirroring `mysql`, keyed on `PROD_DB_*`:

```php
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
    'strict' => true,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],
```

Absent `PROD_DB_*`, `database` is null and the connection is never used — the
controller guards on `PROD_DB_DATABASE` before touching it.

### 2. `DatabaseBackupService::dumpTo(string $path, ?string $connection = null)`

Extract the gzip-dump body so it targets an arbitrary path and connection:

- `dumpTo($absolutePath, $connection)` — gzopen `$path`, run the dump reading
  from `DB::connection($connection)`; `Schema::connection($connection)` for
  column listing.
- `create($origin)` becomes a thin wrapper: resolve name/path via
  `BackupRepository`, call `dumpTo($path)` (default connection). **No behavior
  change** to `create()` or its callers.

Connection threads into `writeTable` (`DB::connection($c)->table(...)`) and
`writeDump` (driver name from the target connection for the quoter).

### 3. `DatabaseController::pull()` + route

`POST admin/database/pull`, name `admin.database.pull`:

```php
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

Reuses the full restore pipeline: allow-list validation, dev pre-restore
snapshot, transactional replay, media-URL rewrite.

### 4. View — `admin.database.index`

A **Copy prod → dev** button in its own form (POST `admin.database.pull`),
rendered only when `restore_enabled && config('database.connections.source.database')`.
`onsubmit` JS confirm: *"This overwrites all dev content with prod. Continue?"*
Displays the `pull` error bag if present.

## Data flow

```
[button] POST /admin/database/pull
  gate: restore_enabled (404 on prod) ; source configured (else back+error)
  DatabaseBackupService::dumpTo(temp, 'source')   -- SELECT prod, gzip -> temp
  DatabaseRestoreService::restore(temp)
      assert gzip + statements allow-listed
      snapshot dev  (create 'auto')                -- recovery point
      transaction: replay DELETE/INSERT ; rewrite media URLs -> MEDIA_FALLBACK_URL
  finally: unlink(temp)
  redirect with status
```

## Error handling

- Prod (route disabled): 404, before any work.
- `source` unconfigured: redirect back, error bag, no temp file created.
- Prod unreachable / PDO error during `dumpTo`: exception bubbles; `finally`
  removes the temp file; dev is untouched (nothing restored yet).
- Malformed dump (should not happen from our own dumper):
  `InvalidBackupException` → back with error; dev untouched.
- Restore failure mid-transaction: rolled back; dev pre-restore snapshot exists.

## Testing (TDD, red first)

Feature (`tests/Feature`):

1. **Happy path** — register a second SQLite connection as `source`, seed it
   with prod-like rows across all four content tables, POST `pull` as admin with
   `restore_enabled=true` and `source` configured. Assert: dev tables now equal
   `source` rows; an `auto` pre-restore snapshot exists; media URLs rewritten to
   `MEDIA_FALLBACK_URL`; the temp file no longer exists.
2. **Gate** — `restore_enabled=false` → 404.
3. **Unconfigured source** — `source.database` null → redirect back with the
   `pull` error, dev unchanged.

Unit (`tests/Unit`):

4. **`dumpTo`** against a given connection writes a gzip whose statements are the
   expected `DELETE`/`INSERT` on the four tables (proves connection targeting and
   that `create()` still works via the wrapper).

Note in the suite: `source` is real MySQL in production; tests stand in a second
SQLite connection, so cross-dialect issues remain uncatchable in CI (same caveat
as the existing backup/restore tests).

## Documentation upkeep (same change)

- **README.md** — new `admin.database.pull` route; the `source` connection and
  `PROD_DB_*` env vars; note the feature is dev-only via `DB_RESTORE_ENABLED`.
- **`.github/scripts/make-env.sh`** — emit `PROD_DB_*` for the dev deploy only
  (never prod), alongside the existing `DB_RESTORE_ENABLED` / `MEDIA_FALLBACK_URL`
  plumbing.
- **`.env.example`** — document `PROD_DB_*`.
- This spec is the design record; the prior admin-database spec is left intact
  and cross-referenced from its Revision section above.

No theme, token, or `AUTHORING.md` surface changes.

## Out of scope

- Retaining the prod dump as a backup on prod or dev (declined: throwaway).
- Local-SQLite as a pull target.
- A prod-side export endpoint or shared secret (not needed on one account).
- Pulling `users`/sessions/cache/jobs (content tables only, unchanged).
