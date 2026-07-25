# Admin Database Page — Design

Date: 2026-07-24

## Goal

Add a `Database` link to the admin dashboard leading to a page with two
operations:

1. **Backup** — dump the site's content tables to a downloadable archive.
2. **Copy prod → dev** — load a prod backup into the dev subdomain so dev
   mirrors live content.

## Context and constraints

These are properties of the existing deployment, not choices made here. They
drive most of the design.

| Constraint | Source | Consequence |
| --- | --- | --- |
| Host is shell-less cPanel; `exec()` disabled | `.github/scripts/make-env.sh:45`, `docs/DEPLOY-CPANEL.md` | `mysqldump` is unavailable. The dumper must be pure PHP over PDO. `spatie/laravel-backup` shells out and cannot be used. |
| Dev and prod are separate cPanel apps with separate databases | `docs/DEPLOY-CPANEL.md:35-46` | Dev holds no credentials for the prod database and gains none here. |
| The same `app.zip` is promoted from dev to prod | `.github/workflows/cicd.yml` (build → deploy_dev → deploy_prd) | Both boxes run identical code. Behaviour can only differ via `.env`. |
| `APP_ENV=production` is hardcoded for both deploys | `.github/scripts/make-env.sh:14` | `app()->environment()` cannot distinguish dev from prod. A dedicated flag is required. |
| Media URLs are stored root-relative on purpose | `app/Http/Controllers/Admin/AttachmentController.php:38-41` | Prod content copied to dev references `/storage/media/...`, which dev resolves against its own docroot — the files are not there. |
| `post_translations.post_id` cascades on delete | `database/migrations/2026_06_26_000003_create_post_translations_table.php:16` | Deleting a `posts` row removes its translations. Restore must order deletes and inserts accordingly. |
| Tests run in-memory SQLite; servers run MySQL | `phpunit.xml`, `.env.example:26` | Dialect-level bugs are not catchable in CI. Stated explicitly under Testing. |

## Decisions

Each was chosen over the alternatives listed.

### Transfer mechanism: manual file handoff

Prod produces a backup file. The operator downloads it and uploads it on dev.

Rejected: a token-guarded export endpoint on prod (adds a public endpoint and a
shared secret), and granting dev's MySQL user read access to the prod database
(puts prod credentials on the lower-trust box).

The tradeoff accepted: because the app never sees where an uploaded file came
from, the prod → dev direction is a convention rather than something the code
derives. The restore flag below is what enforces it.

### Scope: content tables only

`posts`, `post_translations`, `media`, `site_settings`.

Excluded: `users`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`,
`failed_jobs`. Prod password hashes never reach dev, a restore never logs
anyone out, and the blast radius of any mistake is content only.

`site_settings` is included deliberately. A sync therefore overwrites dev's
active theme and section-visibility toggles with prod's. This is intended: dev
is meant to mirror prod.

### Target: the dev subdomain only

Local SQLite development is out of scope. Both source and target are MySQL, so
no cross-dialect portability is required.

### Restore gating: fail-closed env flag

`DB_RESTORE_ENABLED`, surfaced as `config('database_admin.restore_enabled')`.
The restore route is always registered; the controller calls
`abort_unless(config('database_admin.restore_enabled'), 404)` and the view
renders the upload form only when the flag is true. Prod's `.env` leaves it
false, so prod returns 404 for the restore route and shows no form. A missing
value means disabled.

The route is registered unconditionally, rather than only when the flag is
true, because `public/deploy.php` runs `route:cache`: conditional registration
would bake the flag into the cached route table and make behaviour depend on
deploy ordering. Gating in the controller gives the identical outcome and stays
testable.

This is the mechanism that makes "prod is never overwritten" true in code.
Enabling restore on prod would require a deliberate GitHub environment edit.

### Pre-restore snapshot

Before overwriting, dev dumps its own four tables through the same backup
service. Reuses code that exists anyway and provides a rollback file.

### Media: rewrite paths on import

During import, `"/storage/media/` becomes `"https://<prod-host>/storage/media/`
in `media.url` and in `post_translations.body`. Dev then loads images from
prod. No file transfer, and images render.

Matching on the opening quote plus the `media/` prefix keeps the replacement
precise; all uploads land under `media/`
(`AttachmentController.php:34`).

Rejected: accepting broken images (dev cannot be visually checked), shipping a
second archive of `storage/app/public/media/` (large, and shared-host upload
limits bite), and a serve-time 404 fallback route (more moving parts, and it
must shadow static file serving).

Accepted tradeoff: dev's rows no longer match prod byte-for-byte, and a prod
domain is baked into dev data. Only ever on dev.

### Backup storage: server-side, listed, pruned

Manual backups and automatic pre-restore snapshots both write to
`database/backups/` and appear in one table on the page, labelled by origin.
Retention keeps the most recent 10 and deletes older files.

The files sit on a dedicated private filesystem disk, `backups`, rooted at the
project's `database/` directory; every access is scoped to the `backups/`
subfolder, so the disk never touches migrations or the dev sqlite file. The disk
declares no `serve`/`url`, so the directory is never web-reachable — downloads
go through the controller.

This location survives deploys. `public/extract.php` unzips the CI archive with
`extractTo()`, which overlays files and never wipes the tree, and
`make-archive.php` never contains a `database/backups/` (it is created at
runtime, not held in the repo). A deploy therefore writes the archive on top and
leaves existing backups untouched — the same guarantee `storage/` gets. Because
`database/` (unlike `storage/`) does ship in the archive, two small chores
follow: `public/deploy.php` creates `database/backups` in its ensure-loop (the
FTP sync skips empty dirs), and `.gitignore` excludes `/database/backups` so
dump files are never committed.

Rejected: streaming manual backups straight to the browser without persisting,
which would mean two separate mechanisms and no visible history on prod. Also
rejected: `storage/app/private/backups/` — it works and is idiomatic, but the
operator asked for the backups to live under `database/`, and the disk scoping
above keeps that safe.

### No typed confirmation

A plain confirm dialog on the restore form. Because restore snapshots first, a
wrong-file restore is recoverable, so a type-the-database-name step would add
friction without covering a remaining unrecoverable case.

## Architecture

### Routes

Added inside the existing `admin` middleware group in `routes/web.php:16`.

| Method and URI | Name | Registered |
| --- | --- | --- |
| `GET admin/database` | `admin.database.index` | always |
| `POST admin/database/backup` | `admin.database.backup` | always |
| `GET admin/database/backup/{file}` | `admin.database.download` | always |
| `DELETE admin/database/backup/{file}` | `admin.database.destroy` | always |
| `POST admin/database/restore` | `admin.database.restore` | always; controller returns 404 when the flag is off |

`{file}` is constrained to the backup filename pattern so it cannot traverse
outside the backup directory. Downloads are served through the controller from
a private disk; the directory is never web-reachable.

### Components

**`App\Services\Database\DatabaseBackupService`**

- `create(string $origin): string` — writes a gzipped dump of the configured
  tables, returns the path.
- Streams with `gzopen`/`gzwrite`; reads rows in chunks of 500 so memory stays
  flat.
- Depends on: the PDO connection, the configured table list, the backup disk.

**`App\Services\Database\DatabaseRestoreService`**

- `restore(string $archivePath): array{snapshot: string, rows: int}` — takes a
  filesystem path (the controller passes the upload's real path) so it is
  testable without faking an upload.
- Validates the archive (gzip magic byte, then every statement against an
  allow-list) before mutating anything, calls `DatabaseBackupService::create('auto')`,
  then in one transaction replays the dump's deletes and inserts and applies the
  media rewrite. Throws `InvalidBackupException` on a bad archive.
- Depends on: the backup service and the media rewriter.

**`App\Services\Database\MediaPathRewriter`**

- `rewrite(string $value): string` — pure string transform, no I/O.
- Returns input unchanged when no fallback URL is configured.

**`App\Services\Database\BackupRepository`**

- `all(): Collection` — filename, size, timestamp, origin label.
- `prune(int $keep): void`, `delete(string $file): void`,
  `path(string $file): string`.
- Reads and writes through the `backups` disk (`database/backups/`); the only
  place a caller-supplied filename is trusted, guarded by the filename pattern.

**`App\Http\Controllers\Admin\DatabaseController`**

- Thin. Resolves services, passes results to the view. Follows the existing
  controllers under `app/Http/Controllers/Admin/`.

### Backup file format

Gzipped SQL named `backup-{host}-{YmdHis}.sql.gz`. The host makes prod dumps
distinguishable from dev's snapshots in the listing.

**One SQL statement per physical line.** CKEditor bodies contain semicolons
(`&nbsp;`) and newlines, so splitting a dump on `;` is unsafe. Values are
escaped with `PDO::quote`, which renders newlines as `\n`, keeping every
statement on one line. Restore splits on `\n` and performs no SQL parsing.

The result is still valid `.sql` and can be pasted into cPanel's phpMyAdmin for
disaster recovery independently of this feature.

Structure:

```
-- header: source host, UTC timestamp, table list, row counts
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM `post_translations`;
...
INSERT INTO `posts` (`id`, ...) VALUES (...),(...);
...
SET FOREIGN_KEY_CHECKS=1;
```

Inserts are batched, with each batch on one line.

### Data flow

Backup: controller → `DatabaseBackupService::create('manual')` → gz file on the
private disk → `BackupRepository::prune(10)` → redirect back with the file
listed.

Restore (dev only): upload → validate → snapshot via
`DatabaseBackupService::create('auto')` → transaction (delete, insert, rewrite)
→ redirect with a summary of rows imported per table.

### Configuration

New `config/database_admin.php`:

```php
return [
    'restore_enabled' => env('DB_RESTORE_ENABLED', false),
    'media_fallback_url' => env('MEDIA_FALLBACK_URL'),
    'retention' => 10,
    'max_upload_kilobytes' => 20480,
    'tables' => ['posts', 'post_translations', 'media', 'site_settings'],
];
```

`.github/scripts/make-env.sh` gains two lines, both defaulting to off so prod is
fail-closed:

```
DB_RESTORE_ENABLED=${DB_RESTORE_ENABLED:-false}
MEDIA_FALLBACK_URL="${MEDIA_FALLBACK_URL:-}"
```

Both are set as GitHub *variables* on the `dev` environment only. Prod receives
`false` and an empty string. Both are added to `.env.example`.

## Error handling

| Case | Behaviour |
| --- | --- |
| Upload is not gzip | Rejected on magic-byte check, before any write. |
| Upload exceeds the configured size limit | Rejected by validation with the limit named in the message. |
| Upload's header names unexpected tables | Rejected before the transaction opens. |
| A statement fails mid-restore | Transaction rolls back; the pre-restore snapshot is retained and its filename shown. |
| Backup directory missing or unwritable | Created on demand; a clear error if creation fails. |
| Requested backup filename fails the route pattern | 404 from route constraint, before the controller. |
| Restore posted on prod | 404 — the route does not exist there. |
| PHP execution time or upload limits exceeded | Documented in `DEPLOY-CPANEL.md` as a known shared-host ceiling with the phpMyAdmin fallback noted. |

## Testing

Per `CLAUDE.md`, every behaviour below starts as a failing test.

Coverage limit stated plainly: the suite runs in-memory SQLite while the real
path is MySQL, so MySQL dialect bugs cannot be caught in CI. SQLite accepts
backtick identifiers, so generated dumps are expected to replay in tests; this
is verified in the red phase rather than assumed. If it does not hold, the dump
writer gets a thin quoting seam and tests target that.

| Area | Test |
| --- | --- |
| Route gating | Restore route 404s when the flag is off; reachable when on. |
| View gating | The restore form is absent from the page when the flag is off. |
| Dashboard link | The `Database` link appears on the dashboard. |
| Auth | All routes redirect to login when unauthenticated. |
| Dump content | A dump of seeded content contains the expected rows for each table. |
| Line discipline | A post body containing newlines, semicolons and quotes produces exactly one line per statement. |
| Round trip | Dump, wipe, restore, and confirm rows match the original. |
| Snapshot | Restore writes an `auto` backup before mutating anything. |
| Rollback | A failing restore leaves existing rows untouched. |
| Media rewrite | Unit test over `media.url` and an embedded body; no-op when unconfigured. |
| Retention | Pruning keeps exactly the 10 most recent. |
| Path safety | A traversal attempt in `{file}` is rejected. |
| Validation | Non-gzip and oversized uploads are rejected. |

After each green step, the full suite runs — this repo has cross-cutting state
in `SiteSetting.sections`, the active theme and locale that other tests assert
against.

## Documentation

Updated in the same change, per `CLAUDE.md`:

- `README.md` — the new routes, `config/database_admin.php`, the `backups`
  filesystem disk and where backups live (`database/backups/`, preserved across
  deploys), and the two environment variables.
- `docs/DEPLOY-CPANEL.md` — configuring `DB_RESTORE_ENABLED` and
  `MEDIA_FALLBACK_URL` on the `dev` GitHub environment, the backup-download-
  upload workflow, and the shared-host size and timeout ceilings.

No theme CSS, shared view markup, token registry or `theme.json` shape changes,
so `public/themes/AUTHORING.md` and the theme manifests are unaffected.

## Out of scope

- Restoring into local SQLite.
- Transferring media files themselves.
- Copying `users`, sessions, cache or queue tables.
- Scheduled or automatic backups.
- Any dev → prod direction.
