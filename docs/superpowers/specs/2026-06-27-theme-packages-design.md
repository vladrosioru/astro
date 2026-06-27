# Theme Packages — Design Spec

**Date:** 2026-06-27
**Status:** Approved (pending written-spec review)
**Topic:** Self-contained, folder-based theme packages with an admin picker and a portable `theme.json` map.

---

## 1. Goal & motivation

Today a "theme" is just a token-override array in [`config/themes.php`](../../../config/themes.php), copied into `SiteSetting.branding` by `php artisan app:apply-theme`. The actual visual assets are scattered and shared: layout/skin CSS in `public/css/`, the cosmic hero art hardcoded in `hero-solarsystem.css` + `partials/hero.blade.php` + `public/js/solarsystem.js`, fonts in `public/fonts/`. A theme cannot ship a genuinely different look — only recolor the existing one.

We want each theme to be a **self-contained folder** named `theme_<name>` that holds *everything visual it needs* — tokens, CSS (including its own structural layout), JS, fonts, and its Blade markup — plus a single `theme.json` that the app reads to load the theme **and** that doubles as a portable spec for authoring compatible themes in other apps. An admin picker selects among the installed theme folders; the site auto-loads the chosen one.

### Decisions captured during brainstorming

- **Theme scope:** fully self-contained — each folder carries *all* visual CSS including its own `structure.css`. Layout fixes must be re-applied per theme (accepted trade-off; this is a re-skin-per-client template).
- **Markup ownership:** themes own their **visual Blade partials** (hero, cosmos, …), not the routing/content skeleton.
- **The map:** one `theme.json` per theme — simultaneously the app's loader config and the portable authoring spec. No separate human doc, no global style map to keep in sync.

---

## 2. Architecture: app shell vs. theme package

A hard split between the **app shell** (stable integration points) and the **theme package** (everything visual). "Themes own their markup" applies to *visual partials*, not the page/content skeleton — otherwise routing and content injection would break per theme.

### App owns (stable contract)

- **`resources/views/layouts/app.blade.php`** — the integration shell. Renders the nav slot, `@yield`s page content, and **loads theme assets + theme partials from the manifest**. Stops hardcoding the CSS/JS list and load order.
- **Page content views** — `pages/home`, `pages/about`, `pages/contact`, `blog/index`, `blog/show`. Semantic markup, styled entirely by theme CSS.
- **[`config/tokens.php`](../../../config/tokens.php)** — canonical registry of token *names* + safe fallback defaults, so a theme that omits a token still renders.
- **`resources/views/partials/tokens.blade.php`** — emits `:root { --token: value }`; its source becomes `ThemeManager::tokens()`.
- **Article paper** — `article.css` + the self-hosted `ckeditor5.css`, loaded via `@push('head')` on the single-post page. Deliberately **not** themed (mirrors the CKEditor editing surface); stays app-level regardless of active theme.

### Theme owns (the `theme_<name>` folder)

- `theme.json` — manifest + portable map (§3).
- All CSS: its own `structure.css`, `skin.css`, plus any art (`cosmos.css`, `hero.css`) and `fonts.css`.
- All JS (e.g. `solarsystem.js`).
- Self-hosted fonts (WOFF2).
- The visual Blade partials it declares (at minimum `hero`, optionally `cosmos`, `nav`), resolved via a `theme::` view namespace.
- Optional `screenshot.png` for the admin picker.

### Folder location & layout

Themes live under `public/` so CSS/JS/fonts are served statically (Node-free / cPanel-friendly). Server-side files in the folder are protected by `.htaccess`.

```
public/themes/
├── theme.schema.json          # JSON Schema; validates every theme.json
├── theme_solarsystem/
│   ├── theme.json
│   ├── .htaccess              # deny *.blade.php and theme.json; allow css/js/fonts
│   ├── css/    structure.css skin.css cosmos.css hero.css fonts.css
│   ├── js/     solarsystem.js
│   ├── fonts/  *.woff2
│   ├── views/  hero.blade.php  cosmos.blade.php
│   └── screenshot.png         # optional
└── theme_default/
    ├── theme.json
    ├── .htaccess
    ├── css/    structure.css skin.css
    ├── views/  hero.blade.php
    └── ...
```

`.htaccess` (Apache, the cPanel target) denies direct web access to `*.blade.php` and `theme.json` (prevents source disclosure) while serving `css/`, `js/`, `fonts/`, and images normally.

### Request flow

```
SiteSetting.current()->theme  ("solarsystem")
   → ThemeManager resolves public/themes/theme_solarsystem/
   → reads & caches theme.json
   → merges tokens: config('tokens.defaults') ← theme.json values ← SiteSetting.branding
   → partials/tokens.blade.php emits :root
   → registers theme views/ under the "theme::" namespace
   → layout loops manifest css[]/js[] (in order) and @includeIf's theme:: partials
```

The 3-layer token merge keeps the existing `defaults ← branding` emission path intact; the theme folder slots in as the **middle** layer. `branding` now means only *optional per-install overrides on top of the theme* (usually empty).

---

## 3. The `theme.json` schema (manifest + portable map)

One file, two jobs: the app reads it to load the theme; another app/agent reads it as the complete spec to author a compatible theme.

```json
{
  "$schema": "../theme.schema.json",
  "name": "solarsystem",
  "title": "Solar System",
  "description": "Dark celestial theme with an animated orbiting-planets hero.",
  "version": "1.0.0",
  "screenshot": "screenshot.png",

  "tokens": {
    "color-primary": { "type": "color",      "role": "Links, primary button, eyebrow, scroll cue", "value": "#9dc1e6" },
    "color-bg":      { "type": "color",      "role": "Page background",                              "value": "#05060c" },
    "font-base":     { "type": "font-stack", "role": "Body/UI text",      "value": "'Jost', system-ui, sans-serif" },
    "nav-height":    { "type": "length",     "role": "Nav bar min height", "value": "4.5rem" }
  },

  "fonts": [
    { "family": "Jost", "weights": [300,400,500,600], "style": "normal",
      "files": ["fonts/jost-300.woff2","fonts/jost-400.woff2","fonts/jost-500.woff2","fonts/jost-600.woff2"],
      "used_for": "font-base" }
  ],

  "assets": {
    "css": ["css/fonts.css", "css/structure.css", "css/cosmos.css", "css/skin.css", "css/hero.css"],
    "js":  [{ "src": "js/solarsystem.js", "defer": true }]
  },

  "views": {
    "namespace": "theme",
    "hero":   "hero",
    "cosmos": "cosmos"
  },

  "page_home_class": "page-home"
}
```

### Schema rules

- **`tokens`** carries `type` + `role` + `value` per key. `value` drives the live `:root`; `type` + `role` make it a self-documenting authoring spec (subsumes the old `docs/theme-style-map.json` tokens block). Keys should cover every token in `config/tokens.php`; omitted tokens fall back to the canonical default.
- **`assets.css`** is an **explicit ordered list** — the load order *is* the manifest, so each theme controls its own cascade. Paths are relative to the theme folder.
- **`assets.js`** items carry optional `defer` / `async` booleans so behavior scripts stay non-blocking.
- **`views`** maps logical slots (`hero`, `cosmos`, optionally `nav`) to Blade files in the theme's `views/`. The app `@includeIf`s only declared slots — a theme with no `cosmos` simply omits the key and nothing breaks.
- **`screenshot`** (optional) — relative image path for the admin picker thumbnail.
- A checked-in **`public/themes/theme.schema.json`** (JSON Schema) validates any `theme.json` — the contract when authoring elsewhere, and the basis for a test asserting every shipped theme validates.

---

## 4. Runtime loading

### `App\Services\ThemeManager` (bound as a singleton)

| Method | Responsibility |
|---|---|
| `active(): string` | Reads `SiteSetting.current()->theme`; falls back to the configured default if the folder is missing — never renders a broken page. |
| `manifest(): array` | Loads + caches the active theme's parsed `theme.json` (once per request). |
| `tokens(): array` | Merges `config('tokens.defaults')` ← theme.json token `value`s ← `SiteSetting.branding`. |
| `cssUrls(): array` | Maps manifest `assets.css` relative paths to `/themes/theme_<name>/…` URLs, in order. |
| `jsAssets(): array` | Maps `assets.js` entries to `{url, defer, async}`. |
| `registerViews(): void` | Registers the theme's `views/` dir under the `theme::` namespace via the view finder. |
| `available(): array` | Scans `public/themes/theme_*`, reads each `theme.json` for `title`/`description`/`screenshot`, flags the active one (for the admin picker + validation). |

### Wiring

- **`App\Providers\ThemeServiceProvider`** — calls `registerViews()` and `View::share('theme', $manager->manifest())` (and the asset helpers) so layout + partials read the manifest without controller changes. Registered in `bootstrap/providers.php`.
- **`partials/tokens.blade.php`** — source changes from "config defaults + branding" to `ThemeManager::tokens()`; identical `:root` output, now theme-aware.
- **`layouts/app.blade.php`** — loops `cssUrls()` into `<link>`s in manifest order, then `@stack('head')`; renders `@includeIf('theme::cosmos')`; loops `jsAssets()` into `<script>`s with the right `defer`/`async`. The home view renders `@includeIf('theme::hero')`.

### Caching & failure handling

- `theme.json` is small, parsed once per request via the singleton. The `theme::` namespace path is registered fresh each boot from the active theme name, so switching themes only requires dropping compiled Blade — the admin Apply action and the artisan command run `view:clear` automatically.
- Missing folder, missing/invalid `theme.json`, or malformed token type → log a warning and fall back to the base/default theme so the public site always renders.

---

## 5. Admin UI, storage & artisan

### Storage

- Migration: add a **`theme` string column** to `site_settings`, default `'solarsystem'`. `SiteSetting::current()->theme` is the single active-theme pointer.
- `branding` is repurposed to *optional per-install token overrides on top of the theme* (usually empty) — no longer the active theme's full token copy.

### Admin Themes page (`/admin/themes`)

- **`Admin\ThemeController@index`** — calls `ThemeManager::available()`; renders a card grid (screenshot thumbnail, title, description, radio/Apply), highlighting the active theme.
- **`Admin\ThemeController@update`** — validates the chosen name against the scanned list (never trusts raw input → no path traversal), writes `SiteSetting.theme`, runs `view:clear`, redirects back with a success flash.
- Linked from the admin dashboard/nav.

### Routes (inside the existing `admin` middleware group in [`routes/web.php`](../../../routes/web.php))

```
GET   /admin/themes     Admin\ThemeController@index
PATCH /admin/themes     Admin\ThemeController@update
```

### Artisan — repurpose [`app:apply-theme`](../../../app/Console/Commands/ApplyTheme.php)

Instead of copying token arrays into `branding`, it validates the folder exists and sets `SiteSetting.theme = {name}`, then clears the view cache. `app:apply-theme default` selects the base `default` theme folder. The documented deploy step `php artisan app:apply-theme solarsystem` keeps working with identical syntax.

### Security

- Theme names validated against the scanned folder list on **every** write (admin form *and* artisan) → no path traversal.
- `.htaccess` per theme folder blocks `*.blade.php` / `theme.json` from direct web fetch.

---

## 6. Migration of the existing theme

### `theme_solarsystem` (reference theme — proves the system, becomes the portable example)

Move current files into the folder, content unchanged:

| From | To |
|---|---|
| `public/css/structure.css` | `theme_solarsystem/css/structure.css` |
| `public/css/skin.css` | `theme_solarsystem/css/skin.css` |
| `public/css/cosmos.css` | `theme_solarsystem/css/cosmos.css` |
| `public/css/hero-solarsystem.css` | `theme_solarsystem/css/hero.css` |
| `public/css/fonts.css` | `theme_solarsystem/css/fonts.css` |
| `public/js/solarsystem.js` | `theme_solarsystem/js/solarsystem.js` |
| Solar System WOFF2 in `public/fonts/` | `theme_solarsystem/fonts/` |
| `resources/views/partials/hero.blade.php` | `theme_solarsystem/views/hero.blade.php` |
| `resources/views/partials/cosmos.blade.php` | `theme_solarsystem/views/cosmos.blade.php` |

Author its `theme.json` from the current `themes.solarsystem` token set + the asset/view manifest above.

### `theme_default` (base light theme)

A minimal light theme so "default" is a real selectable folder (admin lists folders uniformly): `config/tokens.php` light token values, `structure.css` + `skin.css`, no cosmos/hero art (a minimal static hero partial).

### `theme_mystik` (optional / follow-up)

Currently only a palette. Convert by reusing a generic hero or the cosmos backdrop. Not required for the first cut.

---

## 7. Docs & tests impact

### Docs (per the `CLAUDE.md` upkeep rules)

- **[`README.md`](../../../README.md)** — rewrite the Theming, CSS/asset-layering, Artisan, and Project-layout sections for the folder model.
- **[`config/themes.php`](../../../config/themes.php)** — retired; themes now live in folders. Note the migration.
- **[`docs/theme-style-map.json`](../../theme-style-map.json)** — **retired and replaced** by per-theme `theme.json` + the global `public/themes/theme.schema.json`.
- **`CLAUDE.md`** — update the style-map rule to point at "the active theme's `theme.json`" instead of `docs/theme-style-map.json`, and the infra rule to cover the theme-folder mechanism.

### Tests

- Update token-emission + asset-contract tests to assert against the **active theme's manifest** (not hardcoded `public/css/...` paths).
- New `ThemeManager` unit tests: 3-layer token merge; missing-folder fallback to default.
- New schema test: every shipped `theme.json` validates against `theme.schema.json`.
- New admin feature test: valid switch persists to `SiteSetting.theme`; bogus/path-traversal name rejected.

---

## 8. Out of scope (YAGNI)

- Uploading/installing themes through the admin UI (themes are added by dropping a folder on disk).
- Live theme preview without applying.
- Per-page theme overrides.
- Converting `theme_mystik` (optional follow-up).
```

