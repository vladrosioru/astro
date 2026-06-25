# Reusable Site Template (PHP/Laravel) — cPanel Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make a cloned site deployable and operable on cPanel shared hosting: production env config, document-root setup, a repeatable deploy runbook, scheduler + queue via cron, database + media backups, AutoSSL, and a per-account readiness checklist.

**Architecture:** No Docker, no root, no long-running daemons. Apache + PHP-FPM and MySQL/MariaDB are provided by cPanel. The Laravel app's `public/` becomes the domain document root; app code and `.env` sit outside the web root. The Laravel scheduler is driven by a single cPanel cron; queued work uses the `database` driver drained by a cron, not a daemon. TLS is cPanel AutoSSL. This plan is mostly configuration, scripts, and a verifiable runbook; the few code artifacts (a health route, an env-validation command) are TDD'd, while ops steps use explicit commands with expected output instead of unit tests.

**Tech Stack:** PHP 8.2+, Laravel 11, MySQL/MariaDB, Apache (cPanel), cron, `mysqldump`, AutoSSL.

This is **Plan 4 of 4**. It depends on Plans 1–3 being complete and the test suite green.

## Global Constraints

- Target is **cPanel shared hosting**: no Docker, no systemd, no root, no persistent daemons, no Redis.
- Production drivers: `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `APP_ENV=production`, `APP_DEBUG=false`.
- Secrets live only in the server's `.env`, which sits **outside** the web root.
- Production extensions required on the account: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`, `ctype`, `tokenizer`, `xml`, `curl`.
- Conventional commits; commit at the end of each task that changes the repo. (Ops-only tasks document commands and produce no commit unless a file changes.)

---

### Task 1: Production environment config + queue/cache/session tables

**Files:**
- Create: `.env.production.example`
- Create: `database/migrations/2026_06_28_000001_create_queue_and_cache_tables.php` (only if not already present from earlier plans)
- Test: `tests/Feature/ProductionConfigTest.php`

**Interfaces:**
- Produces:
  - `.env.production.example` — a documented template the operator copies to `.env` on the server.
  - `jobs`, `cache`, and `sessions` tables (database drivers need them).

> Note: Laravel 11 ships stub migrations for `jobs`/`cache`/`sessions` under `database/migrations` (`0001_01_01_000001_create_cache_table`, `..._create_jobs_table`, and the sessions table). If those already exist in the repo, **skip creating a new migration** and only verify they are present. Create the combined migration below **only** if any are missing.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProductionConfigTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_driver_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }

    public function test_production_env_example_documents_required_keys(): void
    {
        $contents = file_get_contents(base_path('.env.production.example'));
        foreach (['APP_ENV=production', 'APP_DEBUG=false', 'SESSION_DRIVER=database', 'QUEUE_CONNECTION=database', 'CACHE_STORE=database', 'DB_CONNECTION=mysql', 'STRIPE_SECRET=', 'STRIPE_WEBHOOK_SECRET='] as $needle) {
            $this->assertStringContainsString($needle, $contents);
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ProductionConfigTest`
Expected: FAIL — `.env.production.example` does not exist (and possibly the tables, if the stubs were removed).

- [ ] **Step 3: Ensure the database-driver tables exist**

Run: `php artisan migrate:status`
Expected: entries for the cache, jobs, and sessions tables appear.

If any are missing, create `database/migrations/2026_06_28_000001_create_queue_and_cache_tables.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache');
    }
};
```

- [ ] **Step 4: Create the production env template**

Create `.env.production.example`:
```
APP_NAME="Your Site"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://example.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_sitedb
DB_USERNAME=cpaneluser_siteuser
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=public

MAIL_MAILER=smtp
MAIL_HOST=mail.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@example.com"

PAYMENTS_DRIVER=stripe
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=ProductionConfigTest`
Expected: PASS — both tests green.

- [ ] **Step 6: Commit**

```bash
git add .env.production.example tests/Feature/ProductionConfigTest.php database/migrations/2026_06_28_000001_create_queue_and_cache_tables.php 2>/dev/null; git add -A
git commit -m "feat: add production env template and database-driver tables"
```

---

### Task 2: Health route + env-readiness command

**Files:**
- Create: `app/Console/Commands/CheckDeployment.php`
- Modify: `routes/web.php` (add `/health`)
- Test: `tests/Feature/HealthAndReadinessTest.php`

**Interfaces:**
- Produces:
  - `GET /health` → 200 JSON `{ "status": "ok" }` (used by uptime checks; no locale prefix).
  - Artisan `app:check-deployment` → exits non-zero and lists problems if `APP_KEY` is empty, `APP_DEBUG` is true in production, the DB is unreachable, or required extensions are missing.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/HealthAndReadinessTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthAndReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_readiness_command_passes_with_a_reachable_db(): void
    {
        config(['app.key' => 'base64:' . base64_encode(str_repeat('a', 32))]);

        $this->artisan('app:check-deployment')->assertExitCode(0);
    }

    public function test_readiness_command_fails_without_app_key(): void
    {
        config(['app.key' => '']);

        $this->artisan('app:check-deployment')->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=HealthAndReadinessTest`
Expected: FAIL — `/health` route and `app:check-deployment` command are undefined.

- [ ] **Step 3: Add the health route**

Edit `routes/web.php` — at top level (outside the locale group):
```php
Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
```

- [ ] **Step 4: Create the readiness command**

Create `app/Console/Commands/CheckDeployment.php`:
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDeployment extends Command
{
    protected $signature = 'app:check-deployment';

    protected $description = 'Verify the app is ready to serve in production';

    public function handle(): int
    {
        $problems = [];

        if (empty(config('app.key'))) {
            $problems[] = 'APP_KEY is empty (run php artisan key:generate).';
        }

        if (config('app.env') === 'production' && config('app.debug')) {
            $problems[] = 'APP_DEBUG must be false in production.';
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $problems[] = 'Database unreachable: ' . $e->getMessage();
        }

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'gd', 'fileinfo', 'curl'] as $ext) {
            if (! extension_loaded($ext)) {
                $problems[] = "Missing PHP extension: {$ext}.";
            }
        }

        if ($problems) {
            foreach ($problems as $p) {
                $this->error($p);
            }

            return self::FAILURE;
        }

        $this->info('Deployment checks passed.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=HealthAndReadinessTest`
Expected: PASS — all three tests green (the test DB is reachable and the test extensions are loaded; `gd`/`pdo_mysql` were enabled in Foundation Task 0).

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/CheckDeployment.php routes/web.php tests/Feature/HealthAndReadinessTest.php
git commit -m "feat: add health endpoint and deployment readiness command"
```

---

### Task 3: Deploy runbook + scripts

**Files:**
- Create: `deploy/README.md`
- Create: `deploy/deploy.sh`
- Create: `deploy/cron.txt`

**Interfaces:**
- Produces: operator-facing documentation and copy-paste scripts. No app code; verification is by following the runbook on a cPanel account.

- [ ] **Step 1: Write the deploy script**

Create `deploy/deploy.sh`:
```bash
#!/usr/bin/env bash
# Run from the application root on the cPanel account (via SSH/Terminal).
set -euo pipefail

echo "==> Pulling latest code"
git pull --ff-only

echo "==> Installing dependencies (production)"
composer install --no-dev --optimize-autoloader

echo "==> Running migrations"
php artisan migrate --force

echo "==> Linking storage"
php artisan storage:link || echo "storage:link skipped (symlink may already exist or be unsupported)"

echo "==> Caching config, routes, views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Verifying readiness"
php artisan app:check-deployment

echo "==> Done"
```

- [ ] **Step 2: Write the cron reference**

Create `deploy/cron.txt`:
```
# cPanel → Cron Jobs. Replace /home/cpaneluser/app with the real app path.

# Laravel scheduler (drives all scheduled tasks). Runs every minute.
* * * * * /usr/local/bin/php /home/cpaneluser/app/artisan schedule:run >> /dev/null 2>&1

# Drain the database queue every 5 minutes (no daemon on shared hosting).
*/5 * * * * /usr/local/bin/php /home/cpaneluser/app/artisan queue:work --stop-when-empty --max-time=240 >> /home/cpaneluser/app/storage/logs/queue.log 2>&1

# Nightly database backup at 02:15 (see deploy/backup.sh, Task 4).
15 2 * * * /home/cpaneluser/app/deploy/backup.sh >> /home/cpaneluser/app/storage/logs/backup.log 2>&1
```

- [ ] **Step 3: Write the runbook**

Create `deploy/README.md`:
```markdown
# Deploying a cloned site to cPanel shared hosting

## One-time setup
1. **PHP version:** cPanel → MultiPHP Manager → set the domain to PHP 8.2+.
2. **Extensions:** cPanel → Select PHP Version → Extensions → enable `pdo_mysql`, `mbstring`,
   `openssl`, `gd`, `fileinfo`, `ctype`, `tokenizer`, `xml`, `curl`.
3. **Database:** cPanel → MySQL Databases → create a database + user, grant ALL. Note the
   prefixed names (e.g. `cpaneluser_sitedb`).
4. **Code:** cPanel → Git Version Control → Create → clone this repo into `~/app`
   (a folder OUTSIDE `public_html`). If Git is unavailable, upload the project via File
   Manager / SFTP into `~/app`.
5. **Document root:** point the domain at the app's `public/`:
   - Preferred: cPanel → Domains → set the document root to `app/public`.
   - If the host forces `public_html`: move the contents of `app/public` into `public_html`,
     and in `public_html/index.php` change the two `require`/`$app` paths from `__DIR__.'/../'`
     to `__DIR__.'/../app/'` so they point at the relocated app.
6. **Env:** copy `.env.production.example` to `~/app/.env`, fill in DB + mail + Stripe values,
   then run `php artisan key:generate`.
7. **First deploy:** from `~/app` run `bash deploy/deploy.sh`.
8. **Cron:** add the three jobs from `deploy/cron.txt` (paths adjusted).
9. **TLS:** cPanel → SSL/TLS Status → run AutoSSL for the domain (Let's Encrypt).
10. **Admin user:** `php artisan app:create-admin you@example.com 'a-strong-password'`.
11. **Stripe webhook:** in the Stripe dashboard add an endpoint pointing at
    `https://example.com/payments/webhook`; copy its signing secret into `STRIPE_WEBHOOK_SECRET`
    and re-run `php artisan config:cache`.

## Routine deploys
From `~/app`: `bash deploy/deploy.sh`

## Rollback
`git reset --hard <previous-commit>` then re-run `deploy/deploy.sh`. Migrations that must be
reverted use `php artisan migrate:rollback --force`.
```

- [ ] **Step 4: Make the script executable and verify shell syntax**

Run:
```bash
chmod +x deploy/deploy.sh
bash -n deploy/deploy.sh && echo "deploy.sh syntax OK"
```
Expected: `deploy.sh syntax OK`.

- [ ] **Step 5: Commit**

```bash
git add deploy/README.md deploy/deploy.sh deploy/cron.txt
git commit -m "docs: add cPanel deploy runbook, deploy script, and cron reference"
```

---

### Task 4: Database + media backup script

**Files:**
- Create: `deploy/backup.sh`
- Modify: `deploy/README.md` (reference restore steps)

**Interfaces:**
- Produces: `deploy/backup.sh` — dumps the MySQL DB and snapshots the media directory into a dated archive under `~/backups`, pruning archives older than 14 days.

- [ ] **Step 1: Write the backup script**

Create `deploy/backup.sh`:
```bash
#!/usr/bin/env bash
# Nightly backup for a cPanel-hosted site. Reads DB creds from the app .env.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${HOME}/backups"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "${BACKUP_DIR}"

# Read DB_* from .env without sourcing the whole file.
get_env() { grep -E "^$1=" "${APP_DIR}/.env" | head -n1 | cut -d= -f2- | tr -d '"'; }
DB_NAME="$(get_env DB_DATABASE)"
DB_USER="$(get_env DB_USERNAME)"
DB_PASS="$(get_env DB_PASSWORD)"

echo "==> Dumping database ${DB_NAME}"
mysqldump --single-transaction --user="${DB_USER}" --password="${DB_PASS}" "${DB_NAME}" \
    | gzip > "${BACKUP_DIR}/db-${STAMP}.sql.gz"

echo "==> Archiving media"
tar -czf "${BACKUP_DIR}/media-${STAMP}.tar.gz" -C "${APP_DIR}/storage/app/public" . 2>/dev/null || true

echo "==> Pruning backups older than 14 days"
find "${BACKUP_DIR}" -name '*.gz' -mtime +14 -delete

echo "==> Backup complete: ${BACKUP_DIR}"
```

- [ ] **Step 2: Add restore notes to the runbook**

Edit `deploy/README.md` — append:
```markdown

## Backups & restore
Nightly backups land in `~/backups` (see the cron entry). To restore:
- Database: `gunzip < ~/backups/db-<stamp>.sql.gz | mysql -u <user> -p <database>`
- Media: `tar -xzf ~/backups/media-<stamp>.tar.gz -C ~/app/storage/app/public`
Download copies off-box regularly (cPanel → Backup, or SFTP) — shared hosting is not a backup.
```

- [ ] **Step 3: Make executable and verify shell syntax**

Run:
```bash
chmod +x deploy/backup.sh
bash -n deploy/backup.sh && echo "backup.sh syntax OK"
```
Expected: `backup.sh syntax OK`.

- [ ] **Step 4: Commit**

```bash
git add deploy/backup.sh deploy/README.md
git commit -m "feat: add database and media backup script"
```

---

### Task 5: Apache hardening + final readiness checklist

**Files:**
- Create: `public/.htaccess.security` (snippet the operator merges into `public/.htaccess`)
- Create: `deploy/CHECKLIST.md`

**Interfaces:**
- Produces: a security headers snippet and a go-live checklist. No app code.

- [ ] **Step 1: Create the security headers snippet**

Create `public/.htaccess.security`:
```apache
# Merge these directives into public/.htaccess (inside <IfModule mod_headers.c>).
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# Deny access to dotfiles (e.g. a misplaced .env).
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

- [ ] **Step 2: Create the go-live checklist**

Create `deploy/CHECKLIST.md`:
```markdown
# Go-live checklist (per cloned site)

## Environment
- [ ] PHP 8.2+ selected in MultiPHP Manager
- [ ] Required extensions enabled: pdo_mysql, mbstring, openssl, gd, fileinfo, ctype, tokenizer, xml, curl
- [ ] `php artisan app:check-deployment` passes on the server

## App config
- [ ] `.env` created from `.env.production.example`, secrets filled, **outside** web root
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `php artisan key:generate` run once
- [ ] `config:cache`, `route:cache`, `view:cache` run (via deploy.sh)
- [ ] Document root points at the app's `public/`
- [ ] `storage:link` succeeded (or media directory exposed another way)

## Security
- [ ] AutoSSL issued; site loads over HTTPS; HTTP redirects to HTTPS
- [ ] Security headers from `public/.htaccess.security` merged into `public/.htaccess`
- [ ] `.env` not reachable over the web (test: `https://example.com/.env` → 403/404)
- [ ] Admin user created with a strong password

## Payments
- [ ] Stripe live keys in `.env`; `STRIPE_WEBHOOK_SECRET` set
- [ ] Webhook endpoint registered in Stripe → `/payments/webhook`
- [ ] Test payment completes and the webhook is received (200)

## Operations
- [ ] Scheduler cron added (`schedule:run` every minute)
- [ ] Queue cron added (`queue:work --stop-when-empty` every 5 min)
- [ ] Backup cron added; first `deploy/backup.sh` run produced files in `~/backups`
- [ ] Off-box backup copy verified
```

- [ ] **Step 3: Run the full test suite one last time**

Run: `php artisan test`
Expected: PASS — every test across Plans 1–4 green.

- [ ] **Step 4: Commit**

```bash
git add public/.htaccess.security deploy/CHECKLIST.md
git commit -m "docs: add Apache hardening snippet and go-live checklist"
```

---

## Self-Review

**Spec coverage (Plan 4 slice):**
- §7 deployment: document-root setup, migrations via `migrate --force`, scheduler cron, queue strategy (database, no daemon), cache/session drivers, AutoSSL, `mysqldump` backups, env secrets — Tasks 1, 3, 4, 5. ✔
- §7 host caveats (PHP version, GD/Imagick, Composer/SSH, symlinks) — runbook + checklist (Tasks 3, 5) and `app:check-deployment` (Task 2). ✔
- §2 / §8 constraints (no Docker, no daemon, cPanel AutoSSL instead of Caddy) — honored throughout; queue uses cron-drained `database` driver. ✔

**Placeholder scan:** Ops tasks use explicit commands with expected output in place of unit tests (appropriate for non-code steps); the two code artifacts (`/health`, `app:check-deployment`) are TDD'd with full code. No TBD/TODO. ✔

**Type/name consistency:** `app:check-deployment`, route `/health`, env keys (`SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION`/`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET`), and the `/payments/webhook` path all match the names defined in earlier plans. ✔

---

## Execution Handoff

This completes the four-plan sequence: Foundation → Admin+Blog → Payments → Deployment. After all four are executed and `php artisan test` is green, a cloned site is buildable locally and deployable to cPanel.
