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
  - `/{locale}/about`, `/{locale}/services`, `/{locale}/contact`
  - `POST /{locale}/contact` (`contact.submit`, `throttle:5,1`) — validates and mails the contact form (`App\Mail\ContactMessage`); a honeypot field plus a render-timestamp trap silently no-op bot submissions
  - `/{locale}/journal`, `/{locale}/journal/{slug}` — the blog feature (`BlogController`), presented as **Journal**; route names stay `blog.*`. Legacy `/{locale}/blog`, `/{locale}/blog/{slug}`, `/{locale}/articles` and `/{locale}/articles/{slug}` **301-redirect** to the `/journal` equivalents.
- `/admin/login`, `/admin/logout` — session auth (`Admin\AuthController`).
- `/admin/*` (`admin` middleware) — dashboard, `posts` resource (no `show`), `attachments` upload, and **Themes** (`GET /admin/themes` list + `PATCH /admin/themes` apply).

Middleware aliases are registered in [`bootstrap/app.php`](bootstrap/app.php):
- `setlocale` → `App\Http\Middleware\SetLocale` (sets `app()->setLocale()` from the route prefix)
- `admin` → `App\Http\Middleware\EnsureAdmin` (gates `/admin` on the `is_admin` flag)

---

## Data model

- **`SiteSetting`** ([`app/Models/SiteSetting.php`](app/Models/SiteSetting.php)) — a **singleton** row (`id = 1`, via `SiteSetting::current()`). JSON columns:
  - `sections` — visibility toggles (`about`/`blog`/`services`/`contact`) read by `sectionVisible()`. `blog` is the internal key for the Journal feature.
  - `nav`, `contact`, `locales` — site config.
  - `branding` — optional **per-install token overrides** layered on top of the active theme (usually empty; see Theming).
  - `theme` — the active theme pointer (a `theme_<name>` folder under `public/themes/`; defaults to `solarsystem`).
  - `hero` — Home hero content (`headline`, `subhead`, `cta_label`, `cta_url`, `eyebrow`, `cta2_label`, `cta2_url`), defaulted by `heroDefaults()`. `eyebrow` is the ASTROTHERAPIA wordmark, now rendered by the nav under the logo (see Nav) rather than inside the hero.
- **`Post`** + **`PostTranslation`** — a post has one translation per locale (title, slug, excerpt, body, seo_title). Blog reads the translation for the active locale.
- **`User`** — `is_admin` boolean gates the admin area.

---

## Theming: self-contained theme packages

This is the core of the template. A **theme is a self-contained folder** under
`public/themes/theme_<name>/` holding everything visual it needs — its own CSS, JS,
self-hosted fonts, the Blade partials it renders, and a `theme.json` manifest. The
active theme is a single pointer (`SiteSetting.theme`); the app loads everything else
from that folder. **No markup contains raw colours or fonts** — appearance still flows
through CSS custom properties.

```
SiteSetting.theme  ("solarsystem")
        │   resolved by ▼
App\Services\ThemeManager  → public/themes/theme_solarsystem/theme.json
        │   merges tokens: config/tokens.php defaults ← theme.json values ← SiteSetting.branding
        │   emitted as :root { --token: value } by ▼
resources/views/partials/tokens.blade.php   (inline <style> in <head>)
        │   the layout then loads, from the manifest ▼
public/themes/theme_solarsystem/css/*.css   (assets.css, in order)
public/themes/theme_solarsystem/js/*.js     (assets.js)
public/themes/theme_solarsystem/views/*.blade.php   (theme::hero, theme::cosmos)
```

- **`config/tokens.php`** — the registry of token *names* and **safe light defaults**, so a theme that omits a token still renders. Never reference a raw literal in CSS/Blade; always `var(--token)`.
- **`theme.json`** — per theme: metadata, all tokens (type/role/value), fonts, the ordered `assets.css` / `assets.js`, and the `views` slots (`hero`, `cosmos`, …). It is both the app's loader manifest **and** the portable spec you hand another app/agent to author a compatible theme. Validated against [`public/themes/theme.schema.json`](public/themes/theme.schema.json) (every field documented inline) by `tests/Unit/ThemeJsonContractTest.php`.
- **Authoring a new theme** — [`public/themes/AUTHORING.md`](public/themes/AUTHORING.md) is the full guide: the CSS class vocabulary a theme must style (nav, hero, blog cards, article images), the token table, the structure/skin layering convention, fonts, and a validation checklist.
- **`App\Services\ThemeManager`** (singleton `app('theme.manager')`) — resolves the active theme (falling back to `config('theme.fallback')` if its folder is missing), merges tokens, and exposes `cssUrls()` / `jsAssets()` / `viewsPath()` / `available()`.
- **`App\Providers\ThemeServiceProvider`** — registers the active theme's `views/` under the `theme::` Blade namespace and shares the manifest to all views.
- **`SiteSetting.branding`** — optional *per-install* token overrides layered on top of the theme's values (usually empty). Because it overrides the active theme's tokens, it is **per theme**: switching to a different theme clears it (via `SiteSetting::switchTheme()`) so the previous theme's palette can't bleed into the new one; re-applying the active theme keeps it.
- **Selecting a theme:** Admin → **Themes** (`/admin/themes`), or the CLI:

  ```bash
  php artisan app:apply-theme {name}   # e.g. solarsystem, default — sets SiteSetting.theme
  ```

  Both validate the name against the installed `theme_*` folders, then clear the view cache. A real switch (to a different theme) also resets `SiteSetting.branding`.

### Available themes

| Theme | Folder | Look |
|---|---|---|
| **`solarsystem`** | `public/themes/theme_solarsystem/` | Dark celestial; animated orbiting-planets Home hero + shared cosmos backdrop. The shipped/active theme. |
| `default` | `public/themes/theme_default/` | Light base theme (system fonts, static hero, no cosmic art). |

> `mystik` (the former dark/gold token set) has not yet been repackaged as a folder — a follow-up.

### The Solar System theme

- **Palette/fonts:** dark `#05060c` bg, icy `#9dc1e6` accent; `Jost` body / `Cormorant Garamond` headings / `Cinzel` chrome — all in `theme_solarsystem/theme.json`.
- **Shared cosmos backdrop:** the deep-space gradient, drifting starfield, nebula glow and twinkling stars live in one fixed, full-viewport layer rendered behind **every** page — `theme_solarsystem/css/cosmos.css` + `theme_solarsystem/views/cosmos.blade.php` (pulled in by the layout as `@includeIf('theme::cosmos')`). It has **no solar system and no mouse parallax / 3D movement** — gentle ambient drift only.
- **Home hero** is a full-viewport `.stage` that sits transparently over the shared backdrop:
  - `theme_solarsystem/css/hero.css` — the five orbiting planets + sun, vignette, hero copy. **Every selector is scoped under `.stage`** so it never affects inner pages; `@keyframes` are global.
  - `theme_solarsystem/js/solarsystem.js` — generates twinkling stars (into `.twinkle`) and binds mouse parallax to the solar system. **Self-guards** (`if (!.stage) return`), so the parallax is inert on inner pages and safe to load site-wide with `defer`.
  - Markup is `theme_solarsystem/views/hero.blade.php` (rendered on Home as `@includeIf('theme::hero')`); **all copy comes from `SiteSetting.hero`** (no hardcoded text).
- **Inner pages** (About/Journal/Services/Contact) show the same shared cosmos backdrop and inherit the dark token skin — no orbit animation.
- **Nav** is one app-level data-driven partial ([`resources/views/partials/nav.blade.php`](resources/views/partials/nav.blade.php)); a `page-home` body class makes it an absolute overlay on Home and a sticky bar elsewhere. The ribbon is **transparent on every page** (Home and inner pages alike) so the cosmos shows straight through it — any future page inherits this. The menu is centered as **2 links · brand · 2 links** (`About · Journal` | brand | `Services · Contact`). The centered `.nav-brand` stacks the logo image [`public/img/logo-nav.png`](public/img/logo-nav.png) over the `.nav-eyebrow` ASTROTHERAPIA wordmark (from `SiteSetting.hero.eyebrow`); both link Home.
- **Phone nav (≤720px)** collapses the two link groups behind a "≡" hamburger on the right, leaving only the brand visible in the closed bar. It's a pure-CSS "checkbox hack" — no JS: `#nav-toggle` (a checkbox placed *before* `<nav>`, not inside it) plus two `<label>`s. Tapping the trigger checks the input, and `#nav-toggle:checked ~ nav` — a plain sibling combinator, not `:has()`, for maximum browser compatibility — overlays a fixed panel listing About/Journal/Services/Contact in one column under the brand, dimmed by a `.nav-scrim` backdrop that also closes the menu when tapped.

> Design docs: [`docs/superpowers/specs/2026-06-27-theme-packages-design.md`](docs/superpowers/specs/2026-06-27-theme-packages-design.md) and the matching plan in `docs/superpowers/plans/`.

### Building a new theme

Drop a `public/themes/theme_<name>/` folder containing a `theme.json` that validates
against [`public/themes/theme.schema.json`](public/themes/theme.schema.json): declare
every token (type/role/value), list your `assets.css` (in cascade order) and any
`assets.js`, ship your fonts, and provide the Blade partials named in `views`
(at least `hero`). Then select it in Admin → Themes or
`php artisan app:apply-theme <name>`. An existing `theme.json` (e.g.
`theme_solarsystem`'s) doubles as the worked example/spec to hand an agent.

---

## Fonts (self-hosted)

Fonts are self-hosted WOFF2 **inside each theme package** (e.g. `public/themes/theme_solarsystem/fonts/`) and declared in that theme's own `css/fonts.css` (listed first in its `assets.css`) — **no Google Fonts CDN** (keeps the site offline-capable and Node-free). The Solar System theme ships:

- **Jost** (300/400/500/600) — body/UI
- **Cormorant Garamond** (400/500 + 400 italic) — display/headings
- **Cinzel** (400/600/700) — brand/chrome

> The retired `mystik` token set used **EB Garamond**; those WOFF2 files still live in `public/fonts/` pending mystik's repackaging.

---

## CSS / asset layering

`layouts/app.blade.php` loads, in order: inline `:root` tokens (`partials/tokens`, sourced from `ThemeManager::tokens()`) → the active theme's `assets.css` looped from the manifest (`ThemeManager::cssUrls()`, in declared order — typically `fonts.css` → `structure.css` → `cosmos.css` → `skin.css` → `hero.css`) → `@stack('head')`, then renders the theme's backdrop via `@includeIf('theme::cosmos')` and loops the manifest's `assets.js` (`ThemeManager::jsAssets()`, honouring each item's `defer`/`async`). Page-specific stylesheets are still pushed via `@push('head')` (the blog article loads `ckeditor5.css` + the app-level `public/css/article.css`). Within a theme, keep the **structure (layout) vs. skin (appearance)** split: positional rules in `structure.css`, anything visual in `skin.css` using tokens.

---

## Blog & rich text

- Public blog: list + single post, per-locale via `PostTranslation`.
- Admin CRUD under `/admin/posts`.
- **CKEditor 5** is **self-hosted** at [`public/vendor/ckeditor/`](public/vendor/ckeditor/) using the npm package's `dist/browser/ckeditor5.umd.js` (distribution channel `"sh"`, valid with the GPL key). **Do not use the CDN/"cloud" build** — the GPL key is invalid there. On upgrade, re-pull the UMD from the npm tarball.
- Submitted HTML is sanitised through a dedicated **`blog` HTMLPurifier profile** (`mews/purifier`).
- Image uploads go through `Admin\AttachmentController` (`intervention/image` v4) and are stored as **root-relative** `/storage/...` URLs. Public image-alignment CSS lives in `skin.css`.
- **Blog card image.** Each post can have a representative card image, stored on `posts.featured_image` (one image per post, shared across locales). Admins upload it via the **Card image** field in the post editor; on save it is validated (`image`, max 8 MB), square-cropped server-side to 1200×1200 (Intervention Image, centered cover crop), stored on the public disk and recorded as a `Media` row, and saved as a root-relative URL. The blog listing (`resources/views/blog/index.blade.php`) opens with a `.journal-hero` photo band ("Cosmic Journal"), sized so the band is always 3x the title's own text height (padding is a multiple of the title's font-size, not a fixed height), then renders posts as a single-column `.blog-grid.blog-grid--journal` stack of cards — a `.card__meta` block (publish date, title, and the post's admin-entered **Subtitle** field, `post_translations.subtitle`, italic + bold in the article body's font — omitted entirely if left blank) stacked **above** the square card image (not overlaid on it, so the image always renders fully), then below the image an auto-generated teaser (first sentence + first few words of the next, ending in a linked `[...]`) and a "Read more" button; posts without an image fall back to a text-only card with date/title/subtitle/teaser/button all in one panel, and the newest post gets a stronger `.card--first` border.
- **Article display:** the single-post view ([`blog/show.blade.php`](resources/views/blog/show.blade.php)) opens with the same `.journal-hero` photo band, overlaid with the article's own title; below it, in order: the post's own featured image (if set), the body inside a `.ck-content` wrapper on an `.article-paper` panel, an `.article-footer` (publish date + working Facebook/X share links — real `sharer.php`/`x.com/intent/tweet` URLs for the current article, opened in a small popup window via [`public/js/article-share.js`](public/js/article-share.js) rather than a full new tab, same as the reference theme; Instagram has no public share-link web intent, so it stays a `#` placeholder, matching the reference theme which also has no Instagram share button — only FB/X/Tumblr), and an `.article-adjacent` previous/next nav wired to the chronologically adjacent published posts in the same locale, omitting either side when there isn't one (oldest/newest post). All of this — including `.article-paper`/`.ck-content` — is styled by the app-level [`public/css/article.css`](public/css/article.css), fully `var(--token)`-driven so it matches whichever theme is active (same panel look as a Journal card); it no longer reproduces the CKEditor editing surface's literal black-on-white colors. It still loads the editor's own `ckeditor5.css` so structural rich-text rules (lists, tables, spacing) match.

---

## Artisan commands

| Command | Purpose |
|---|---|
| `php artisan app:create-admin` | Create an admin user (`is_admin = true`). |
| `php artisan app:apply-theme {name}` | Set the active theme pointer (`SiteSetting.theme`) to an installed `theme_<name>` folder, then clear the view cache. Validates against the installed themes; a switch to a different theme also resets `SiteSetting.branding`. |

---

## Project layout

```
app/
  Console/Commands/      ApplyTheme, CreateAdmin
  Http/Controllers/      PageController, BlogController, Admin/* (incl. ThemeController)
  Http/Middleware/       SetLocale, EnsureAdmin
  Models/                SiteSetting, Post, PostTranslation, User
  Providers/             ThemeServiceProvider (registers the theme:: namespace)
  Services/              ThemeManager (resolves active theme, merges tokens, lists themes)
config/
  tokens.php             token names + light defaults (fallback source of truth)
  theme.php              theme.fallback + theme.path
public/
  themes/                self-contained theme packages + theme.schema.json
    theme_solarsystem/   theme.json, css/, js/, fonts/, views/, .htaccess
    theme_default/        theme.json, css/, views/, .htaccess (light base)
  css/article.css        app-level blog "paper" (not themed)
  vendor/ckeditor/       self-hosted CKEditor 5 (GPL "sh" build)
  fonts/                 retired mystik WOFF2 (pending repackage)
resources/views/
  layouts/app.blade.php  master layout (loads theme assets from the manifest)
  partials/              nav (app-level), tokens (:root emitter)
  pages/                 home (renders theme::hero), about, services, contact
  blog/                  index, show  (the Journal feature; served at /journal)
  admin/                 dashboard, login, posts/*, themes/index
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
