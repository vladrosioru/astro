# Reusable Site Template

A reusable, multilingual marketing-site + blog template built on **Laravel 11**, designed to deploy to **cPanel shared hosting** with **no Node build step**. Theming is driven entirely by a CSS **design-token contract**, so the whole look can be re-skinned (or swapped per client) without touching markup.

---

## Stack & constraints

| Area | Choice |
|---|---|
| Framework | Laravel 11 (PHP 8.2+; developed on 8.3) |
| Database | MySQL in production; SQLite for local dev & tests |
| Front-end build | **None** — plain CSS + vanilla JS served from `public/`. No Vite/Tailwind/npm. |
| Rich text | CKEditor 5 (self-hosted GPL build) + HTMLPurifier sanitisation |
| Images | `intervention/image` v4 |
| Deploy target | cPanel shared hosting (no shell build, no Node) |
| i18n | `en`, `ro` (locale-prefixed routes) |

**Why Node-free:** the deploy target is shared hosting without a build pipeline. All styling ships as static files under `public/`; fonts are self-hosted WOFF2. There is intentionally no `package.json`/Vite/Tailwind.

---

## Quick start (local)

Requires PHP 8.2+ and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # local dev DB (gitignored)
php artisan migrate
php artisan app:create-admin          # create an admin login
php artisan app:apply-theme solarsystem   # activate the active theme
php artisan serve                     # http://127.0.0.1:8000
```

Run the test suite (uses a separate in-memory SQLite DB, see `phpunit.xml`):

```bash
php artisan test
```

> **Windows toolchain note:** PHP 8.3 + Composer were installed via winget under
> `…/WinGet/Packages/PHP.PHP.8.3_…`. That dir is not always on PATH — prepend it before running `php`/`composer` if the command isn't found.

---

## Routing & request flow

Defined in [`routes/web.php`](routes/web.php).

- `/` → redirects to the default locale (`/en`).
- `/{locale}` group (`locale` constrained to `en|ro`, `setlocale` middleware):
  - `/{locale}` → Home (`PageController@home`)
  - `/{locale}/about`, `/{locale}/contact`
  - `/{locale}/blog`, `/{locale}/blog/{slug}`
- `/admin/login`, `/admin/logout` — session auth (`Admin\AuthController`).
- `/admin/*` (`admin` middleware) — dashboard, `posts` resource (no `show`), `attachments` upload.

Middleware aliases are registered in [`bootstrap/app.php`](bootstrap/app.php):
- `setlocale` → `App\Http\Middleware\SetLocale` (sets `app()->setLocale()` from the route prefix)
- `admin` → `App\Http\Middleware\EnsureAdmin` (gates `/admin` on the `is_admin` flag)

---

## Data model

- **`SiteSetting`** ([`app/Models/SiteSetting.php`](app/Models/SiteSetting.php)) — a **singleton** row (`id = 1`, via `SiteSetting::current()`). JSON columns:
  - `sections` — visibility toggles (`about`/`blog`/`contact`) read by `sectionVisible()`.
  - `nav`, `contact`, `locales` — site config.
  - `branding` — **token overrides** (the active theme's values; see Theming).
  - `hero` — Home hero content (`headline`, `subhead`, `cta_label`, `cta_url`, `eyebrow`, `cta2_label`, `cta2_url`), defaulted by `heroDefaults()`.
- **`Post`** + **`PostTranslation`** — a post has one translation per locale (title, slug, excerpt, body, seo_title). Blog reads the translation for the active locale.
- **`User`** — `is_admin` boolean gates the admin area.

---

## Theming: the design-token contract

This is the core of the template. **No markup contains raw colours or fonts** — everything resolves through CSS custom properties.

```
config/tokens.php (light defaults)
        │   merged with ▼
SiteSetting.branding (active theme overrides)
        │   emitted as :root { --token: value } by ▼
resources/views/partials/tokens.blade.php   (inline <style> in <head>)
        │   consumed by ▼
public/css/structure.css   (layout only — no colours)
public/css/cosmos.css      (shared starfield backdrop, behind every page)
public/css/skin.css        (appearance only — all var(--token))
public/css/hero-solarsystem.css  (animated Home stage, .stage-scoped)
```

- **`config/tokens.php`** — the single source of truth for token *names* and their **light defaults**. Never reference a raw literal in CSS/Blade; always `var(--token)`.
- **`SiteSetting.branding`** — per-install overrides of those tokens. This is the same `:root` override path a future admin "Theme" record would use.
- **Named themes** live in [`config/themes.php`](config/themes.php) and are applied to `branding` with:

  ```bash
  php artisan app:apply-theme {name}    # e.g. solarsystem, mystik
  php artisan app:apply-theme default   # clears branding → back to light defaults
  ```

### Available themes

| Theme | Look | Notes |
|---|---|---|
| **`solarsystem`** | Dark celestial; animated orbiting-planets Home hero | The current active theme. See below. |
| `mystik` | Dark/gold astrology | Token set retained; not currently active. |

### The Solar System theme

- **Palette/fonts:** `themes.solarsystem` (dark `#05060c` bg, icy `#9dc1e6` accent; `Jost` body / `Cormorant Garamond` headings / `Cinzel` chrome).
- **Shared cosmos backdrop:** the deep-space gradient, drifting starfield, nebula glow and twinkling stars live in one fixed, full-viewport layer rendered behind **every** page — [`public/css/cosmos.css`](public/css/cosmos.css) + [`resources/views/partials/cosmos.blade.php`](resources/views/partials/cosmos.blade.php) (included once by the layout). It has **no solar system and no mouse parallax / 3D movement** — gentle ambient drift only.
- **Home hero** is a full-viewport `.stage` that sits transparently over the shared backdrop:
  - [`public/css/hero-solarsystem.css`](public/css/hero-solarsystem.css) — the five orbiting planets + sun, vignette, hero copy. **Every selector is scoped under `.stage`** so it never affects inner pages; `@keyframes` are global. (The starfield/nebula/twinkle art now lives in `cosmos.css`.)
  - [`public/js/solarsystem.js`](public/js/solarsystem.js) — generates twinkling stars (into `.twinkle`) and binds mouse parallax to the solar system. **Self-guards** (`if (!.stage) return`), so the parallax is inert on inner pages and safe to load site-wide with `defer`.
  - Markup is [`resources/views/partials/hero.blade.php`](resources/views/partials/hero.blade.php); **all copy comes from `SiteSetting.hero`** (no hardcoded text).
- **Inner pages** (About/Contact/Blog) show the same shared cosmos backdrop (no solar system, no parallax) and inherit the dark token skin — no orbit animation.
- **Nav** is one data-driven partial; a `page-home` body class makes it a transparent overlay on Home and a sticky translucent bar elsewhere. The menu is centered as **2 links · logo · 2 links**: the brand is the image [`public/img/logo-nav.png`](public/img/logo-nav.png) (a transparent PNG keyed/cropped from `res/logo.jpeg`, sized to 3× the nav-link letters) rather than a text wordmark.

> Design docs: [`docs/superpowers/specs/2026-06-26-solarsystem-theme-design.md`](docs/superpowers/specs/2026-06-26-solarsystem-theme-design.md) and the matching plan in `docs/superpowers/plans/`.

### Building a new theme

[`docs/theme-style-map.json`](docs/theme-style-map.json) is a complete machine-readable map of the styling system — every token (role, type, per-theme values, and which selectors consume it), an element→token map per component, and the animated stage's tokenized vs. hardcoded literals. It's written to be handed to an agent to author a new, drop-in-replaceable theme. Start at its `meta.how_to_create_a_theme`.

---

## Fonts (self-hosted)

All fonts are self-hosted WOFF2 in [`public/fonts/`](public/fonts/) and declared in [`public/css/fonts.css`](public/css/fonts.css) — **no Google Fonts CDN** (keeps the site offline-capable and Node-free):

- **Jost** (300/400/500/600) — body/UI
- **Cormorant Garamond** (400/500 + 400 italic) — display/headings
- **Cinzel** (400/600/700) — brand/chrome
- **EB Garamond** (400/700 + italic) — used by the `mystik` theme

---

## CSS / asset layering

`layouts/app.blade.php` loads, in order: `fonts.css` → inline `:root` tokens (`partials/tokens`) → `structure.css` → `cosmos.css` → `skin.css` → `hero-solarsystem.css` → `@stack('head')`, then renders the shared `partials/cosmos` backdrop and loads `solarsystem.js` (`defer`). Page-specific stylesheets are pushed via `@push('head')` (e.g. the blog article loads `ckeditor5.css` + `article.css`). Keep the **structure (layout) vs. skin (appearance)** split: positional rules go in `structure.css`, anything visual goes in `skin.css` and must use tokens.

---

## Blog & rich text

- Public blog: list + single post, per-locale via `PostTranslation`.
- Admin CRUD under `/admin/posts`.
- **CKEditor 5** is **self-hosted** at [`public/vendor/ckeditor/`](public/vendor/ckeditor/) using the npm package's `dist/browser/ckeditor5.umd.js` (distribution channel `"sh"`, valid with the GPL key). **Do not use the CDN/"cloud" build** — the GPL key is invalid there. On upgrade, re-pull the UMD from the npm tarball.
- Submitted HTML is sanitised through a dedicated **`blog` HTMLPurifier profile** (`mews/purifier`).
- Image uploads go through `Admin\AttachmentController` (`intervention/image` v4) and are stored as **root-relative** `/storage/...` URLs. Public image-alignment CSS lives in `skin.css`.
- **Blog card image.** Each post can have a representative card image, stored on `posts.featured_image` (one image per post, shared across locales). Admins upload it via the **Card image** field in the post editor; on save it is validated (`image`, max 8 MB), square-cropped server-side to 1200×1200 (Intervention Image, centered cover crop), stored on the public disk as a `Media` row, and saved as a root-relative URL. The blog listing (`resources/views/blog/index.blade.php`) renders posts as a `.blog-grid` of cards — a square image on top and title + excerpt below; posts without an image render a text-only card.
- **Article display fidelity:** the single-post view ([`blog/show.blade.php`](resources/views/blog/show.blade.php)) renders the body inside a `.ck-content` wrapper on a white "paper" sheet ([`public/css/article.css`](public/css/article.css)) and loads the editor's own `ckeditor5.css`, so a published article looks **exactly as authored in CKEditor**. The cosmic theme is reset out of `.ck-content`; only the article `<h1>` title above the sheet stays themed like the rest of the site.

---

## Artisan commands

| Command | Purpose |
|---|---|
| `php artisan app:create-admin` | Create an admin user (`is_admin = true`). |
| `php artisan app:apply-theme {name}` | Apply a named theme from `config/themes.php` to `SiteSetting.branding`; `default` clears it. |

---

## Project layout

```
app/
  Console/Commands/      ApplyTheme, CreateAdmin
  Http/Controllers/      PageController, BlogController, Admin/*
  Http/Middleware/       SetLocale, EnsureAdmin
  Models/                SiteSetting, Post, PostTranslation, User
config/
  tokens.php             token names + light defaults (source of truth)
  themes.php             named token sets (solarsystem, mystik)
public/
  css/                   fonts, structure, cosmos, skin, hero-solarsystem, article
  js/                    solarsystem.js
  fonts/                 self-hosted WOFF2
  vendor/ckeditor/       self-hosted CKEditor 5 (GPL "sh" build)
resources/views/
  layouts/app.blade.php  master layout
  partials/              nav, cosmos (shared backdrop), hero (the .stage), tokens
  pages/                 home, about, contact
  blog/                  index, show
  admin/                 dashboard, login, posts/*
routes/web.php
docs/
  superpowers/specs/     design specs
  superpowers/plans/     implementation plans
  BACKLOG.md             follow-ups & ideas
tests/                   Feature + Unit (PHPUnit, in-memory SQLite)
```

---

## Testing

PHPUnit, configured by [`phpunit.xml`](phpunit.xml) to run against an **in-memory SQLite** DB (separate from the dev DB — a passing suite does not imply the dev DB is migrated). Run with `php artisan test`. Coverage spans page rendering, locale routing, section visibility, theming/token emission, the hero, blog publishing, admin auth, and the CSS/JS asset contracts.

---

## Deployment (cPanel)

Target is shared hosting with no build step:

1. Upload the app; point the web root at `public/`.
2. `composer install --no-dev --optimize-autoloader`.
3. Configure `.env` for MySQL; `APP_DEBUG=false`; `php artisan key:generate` if needed.
4. `php artisan migrate --force`.
5. `php artisan storage:link` (or replicate the symlink) for uploaded images.
6. Apply the theme: `php artisan app:apply-theme solarsystem`.

No `npm`/Vite step is required or expected. (A full deployment plan is in `docs/superpowers/plans/2026-06-25-…-deployment.md`.)

---

## Conventions

- **Never** put raw colours/fonts in markup or CSS — add a token to `config/tokens.php` and reference `var(--token)`.
- Keep `structure.css` colour-free and `skin.css` token-driven.
- Hero/site copy is data-driven via `SiteSetting`; don't hardcode marketing text in Blade.
- Stay Node-free: no build tooling, CDN runtime deps, or JS libraries in the front end.
