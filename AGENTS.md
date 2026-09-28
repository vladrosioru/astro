# AI Agent Guidelines — Astrotherapia (Laravel 13)

This document establishes operational context, architecture constraints, and quality standards for AI coding agents operating within this repository.

---

## 1. Project Overview & Core Philosophy

**Astrotherapia** is a reusable, multilingual marketing site and journal/blog platform built on **Laravel 13**, tailored specifically for deployment to **cPanel shared hosting**.

### Fundamental Constraints:
- **Zero Node / Build-Free Frontend**: The deployment target is shared hosting with no build pipeline. There is **no Node, no Vite, no Tailwind, and no npm**. All styling and scripts are vanilla CSS and vanilla JavaScript served statically from `public/`. Fonts are self-hosted WOFF2 files. Do NOT introduce npm packages, build steps, or Tailwind CSS classes.
- **Design Token Theming**: Visual styling is decoupled through a CSS design-token contract located in `public/themes/theme_<name>/theme.json` and validated by `public/themes/theme.schema.json`.
- **Pure PHP Database Portability**: The hosting environment has `exec()` disabled (no `mysqldump`). Database dumps and restores rely on pure PHP PDO streaming (`App\Services\Database\DatabaseBackupService`).

---

## 2. Technology Stack

| Layer | Technology | Details |
|---|---|---|
| **Framework** | Laravel 13 | PHP 8.3+ locally; PHP 8.4 in CI and production |
| **Database** | SQLite & MySQL | SQLite for local development (`database/database.sqlite`) & tests (`:memory:`); MySQL in production |
| **Frontend** | Vanilla CSS + JS | Pure CSS3 (custom properties / design tokens) and lightweight vanilla JS |
| **Rich Text** | CKEditor 5 + Purifier | Self-hosted GPL build of CKEditor 5; sanitised on save with HTMLPurifier (`mews/purifier`) |
| **Media Handling** | Intervention Image v4 | Image manipulation, resizing, and responsive thumbnails |
| **Internationalization**| Custom Locale Prefix | `en` and `ro` with prefix routes (`/{locale}/...`) managed via `SetLocale` middleware |
| **Code Formatter** | Laravel Pint | Enforces strict PSR-12 / Laravel styling guidelines |
| **Testing** | PHPUnit 12 | Feature and Unit test suites (`tests/Feature`, `tests/Unit`) |

---

## 3. Directory Layout

```
.
├── app/
│   ├── Console/Commands/       # Artisan commands (create-admin, apply-theme, etc.)
│   ├── Http/
│   │   ├── Controllers/        # Public controllers (PageController, BlogController) & Admin/
│   │   └── Middleware/         # SetLocale, EnsureAdmin
│   ├── Mail/                   # Mailable classes (e.g., ContactMessage)
│   ├── Models/                 # Eloquent models (SiteSetting, Post, PostTranslation, Author, Media, User)
│   ├── Services/
│   │   ├── Database/           # Backup, restore, quoter services (pure PHP PDO dumper)
│   │   └── ThemeManager.php    # Theme activation, validation, and token compilation
│   └── Support/                # Global helper functions (helpers.php)
├── config/                     # Application configurations (database_admin.php, tokens.php, etc.)
├── docs/                       # Architecture, operations, and deployment documentation
│   ├── arhitecture.md          # Site structure and navigation specifications
│   ├── OPERATIONS.md           # Hosting specifics, WAF rules, and incident records
│   ├── DEPLOY-CPANEL.md        # Deployment runbook and scripts
│   └── BACKLOG.md              # Feature roadmap and pending tasks
├── public/
│   ├── admin-assets/           # Assets dedicated strictly to the admin portal
│   ├── themes/                 # Theme packages (theme_default, theme_solarsystem)
│   │   ├── AUTHORING.md        # Shared view class contract and token authoring guide
│   │   ├── theme.schema.json   # JSON schema validating theme manifests
│   │   └── theme_<name>/       # Self-contained theme package with theme.json, CSS, JS, fonts
│   ├── extract.php             # Deployment extraction hook
│   └── deploy.php              # Deployment post-extraction hook
├── resources/views/
│   ├── admin/                  # Admin dashboard, forms, themes picker, and database manager
│   ├── blog/                   # Public journal listing and post views
│   ├── layouts/                # Base layouts (app.blade.php, admin.blade.php)
│   ├── pages/                  # Public static pages (home, about, services, contact)
│   └── partials/               # Shared components (nav, footer, analytics, flash messages)
├── routes/
│   └── web.php                 # Multilingual public routes and guarded admin routes
└── tests/
    ├── Feature/                # Integration and end-to-end HTTP/CLI tests
    └── Unit/                   # Model, service, and contract tests
```

---

## 4. Development Workflow & Quality Disciplines

### Test-Driven Development (TDD) — Mandatory
**No production code without a failing test first.** Every feature, bug fix, or behavioral change must follow this lifecycle:
1. **Red**: Write a focused test in `tests/Feature` or `tests/Unit`. Run it scoped:
   ```bash
   php artisan test --filter=YourTestName
   ```
   Verify that it fails for the expected reason (not a syntax or setup error).
2. **Green**: Write the minimal code required to pass the test. Run the **full** suite:
   ```bash
   php artisan test
   ```
   *Note: Cross-cutting state (`SiteSetting.sections` visibility toggles, active theme, locale prefixing) must never be broken by localized changes.*
3. **Refactor**: Clean up and optimize while remaining green.
4. **Bug Fixes**: Always write a reproduction test before fixing any bug.

### Full CI Pre-Commit Gate
Do not commit without running the three CI checks locally in sequence:
```bash
# 1. Style & Linting
vendor/bin/pint --test       # If errors occur, run 'vendor/bin/pint' to auto-fix, then re-test

# 2. Complete Test Suite
php artisan test

# 3. Security Audit
composer audit --no-dev
```

---

## 5. Security & Live Infrastructure Rules

1. **Ask Before Outward Actions**:
   - **Never connect directly to the live host** (FTP/FTPS, SSH, cPanel, SMTP, or direct HTTP probing) without explicit, per-action permission from the repository owner.
   - Deploys are performed manually by the owner; agents commit code locally and stop there.
2. **Never Send Bare `curl` to Live Sites**:
   - The production host uses LiteSpeed + Imunify360 WAF, which blocks generic HTTP clients with a JavaScript/cookie challenge ("One moment, please…") returning HTTP 200.
   - **Use dedicated helper scripts only**:
     - Deploy hooks: `.github/scripts/run-deploy-hook.sh URL MARKER TOKEN [MAX_TIME]`
     - Health checks / probes: `.github/scripts/fetch-site.sh URL OUT_FILE [MAX_TIME]`
   - Every request must include standard browser headers, a shared cookie jar (`-c/-b "$WAF_COOKIE_JAR"`), body content verification, and retries with backoff.

---

## 6. Documentation & Contract Integrity

When modifying code, maintain these documentation contracts in the same commit:
- **Theme Manifests**: If altering theme CSS/JS, `@font-face` definitions, or views, update the corresponding `public/themes/theme_<name>/theme.json`. It must validate against `public/themes/theme.schema.json` (enforced by `ThemeJsonContractTest.php`).
- **Theme Authoring Guide**: If changing shared markup or token definitions in `config/tokens.php`, update `public/themes/AUTHORING.md`.
- **Infrastructure Docs**: If changing routes, middleware, models, database backup logic, or deployment mechanics, immediately update `README.md` and relevant docs in `docs/`.
