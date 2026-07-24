# Deploying to cPanel (dev + prod, no SSH)

This site deploys to shared cPanel hosting via GitHub Actions. The host offers
**no SSH** — only File Manager / FTP — so the pipeline:

1. builds the production app in CI (`composer install --no-dev`) into a single
   `app.zip` ([`.github/scripts/make-archive.php`](../.github/scripts/make-archive.php)),
2. uploads three files over **FTPS** with `curl` — `app.zip`, the generated
   `.env`, and a bootstrap [`public/extract.php`](../public/extract.php) —
   instead of syncing thousands of `vendor/` files individually (much faster),
3. calls token-guarded web hooks: `extract.php` unzips `app.zip` server-side,
   then [`public/deploy.php`](../public/deploy.php) runs `artisan`
   (migrate, cache, `storage:link`).

The server `.env` and `storage/` are never in the archive, so they survive
every deploy. Extraction overlays files (it does not delete files removed from
the repo).

The workflow is [`.github/workflows/cicd.yml`](../.github/workflows/cicd.yml).
It is a single-branch cascade off `master`:

```
lint → test → security → build → deploy_dev → test_dev → [approve] deploy_prd → test_prd
```

`deploy_prd` targets the GitHub `production` environment, whose **required
reviewer** rule is the manual approval gate before production.

> **Custom port for dev is not possible here.** Shared cPanel (Apache/LiteSpeed)
> only serves apps on 80/443 through domain/subdomain vhosts. Dev therefore
> lives on its own subdomain (e.g. `dev.astrotherapia.com`), still over HTTPS.

## One-time cPanel setup (do this for BOTH dev and prod)

Do everything below **twice** — once for the dev subdomain, once for prod —
with separate databases and separate app directories.

1. **PHP version & extensions** — cPanel → *MultiPHP Manager*: set the
   (sub)domain to **PHP 8.4** (match CI; Laravel 12 supports 8.2–8.4, and
   `intervention/image` needs ≥ 8.3). Extensions are per-version, so in
   *Select PHP Version → Extensions* for 8.4 enable: `pdo_mysql`, `mysqli`,
   `gd` (Intervention Image needs it), `mbstring`, `openssl`, `fileinfo`,
   `curl`, `intl`, `bcmath`, `zip` (server-side unzip).

2. **MySQL database** — cPanel → *MySQL Databases*: create a database and a
   user, then **add the user to the database with ALL PRIVILEGES**. cPanel
   prefixes both with your account name (e.g. `acct_astro_prod`,
   `acct_astrouser`). Note the final names — they go into GitHub secrets.

3. **App directory + subdomain docroot** — put the app **outside**
   `public_html` so its `.env`/source can never be served directly:
   - Create the app dir, e.g. `/home/<acct>/astrotherapia_prod` (prod) and
     `/home/<acct>/astrotherapia_dev` (dev).
   - cPanel → *Domains* / *Subdomains*: create the (sub)domain and set its
     **Document Root** to the app's `public/` folder, e.g.
     `/home/<acct>/astrotherapia_prod/public`.
   - The FTP `server-dir` for that environment is the **app dir** (the parent
     of `public/`), home-relative — e.g. `astrotherapia_prod/`.

4. **FTP access** — use the main cPanel login, or cPanel → *FTP Accounts* to
   create a dedicated user. Confirm **FTPS on port 21** is allowed. The host is
   `server20.romania-webhosting.com` (or as your host specifies).

You do **not** need to upload `.env` or run any command by hand — CI writes the
`.env` (from your GitHub secrets) and runs migrations via the deploy hook.

## GitHub setup

### Environments

Repo → *Settings → Environments* → create **`dev`** and **`production`**.
On **`production`**, enable **Required reviewers** and add yourself — that
turns `deploy_prd` into a hold-for-approval step.

### Per-environment secrets & variables

Set these on **each** environment (dev values on `dev`, prod values on
`production`). *Secrets* are hidden; *Variables* are plain.

| Name | Kind | Example / notes |
|---|---|---|
| `FTP_HOST` | secret | the **server hostname** whose TLS cert matches, e.g. `server20.romania-webhosting.com` — not the `ftp.<domain>` alias cPanel shows (that fails FTPS cert validation) |
| `FTP_USERNAME` | secret | a **dedicated FTP account scoped to the app dir** (e.g. `deploy_dev@astrotherapia.com`) |
| `FTP_PASSWORD` | secret | that account's password |
| `APP_URL` | variable | `https://astrotherapia.com` (prod) / `https://dev.astrotherapia.com` (dev) |
| `APP_DEBUG` | variable | `false` on prod, `true` on dev |
| `APP_KEY` | secret | `base64:...` from `php artisan key:generate --show` (one per env) |
| `DEPLOY_TOKEN` | secret | random, e.g. `php -r "echo bin2hex(random_bytes(24));"` (one per env) |
| `DB_HOST` | variable | usually `localhost` |
| `DB_DATABASE` | secret | prefixed DB name |
| `DB_USERNAME` | secret | prefixed DB user |
| `DB_PASSWORD` | secret | DB user password (avoid a literal `"`) |
| `MAIL_HOST` | variable | SMTP host (contact form) |
| `MAIL_PORT` | variable | `587` |
| `MAIL_USERNAME` | secret | SMTP user |
| `MAIL_PASSWORD` | secret | SMTP password |
| `MAIL_FROM_ADDRESS` | variable | `no-reply@astrotherapia.com` |

`APP_KEY` must stay **stable** per environment — changing it invalidates
existing sessions and any encrypted data.

## Running a deploy

**Every push to `master` runs the full pipeline, then waits for your approval
before prod.** It lints, tests, scans, builds one artifact, deploys to dev,
smoke-tests dev, then **parks at the `production` approval gate**. Approve the
run you want in its **"Review deployments"** prompt and the *same* artifact
deploys to prod and gets smoke-tested; leave the others unapproved and nothing
reaches prod.

Deploy dev as often as you like — each push parks its own prod approval, so just
approve the build you actually want to promote and ignore the rest. A parked run
does **not** block new dev runs (no workflow-level concurrency; `deploy-prod`
serializes separately). Only pull requests skip the deploys.

- **Every deploy uploads one `app.zip`** (~30 MB, a single FTPS transfer of a
  minute or two) plus the small `.env` and `extract.php`. `extract.php` unzips
  it on the server. No per-file syncing — deploy time is roughly constant
  regardless of how much of `vendor/` changed.
- **Migrations run every deploy** (`migrate --force`, additive). Content is
  entered through the admin panel on the live site.

## Rolling back production

The host has no SSH or server-side git, so rollback is **redeploy the previous
good build**, not `git reset`. Every successful pipeline keeps its `app.zip` as
a GitHub artifact (`app-build`, retained **30 days**).

To roll back: Actions tab → **Rollback Production** workflow → **Run workflow** →
enter the **run ID** of the last green pipeline *before* the bad one (open that
run, copy the ID from its URL). It downloads that run's `app-build`, redeploys
the *same* bits to prod behind the **`production` approval gate**, then
smoke-tests. It serializes on the `deploy-prod` concurrency group, so it never
races a normal deploy.

**This is a code-only rollback.** `migrate --force` on the older code is a no-op
(the old `app.zip` carries only its own migration files — nothing pending), so
newer already-applied migrations simply stay and additive changes roll back
cleanly. The one gap: a migration that **dropped or renamed a column** the old
code still needs. Before shipping any such schema-breaking change, take a manual
DB snapshot first — cPanel → **phpMyAdmin** → select `martinis_astro_prod` →
**Export** — so you can restore the schema by hand if you have to roll back.

## Uploaded images (no symlink)

This host disables `symlink()`/`exec()`, so `php artisan storage:link` fails.
Instead the generated `.env` sets **`PUBLIC_DISK_IN_DOCROOT=true`**, which makes
the `public` filesystem disk write to a real `public/storage` folder (created by
`deploy.php`) that Apache serves directly at `/storage/...` — no symlink needed.
Uploads there persist across deploys (the folder is not in `app.zip`, and
extraction only overlays files). See `config/filesystems.php`.

## Security notes

- Transfer is **FTPS** (`curl --ssl-reqd`, encrypted). Never drop to plain FTP —
  it would send the FTP credentials and `.env` in the clear.
- `.env` is generated in CI and is **git-ignored** (`.env`, `.env.*`); it is
  never committed, and it is uploaded to the app root (above `public/`), not
  into the web-served docroot.
- Both `public/extract.php` and `public/deploy.php` require the `DEPLOY_TOKEN`
  in the `X-Deploy-Token` header (`hash_equals`). With no valid token they
  return 403 and do nothing; safe to leave deployed. `extract.php` only ever
  unzips the CI-built `app.zip`.
- Keep the app directory **outside** `public_html` so source/`.env` is never
  web-served.

## Troubleshooting

- **Upload fails `curl (60) SSL: no alternative certificate subject name`** →
  `FTP_HOST` doesn't match the FTPS server's TLS cert. Use the server hostname
  the cert is issued for (e.g. `server20.romania-webhosting.com`), not the
  `ftp.<domain>` alias. To see the cert's names:
  `openssl s_client -connect HOST:21 -starttls ftp </dev/null 2>/dev/null | openssl x509 -noout -subject -ext subjectAltName`.
- **Site 500 with `requires a PHP version >= X` though MultiPHP is set right** →
  the docroot PHP handler was overwritten by the deploy. cPanel applies the
  (sub)domain PHP version via a handler in the docroot `.htaccess`; our deploy
  replaces `public/.htaccess`, so the version is **pinned in the repo**
  (`AddHandler application/x-httpd-ea-phpNN` at the top of `public/.htaccess`).
  Keep it in sync with the CI `PHP_VERSION`. Check the real web PHP with a
  one-line `phpver.php` (`<?php echo PHP_VERSION;`).
- **Deploy hook returns 500** → almost always wrong DB secrets; `migrate`
  can't connect. Check `DB_*` on that environment.
- **`extract.php` 500 "zip extension not available"** → enable `zip` in the
  cPanel PHP extension list for the (sub)domain.
- **Smoke test fails at "did not come up"** → the (sub)domain docroot isn't
  pointed at `public/`, or DNS/SSL for the subdomain isn't ready yet.
- **Rollback** → re-run an earlier green workflow run to re-promote that
  artifact (artifacts are retained 5 days), or restore from a File Manager
  backup.
