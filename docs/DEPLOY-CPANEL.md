# Deploying to cPanel (dev + prod, no SSH)

This site deploys to shared cPanel hosting via GitHub Actions. The host offers
**no SSH** — only File Manager / FTP — so the pipeline:

1. builds the production app in CI (`composer install --no-dev`),
2. uploads it over **FTPS** ([`SamKirkland/FTP-Deploy-Action`]),
3. runs the post-deploy `artisan` steps by calling a token-guarded web hook,
   [`public/deploy.php`](../public/deploy.php) (migrate, cache, storage:link).

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
   (sub)domain to **PHP 8.2+**. In *Select PHP Version → Extensions* enable:
   `pdo_mysql`, `mysqli`, `gd` (Intervention Image needs it), `mbstring`,
   `openssl`, `fileinfo`, `curl`, `intl`, `bcmath`.

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
| `FTP_HOST` | secret | `server20.romania-webhosting.com` |
| `FTP_USERNAME` | secret | cPanel or FTP-account user |
| `FTP_PASSWORD` | secret | that account's password |
| `FTP_SERVER_DIR` | variable | app dir, home-relative, trailing slash — `astrotherapia_prod/` |
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

Push to `master` (or run the workflow manually). The pipeline lints, tests,
scans, builds one artifact, deploys it to dev, smoke-tests dev, then **waits
for your approval** on the `production` environment before deploying the same
artifact to prod and smoke-testing it.

- **First run is slow.** The initial FTPS upload pushes all of `vendor/`
  (thousands of files). Later runs are incremental — the action keeps a
  per-environment state file (`.ftp-deploy-dev.json` / `.ftp-deploy-prod.json`)
  on the server and only sends changed files.
- **Migrations run every deploy** (`migrate --force`, additive). Content is
  entered through the admin panel on the live site.

## Storage symlink

Admin image uploads are written to `storage/app/public` and served at
`/storage/...`, which needs the `public/storage` symlink. The deploy hook runs
`php artisan storage:link` for you (non-fatal). Most CloudLinux/cPanel hosts
allow `symlink()`. If yours doesn't and uploaded images 404, either ask the
host to enable it, or point the `public` disk directly at a real folder under
the docroot in `config/filesystems.php`.

## Security notes

- Transfer is **FTPS** (encrypted). Plain FTP would send credentials and
  `.env` in the clear — do not switch `protocol` to `ftp`.
- `.env` is generated in CI and is **git-ignored** (`.env`, `.env.*`); it is
  never committed and never placed in `public/`.
- `public/deploy.php` requires the `DEPLOY_TOKEN` in the `X-Deploy-Token`
  header (`hash_equals`). With no valid token it returns 403 and does nothing;
  it is safe to leave deployed.
- Keep the app directory **outside** `public_html` so source/`.env` is never
  web-served.

## Troubleshooting

- **Deploy hook returns 500** → almost always wrong DB secrets; `migrate`
  can't connect. Check `DB_*` on that environment.
- **Smoke test fails at "did not come up"** → the (sub)domain docroot isn't
  pointed at `public/`, or DNS/SSL for the subdomain isn't ready yet.
- **`vendor/autoload.php` not found on server** → the build/upload didn't
  complete; re-run the job (the first upload can time out — it resumes).
- **Rollback** → re-run an earlier green workflow run to re-promote that
  artifact (artifacts are retained 5 days), or restore from a File Manager
  backup.

[`SamKirkland/FTP-Deploy-Action`]: https://github.com/SamKirkland/FTP-Deploy-Action
