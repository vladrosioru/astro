# Reusable Site Template (PHP/Laravel) — Architecture & Design Spec

**Date:** 2026-06-25
**Status:** Re-evaluated design, pending spec review
**Build model:** Custom build · one codebase cloned/forked per site · maintained by a developer
**Supersedes:** `2026-06-24-reusable-site-template-design.md` (Next.js/Postgres) for two changes — **theming removed** and **PHP/MySQL on cPanel shared hosting**.

---

## 1. Goal

A reusable, developer-owned website starter that can be cloned per project and customized. Each site is its own deployment on **cPanel shared hosting**. Core capabilities:

- Pages/sections: **Home, About, Blog, Contact**
- **Blog** with rich content and image uploads
- **Multilingual**: English + Romanian (extensible to more locales)
- **Payments**
- **Admin module**: edit blog posts, edit branding/site settings, edit payment details, show/hide site sections

**Removed from the original goal:** runtime theming (switch/customize/upload themes). Per-clone look is achieved by editing Blade templates + CSS, plus a small set of admin-editable branding settings.

## 2. Key decisions (and what was rejected)

| Decision | Choice | Rejected alternative & why |
|---|---|---|
| Hosting target | **cPanel shared hosting** (Apache + PHP-FPM + MySQL, managed by host) | Docker/VPS topology from the original spec — shared hosting has no root, no containers, no long-running daemons. This is the driving constraint behind the whole re-evaluation. |
| Framework | **Single Laravel 11 app** (admin = protected route group) | Plain PHP (reimplements auth/migrations/routing) and Symfony (more boilerplate for CRUD). Laravel runs well on cPanel and ships auth, ORM, migrations, payments. |
| Theming | **None now, but architected for it.** Templates + CSS + light admin branding settings, with all styles isolated behind a single design-token (CSS-variable) layer | Declarative theme packages, build-time code themes, sandboxed runtime execution — largest and riskiest part of the original; deferred, not designed out. Style isolation keeps the upgrade path open (see §5.3). |
| Rich text | **Trix** (pre-compiled JS/CSS, no Node build) → sanitized HTML | TipTap (requires a Node build pipeline); keeps the stack strictly PHP-only on the server with zero Node toolchain. |
| Front-end assets | **Plain CSS + Trix**, no build step | Vite/Tailwind pipeline — pulls Node into the toolchain; avoided to keep deployment to file upload / git pull. |
| Media | **Laravel Filesystem (`local` driver) + Intervention Image**, behind Flysystem | S3/R2 as a mandatory service — extra service to run/secure. Flysystem allows a swap later with no app-code change. |
| Payments | **Provider behind an interface; Stripe (Cashier) default**, Mollie optional | Hard-coding a provider now. |
| TLS | **cPanel AutoSSL (Let's Encrypt)** | Caddy/Nginx + certbot — not available or needed on managed shared hosting. |

## 3. Technology stack

| Concern | Technology |
|---|---|
| Framework | Laravel 11 (PHP 8.2+) |
| Language | PHP (server) · minimal browser JS/CSS |
| Templating | Blade |
| Auth (admin) | Laravel Breeze or Fortify; DB-backed sessions |
| Rich text | Trix (ships pre-compiled); content stored as sanitized HTML |
| HTML sanitization | `mews/purifier` (HTMLPurifier) on save and/or render |
| i18n | Laravel localization (lang files) + locale-prefixed routes (`/en`, `/ro`) |
| Database | MySQL 8 / MariaDB (whatever cPanel provides) |
| ORM | Eloquent + migrations (JSON columns for settings) |
| Image processing | Intervention Image (GD; Imagick if available) |
| Payments | Stripe via Laravel Cashier **or** Mollie behind a `PaymentProvider` interface |
| File storage | Laravel Filesystem / Flysystem (`local` driver) |
| Deployment | cPanel shared hosting (git pull / upload + `composer install`) |
| TLS | cPanel AutoSSL (Let's Encrypt) |

**Rationale:**
- *Single Laravel app*: integrated controllers + Blade for a brochure+blog site; no separate API or SPA to deploy. Admin is a protected route group, not a separate app.
- *Trix over TipTap*: Trix is a single pre-compiled JS/CSS asset bundled with Laravel — no Node build pipeline, keeping the server strictly PHP and deployment to file upload.
- *Eloquent + migrations*: schema versioning runs via `php artisan migrate` over cPanel Terminal/SSH or a cron-driven deploy step.
- *Local filesystem*: uploaded media lives under the cPanel account; `php artisan storage:link` exposes it. Flysystem keeps an S3/R2 swap one config change away.

## 4. Architecture

```
┌──────────────────────────────────────────────┐
│        cPanel shared hosting account           │
├──────────────────────────────────────────────┤
│  Apache + PHP-FPM (managed by host)            │
│  cPanel AutoSSL (automatic HTTPS)              │
├──────────────────────────────────────────────┤
│  Laravel 11 app                                │
│   • public site (Home/About/Blog/Contact)      │
│   • /admin (protected route group)             │
│   • Controllers + Blade views (CRUD)           │
│   • Webhook routes (payment providers)         │
│  Document root → app's public/                 │
│  App code + .env kept OUTSIDE web root         │
├──────────────────────────────────────────────┤
│  MySQL database (cPanel-provisioned)           │
│  storage/app/public → public/storage symlink   │
│  cPanel cron → php artisan schedule:run        │
└──────────────────────────────────────────────┘
   Optional later: S3 / Cloudflare R2 for media
```

Single deployable app. Internal admin mutations go through controllers + form requests; payment webhooks are public routes with signature verification. No second runtime, no container, no daemon.

## 5. Component design

### 5.1 Public site & section visibility
- Routes: `/{locale}`, `/{locale}/about`, `/{locale}/blog`, `/{locale}/blog/{slug}`, `/{locale}/contact`.
- A `SiteSetting` singleton (JSON columns) holds **section toggles** (`showBlog`, `showContact`, …) and **nav items**. Views read it at render; admin edits it. Hiding a section removes it from nav and returns 404/redirect on its route.

### 5.2 Blog
- Models: `Post` and `PostTranslation` (per-locale `title`, `slug`, `excerpt`, `body` HTML, SEO fields). Posts translated independently per locale.
- Editing via **Trix** in admin; body stored as **sanitized HTML** (HTMLPurifier on save).
- **Images**: uploaded through the editor → processed by **Intervention Image** (resize + optimize, generate responsive sizes) → persisted via the Filesystem interface → URL embedded in content. Served from `public/storage`.

### 5.3 Branding & style isolation (theming-ready)
- No theme model, block registry, layout editor, or upload/export **yet**. The runtime theming *feature* is deferred, but styling is **architected so it can be added later without a rewrite**.
- Per-clone visual identity comes from **editing Blade templates + CSS**.
- A small set of **admin-editable branding settings** in `SiteSetting`: site name, logo, primary/accent colors, base font. Applied as **CSS variables** in the layout so common tweaks need no code edit.

**Style isolation rules (the contract a future theme layer plugs into):**
1. **Single token layer.** All design tokens — colors, fonts, spacing scale, border radius, shadows — are declared as CSS custom properties in **one place** (`resources/css/tokens.css`, emitted into `:root`). This is the single source of truth.
2. **No raw values downstream.** Component/layout CSS and Blade templates reference **only** `var(--token-*)` — never hardcoded hex colors, font names, or pixel scales. No inline `style="..."` with literal values.
3. **Structure vs. skin split.** Layout/structural CSS (positioning, grid, sizing) is kept separate from skin CSS (color, typography, radius, shadow). Only the skin layer is token-driven, so re-skinning never touches structure.
4. **One application point.** Active token values are injected in **one** layout partial (`<style>` in the `<head>`, or a generated stylesheet) that overrides `:root` defaults with `SiteSetting` branding values. Today that override source is `SiteSetting`; later a `Theme` row supplies the same token keys to the same injection point — and an optional `[data-theme="<id>"]` scope enables per-theme overrides exactly as the original spec envisioned, with no template changes.
5. **Documented token names.** The token list (names + meaning + default) is documented so future themes and the admin token editor target a stable contract.

This makes the eventual theming work additive: add a `Theme` model, a token editor, and (optionally) package import — all feeding values into an injection point and CSS-variable contract that already exist.

### 5.4 Auth (admin)
- **Laravel Breeze/Fortify**, email + password (optional 2FA later). DB-backed sessions for immediate invalidation.
- `/admin/**` guarded by `auth` middleware + role check (`admin`). Public site needs no auth.

### 5.5 i18n
- **Laravel localization** with locale-prefixed routing (`/en`, `/ro`); default locale + negotiation middleware.
- **UI strings**: lang files per locale (`lang/en/*.php`, `lang/ro/*.php`).
- **Content**: per-locale DB rows (`PostTranslation`, page content) so editorial content is translated independently from UI chrome.

### 5.6 Payments
- A `PaymentProvider` interface (`createCheckout`, `verifyWebhook`, `handleWebhookEvent`, `getStatus`).
- Default adapter: **Stripe via Laravel Cashier** (best DX/docs; Stripe Tax handles Romanian VAT; cards + SEPA) — or **Mollie** (EU-domiciled). Final pick deferred to implementation.
- Webhooks handled in a public route with signature verification. Public payment config (enabled methods, product/price refs) editable in admin and stored in `PaymentSettings`; **secrets via `.env`, never the editable UI**.

### 5.7 Media storage
- Laravel **Filesystem** abstraction (Flysystem): `put`, `get`, `delete`, `url`.
- Default: **`local` disk** (`storage/app/public`, exposed via `storage:link`) with Intervention Image processing.
- Swap-in (no app-code change): **S3 / Cloudflare R2** via config + a Flysystem adapter.

## 6. Data model (initial)

- `User` — admin accounts.
- `Post`, `PostTranslation` — blog content per locale.
- `Media` — uploaded asset metadata (path/url, sizes, alt text per locale).
- `SiteSetting` — singleton row: section toggles, nav, contact info, **branding tokens** (name, logo, colors, font), locale settings (JSON columns).
- `PaymentSettings` — provider, public config, enabled methods (secrets in `.env`).

**Removed vs original:** `Theme` (and all token/layout/block data).

## 7. Deployment (cPanel shared hosting)

- **Code delivery**: cPanel **Git Version Control** (pull on the server) or upload. Run `composer install --no-dev` over **cPanel Terminal/SSH**; if SSH is unavailable, commit `vendor/` and upload.
- **Document root**: point the domain's document root at the app's **`public/`**; keep app code and `.env` **outside** the web root. (On hosts that force `public_html`, use the standard relocation: move `public/` contents into `public_html` and adjust paths in `index.php`.)
- **Database**: create the MySQL DB + user in cPanel; set credentials in `.env`. Schema via `php artisan migrate --force`.
- **Storage**: `php artisan storage:link` (or a manual symlink / copied directory if symlinks are restricted).
- **Scheduler**: a single cPanel cron job — `* * * * * /usr/local/bin/php /home/<acct>/app/artisan schedule:run >/dev/null 2>&1`.
- **Queues**: driver `sync` (inline) or `database` drained by a cron-driven `queue:work --stop-when-empty`. **No long-running daemon.**
- **Cache/session**: `file` or `database` driver (no Redis on shared hosting).
- **TLS**: cPanel **AutoSSL** (Let's Encrypt), managed by the host.
- **Backups**: cPanel account backups + a scheduled `mysqldump` cron to off-account storage; media directory included in account backups.
- **Secrets**: `.env` outside web root (DB creds, `APP_KEY`, payment keys).

**Host caveats to verify per account (call out in clone docs):**
- PHP version selectable via cPanel **MultiPHP Manager** (need 8.2+).
- **GD** present by default; confirm **Imagick** if higher-quality processing is wanted (Intervention falls back to GD).
- **Composer / SSH** availability; if absent, build `vendor/` locally and upload.
- Symlink support for `storage:link`.

## 8. Out of scope (YAGNI)

- All theming *features* (switch/customize/upload themes, block registry, declarative layouts) — deferred, but the style-token layer in §5.3 is built now so theming can be added later without restructuring CSS or templates.
- Multi-tenant hosting (each site is its own deployment).
- Separate API service / microservices / SPA admin.
- Real-time/collaborative editing.
- Managed object storage on day one.
- **Docker/containers and any long-running process model** (incompatible with shared hosting).
- Node.js anywhere in the toolchain (Trix + plain CSS keep it Node-free).

## 9. Build order (high level)

1. Laravel 11 skeleton; `.env`, MySQL connection, base Blade layout + plain CSS. **Establish the style-isolation layer first** (§5.3): `tokens.css` with all design tokens as `:root` CSS variables, a structure/skin CSS split, and a single token-injection partial in the layout head.
2. Locale-prefixed routing (`/en`, `/ro`) + public pages (Home/About/Contact) reading `SiteSetting`.
3. Migrations + Eloquent models; Breeze/Fortify auth + `/admin` shell.
4. Blog: models, Trix editor, image upload via Filesystem + Intervention Image, HTML sanitization, public blog rendering.
5. Branding/site settings admin (name, logo, colors, font, contact, nav) — values feed the token-injection partial from step 1, overriding `:root` defaults.
6. Section show/hide in admin (`SiteSetting`).
7. Payments behind `PaymentProvider`; choose Stripe (Cashier)/Mollie; webhook route; admin payment settings.
8. cPanel deployment: document-root setup, migrations, `storage:link`, scheduler cron, AutoSSL, `mysqldump` backup cron, clone/hardening docs.

## 10. References

- Laravel deployment — https://laravel.com/docs/11.x/deployment
- Laravel localization — https://laravel.com/docs/11.x/localization
- Laravel Cashier (Stripe) — https://laravel.com/docs/11.x/billing
- Trix editor — https://trix-editor.org/
- Intervention Image — https://image.intervention.io/
- HTMLPurifier for Laravel (mews/purifier) — https://github.com/mewebstudio/Purifier
- Deploying Laravel on cPanel shared hosting — https://laravel.com/docs/11.x/deployment#server-requirements
- Payments in Romania (Stripe) — https://stripe.com/resources/more/payments-in-romania
- Mollie for Laravel — https://github.com/mollie/laravel-mollie
