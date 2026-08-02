# Restore a Backup — Design

Date: 2026-08-02

Supersedes the restore half of
[`2026-07-24-admin-database-page-design.md`](2026-07-24-admin-database-page-design.md).
The backup half of that document still describes what ships.

## Goal

Let the operator roll the site back to one of its own backups from
`GET /admin/database`, on production as well as dev.

`App\Services\Database\DatabaseRestoreService` already does the work — gzip
check, statement allow-list, automatic pre-restore snapshot, transactional
replay, media rewrite. Its only caller is `DatabaseController::pull()`, which
copies live production into dev over the read-only `source` connection. A backup
listed on the page can therefore be created, downloaded and deleted, but not put
back. This adds the missing entry point.

## Context and constraints

| Constraint | Source | Consequence |
| --- | --- | --- |
| Both boxes deploy the same artifact with `APP_ENV=production` | `.github/scripts/make-env.sh:14` | The framework cannot tell dev from prod. Any rule that must differ per box has to derive from data, not from the environment. |
| `DB_RESTORE_ENABLED` is true only on the dev GitHub environment | `config/database_admin.php:13` | Reusing that flag for this feature would block the primary use case, which is rolling back production. |
| Backup filenames carry the host that wrote them | `BackupRepository::filename()` | The host is available before the file is opened, which makes it usable as a gate. |
| The dump is data-only, content tables only | `config/database_admin.php:34` | A restore never touches `users`, sessions, cache or queue tables. It cannot log anyone out or damage an account. |
| Media files are not in the dump | `2026-07-24` spec, "Media" | A restore rewires rows, never files. Rows restored on a host whose media directory has since been emptied will point at missing files. |
| Tests run in-memory SQLite; servers run MySQL | `phpunit.xml`, `.env.example:26` | Dialect and isolation-level behaviour is not catchable in CI. Stated again under Testing. |

## Decisions

### Use case: roll back this site's own content

The operator edits or deletes content on production and wants it back. Everything
below follows from that being the primary case, not a dev convenience.

### Gate: filename host must match this host

`BackupRepository` parses the host segment out of the filename and compares it
to the host of `config('app.url')`. Mismatch aborts 404.

Production restores production's backups. Dev restores dev's. Neither can be
made to swallow the other's data by a mis-click, and the rule is in code rather
than in an environment variable somebody can flip.

Rejected: a second env flag (`DB_ROLLBACK_ENABLED`). It needs a deliberate
GitHub environment edit to enable, but once enabled it is permanently on and
still permits restoring a foreign dump. It buys ceremony, not safety.

Rejected: allowing any listed file plus a typed confirmation. Simpler to state,
but it makes the operator the only thing standing between a dev dump and live
content.

404 rather than 403, so production does not confirm which backups exist.

### No environment flag for restore

`config('database_admin.restore_enabled')` is unchanged and keeps gating only
`pull()`, which is genuinely dev-only because it needs production credentials on
the lower-trust box.

The host-match rule makes a flag redundant. Combined with dropping upload
(below), no file from another host can reach either box's backup directory in
the first place.

### Upload restore: dropped

The `2026-07-24` spec planned a `POST admin/database/restore` upload form, which
never shipped. It stays unbuilt.

Getting live content onto dev is what `pull()` does. With upload gone, every
restorable file was written by this application's own dumper, which removes the
whole class of risk from foreign archives — including the fact that
`DatabaseRestoreService`'s allow-list matches on the *start* of each line, so a
hand-edited archive with a second statement appended after the semicolon would
pass validation and then execute under `DB::unprepared`. That is unreachable
today and stays unreachable. It must be fixed before any upload path is ever
added; it is noted here so a future change does not discover it the hard way.

### Confirmation: typed host, on a page showing row counts

`GET .../restore/{file}` renders a confirmation page. It shows the backup's
per-table row counts next to the live ones, and requires typing the site host to
enable the button.

The counts are the part that works. `posts 41 → 12` catches a wrong file; a
filename does not. Typing makes the click deliberate.

Rejected: the plain `confirm()` dialog used by the pull button. The `2026-07-24`
spec argued a snapshot makes a wrong restore recoverable, which is true, and that
argument was sound for a dev-only operation. Production raises the cost of the
recoverable-but-messy path enough to justify one extra screen.

The typed value is not re-checked server-side. It is friction for a human; the
host-match rule is the control.

### Row counts in the dump header

`writeDump()` writes one more header line:

```
-- rows: authors=3, posts=41, post_translations=82, media=210, site_settings=1
```

Counts are taken with `count(*)` per table before the tuples are written, and the
whole read runs inside one transaction on the dumping connection so the counts
describe the file rather than a moment before it.

That transaction also closes an existing hole: `writeTable()` chunks each table
separately with nothing spanning them, so a write landing mid-dump could produce
an archive containing a child row whose parent was never read. Read-only on
InnoDB under REPEATABLE READ, so it costs nothing.

Backups written before this change have no `rows:` line. The confirmation page
reports the counts as unavailable and still allows the restore. Refusing to
restore older backups would break the exact case the feature exists for.

### Media rewrite becomes the caller's decision

`DatabaseRestoreService::restore()` takes the media fallback URL as a parameter
instead of reading `config()` itself. `pull()` passes the configured value;
restore-from-file passes `null`.

Rewriting is a cross-host correction. A backup this host wrote already contains
paths that resolve here. On dev — where `MEDIA_FALLBACK_URL` points at
production — the current code would rewrite a dev-native backup's
`/storage/media/` paths to production URLs for files production has never seen.
Making the caller state its intent removes that silently-wrong path.

## Architecture

### Routes

Added inside the existing `admin` middleware group in `routes/web.php`, with the
same `[A-Za-z0-9.\-]+\.sql\.gz` constraint the download and destroy routes use.

| Method and URI | Name | Gate |
| --- | --- | --- |
| `GET admin/database/restore/{file}` | `admin.database.restore.confirm` | host match |
| `POST admin/database/restore/{file}` | `admin.database.restore` | host match |

Both are registered unconditionally. `public/deploy.php` runs `route:cache`, so
conditional registration would bake state into the cached route table and make
behaviour depend on deploy ordering — the same reasoning that governs the
existing routes.

### Components

**`App\Services\Database\BackupRepository`** — gains:

- `exists(string $name): bool`
- `header(string $name): array{created: ?CarbonInterface, rows: ?array<string,int>}` —
  reads only the leading `--` lines, stops at the first statement.
- `hostOf(string $name): string` — the host segment of the filename. Parsed by
  capturing group, not by splitting on `-`: hosts legitimately contain hyphens
  and dots, so the segment is what sits between the `HHMMSS-` prefix and the
  trailing `-manual` / `-auto`. `FILENAME_PATTERN` gains that capture group
  rather than a second regex being written beside it.
- `isRestorable(string $name): bool` — `exists()`, and `hostOf()` matches the
  host of `config('app.url')`.

Deciding what to do about a failed check stays in the controller
(`abort_unless($this->backups->isRestorable($file), 404)`). The repository
reports facts about files and does not know about HTTP.

**`App\Services\Database\DatabaseBackupService`** — `writeDump()` wraps its reads
in a transaction and emits the `rows:` header line. No signature change.

**`App\Services\Database\DatabaseRestoreService`** — `restore()` gains a second
parameter, the media fallback URL. `rewriteMediaPaths()` takes it as an argument
rather than reading config. No other change; validation, snapshot, transaction
and rollback already behave correctly for this case.

**`App\Http\Controllers\Admin\DatabaseController`** — gains `confirm()` and
`restore()`, both opening with `abort_unless($this->backups->isRestorable($file), 404)`.
`download()` and `destroy()` gain `abort_unless($this->backups->exists($file), 404)` —
existence only, since neither overwrites anything and a foreign-host file should
still be downloadable and deletable. That replaces today's 500 on a missing
download and today's silently-successful delete of a file that was never there.

**`resources/views/admin/database/confirm.blade.php`** — new. Full page in the
existing admin chrome, not a modal: it must survive a refresh and be linkable.

**`resources/views/admin/database/index.blade.php`** — each row gains a Restore
link, rendered only when `isRestorable()` holds.

### Data flow

```
GET  restore/{file}  -> isRestorable? -> header() + live counts -> confirm view
POST restore/{file}  -> isRestorable?
                     -> DatabaseRestoreService::restore(path($file), mediaFallbackUrl: null)
                        -> gzip magic check
                        -> statement allow-list (whole file, nothing mutated yet)
                        -> DatabaseBackupService::create('auto')   <- snapshot
                        -> transaction: replay deletes + inserts
                     -> redirect to index with rows restored and the snapshot name
```

### Confirmation page

```
Restore backup-20260801-220000-astrotherapia.com-manual.sql.gz

  created   1 Aug 2026, 22:00

  table               in backup     live now
  authors                     3            3
  posts                      41           12
  post_translations          82           24
  media                     210          210
  site_settings               1            1

  Replaces all content on this site. A snapshot of the current
  content is taken automatically first.

  Type astrotherapia.com to confirm:  [________________]

                             [ Cancel ]  [ Restore ]
```

The button is `disabled` until the field matches, via a small inline script —
consistent with the page's existing `onsubmit="return confirm(...)"` rather than
introducing anything new.

## Error handling

| Case | Behaviour |
| --- | --- |
| Filename host ≠ this host | 404 before the file is opened |
| File not on disk | 404 |
| Filename fails the route pattern | 404 from the route constraint |
| Filename fails `FILENAME_PATTERN` | `InvalidArgumentException` from the existing guard |
| Not gzip, or a disallowed statement | `InvalidBackupException` shown on the page; nothing mutated, no snapshot written |
| Snapshot cannot be written | `RuntimeException` propagates before the transaction opens; live data untouched |
| A statement fails mid-restore | Transaction rolls back; the snapshot is retained and its filename shown |
| Unauthenticated | Redirect to login, as with every `admin` route |

## Testing

Every behaviour starts as a failing test, per `CLAUDE.md`.

| Area | Test |
| --- | --- |
| Host match | Restoring a file whose host segment differs from `app.url`'s host 404s |
| Host parsing | A hyphenated host (`dev-2.astrotherapia.com`) round-trips through `filename()` and `hostOf()` without being truncated at the hyphen |
| Missing file | Restore, download and destroy of a vanished file all 404 |
| Path safety | The existing traversal coverage extends to both new routes |
| Auth | Both routes redirect to login when unauthenticated |
| Confirm page | Renders backup counts beside live counts |
| Old format | A headerless backup renders "unavailable" and stays restorable |
| Listing | Restore link present for an own-host row, absent for a foreign-host one |
| Round trip | Seed, back up, mutate, restore, rows match the original |
| Snapshot | Restore writes an `auto` backup before mutating |
| Rollback | A failing restore leaves rows untouched and keeps the snapshot |
| No rewrite | Restore from file leaves `/storage/media/` alone even with `MEDIA_FALLBACK_URL` set |
| Pull unchanged | `pull()` still rewrites — the new parameter did not regress it |
| Header | A dump's `rows:` line matches actual counts; `header()` parses it |

Coverage limit, unchanged from the `2026-07-24` spec: the suite runs in-memory
SQLite while both boxes run MySQL, so dialect-level bugs are invisible in CI. The
dump transaction is the one new thing whose isolation semantics differ between
the two engines, and no test exercises it against real concurrency.

The full suite runs after each green step — this repo has cross-cutting state in
`SiteSetting.sections`, the active theme and locale that other tests assert
against, and `site_settings` is one of the tables a restore replaces.

## Documentation

Updated in the same change, per `CLAUDE.md`:

- `README.md` — the two new routes, and a restore paragraph stating that it is
  governed by the filename host rather than by an environment flag.
- `docs/OPERATIONS.md` — a short rollback runbook.
- `2026-07-24-admin-database-page-design.md` — a superseded-by line on its
  restore section, which still describes the upload transfer that never shipped.

No theme CSS, shared view markup, token registry or `theme.json` change, so
`public/themes/AUTHORING.md` and the theme manifests are unaffected.

## Out of scope

Each is a separate change, not a rejection.

- Upload restore, and the allow-list hardening it would require first.
- An audit log of who restored, deleted or pulled what.
- Splitting retention so an `auto` snapshot burst cannot prune every manual
  backup (`prune(10)` currently counts both origins together).
- A `db:backup` artisan command and cron, so backups are not purely manual.
- Schema in the dump. It stays data-only; full recovery is migrations first,
  then a restore.
- Checksums in the listing.
- Restoring into local SQLite.
- Any dev → prod direction.
