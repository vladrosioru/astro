# "Solar System" Theme — Design Spec

**Date:** 2026-06-26
**Status:** Approved design
**Context:** A second front-end theme for the template, replacing the active "Mystik" look with an animated celestial landing — a full-viewport orbiting solar-system Home hero plus a dark cosmos skin on inner pages. Source artifact: `resources/theme_solarsystem/` (`index.html`, `css/styles.css`, `js/main.js`). Authored through the existing **token style-isolation contract** and **data-driven** content model so it activates the same way Mystik does.

## 1. Goal

Faithfully reproduce the `theme_solarsystem` design within the project's constraints:

- **Home:** full-viewport (`100vh`) animated `.stage` — drifting starfield, nebula glow, JS-twinkling stars, five orbiting planets + sun, vignette, and mouse parallax — overlaid with the nav and hero copy.
- **Inner pages (About / Contact / Blog):** the matching dark celestial *skin* (palette, fonts, nav, buttons, cards, static CSS starfield) without the full orbit animation.
- Stays **Node-free**, **token-driven**, **multi-locale**, and **admin-overridable**.

## 2. Constraints & principles

- **Node-free / cPanel-safe:** self-hosted OFL fonts in `public/fonts/`; plain CSS + a small vanilla-JS file in `public/`; no build step, no CDN, no animation library.
- **Theming mechanism reuse:** the theme is a **named token set** in `config/themes.php` (`themes.solarsystem`), applied to `SiteSetting.branding` via the existing `php artisan app:apply-theme solarsystem`. `config/tokens.php` light defaults are **unchanged**; Mystik is **kept** alongside. "Replace the existing theme" = switch the active set to `solarsystem`.
- **Style isolation holds:** brand-level palette/fonts come from tokens; only decorative literals (planet gradients, nebula, sun glow, star colors) live in the theme CSS. Structure-vs-skin split preserved; the elaborate stage/orbit/animation CSS is a dedicated `.stage`-scoped module.
- **Data-driven content preserved:** hero copy from `SiteSetting.hero`; nav from `SiteSetting` (`sectionVisible`, locale-prefixed URLs); brand from `config('app.name')`. No theme demo copy ("AstroTherapia") hardcoded.

## 3. Token set (`config/themes.solarsystem`)

Maps the theme's `:root` to the existing token vocabulary. Applied to `branding`; defaults stay light.

| Token | Solar System value | Theme source |
|---|---|---|
| `color-bg` | `#05060c` | `--bg` |
| `color-bg-alt` | `#0b1426` | nebula/base mid-tone |
| `color-fg` | `#aab6c8` | `--text` (body) |
| `color-heading` | `#f2f7fd` | `--ink` |
| `color-muted` | `#9aa6b8` | `--muted` |
| `color-primary` | `#9dc1e6` | `--icy` (accent) |
| `color-accent` | `#dcebfb` | `--icy-bright` (highlight) |
| `font-display` | `'Cinzel', serif` | brand / chrome / buttons (uppercase, letter-spaced) |
| `font-heading` | `'Cormorant Garamond', serif` | hero title + `h1/h2/h3` |
| `font-base` | `'Jost', system-ui, sans-serif` | body / UI text |
| `nav-height` | `4.5rem` | nav padding |
| `hero-overlay` | `rgba(0,0,0,0.45)` | retained for the inner-page/legacy `.hero` overlay + test contract |

Decorative (non-token) literals kept in theme CSS: planet radial-gradients, sun glow, nebula radial-gradients, twinkle star colors, saturn ring.

## 4. Components

### 4.1 Fonts (self-hosted)
Download WOFF2 into `public/fonts/` and add `@font-face` rules to `public/css/fonts.css`:
- **Jost** — 300, 400, 500, 600 (body/UI).
- **Cormorant Garamond** — 400, 500, plus 400 italic (hero title emphasis).
- **Cinzel** — already present (500/600/700 covered by existing 400/700 faces; add 600 if a file is available, else reuse 700).

### 4.2 Home animated stage
- **CSS:** new `public/css/hero-solarsystem.css` — the `.stage`, `.bg-base`, starfield (`.bg-layer/.stars1/.stars2`), `.nebula`, `.twinkle`, `.solar-wrap/.plane/.orbit*/.anchor/.planet*/.saturn*/.sun`, `.vignette`, `.scroll-cue`, all keyframes, and the responsive + `prefers-reduced-motion` blocks. All selectors scoped under `.stage` to avoid leaking onto inner pages.
- **JS:** new `public/js/solarsystem.js` — adapted from `main.js`: generates twinkle stars and binds mouse parallax. Self-guards (`if (!document.querySelector('.stage')) return;`) so it is inert on inner pages; loaded globally with `defer`.
- Linked from `layouts/app.blade.php` (stylesheet in `<head>`, script before `</body>`).

### 4.3 Hero partial (`partials/hero.blade.php`, rebuilt, data-driven)
Replaces the old starfield `<header class="hero">` with the `.stage` markup. Content bindings:
- `SiteSetting.hero.headline` → `.title`
- `SiteSetting.hero.subhead` → `.lede`
- `SiteSetting.hero.cta_label` / `cta_url` → primary `.btn.btn-primary`
- `SiteSetting.hero.eyebrow` → `.eyebrow` kicker (rendered only if non-empty)
- `SiteSetting.hero.cta2_label` / `cta2_url` → ghost `.btn.btn-ghost` (rendered only if `cta2_label` non-empty)

The stage contains **only** the cosmos layers, the hero copy, and the scroll cue — **not** the nav (the nav stays a single global include; see 4.5). The stage is `100vh`; the hero copy is vertically centered with a top offset of `~var(--nav-height)` so it never hides behind the overlaid nav.

The hero copy container keeps the class `hero` (the theme uses `<main class="hero">`), preserving the `.hero` / `var(--hero-overlay)` references the skin tests assert.

### 4.4 Hero data model (`SiteSetting::heroDefaults()`, additive)
Add three optional keys so the theme's hero is fully representable without an admin-UI change (defaults fill missing keys via the existing `array_merge`):
- `eyebrow` → `'Celestial Guidance'`
- `cta2_label` → `'Read the Journal'`
- `cta2_url` → `'/en/blog'`

Existing keys (`headline`, `subhead`, `cta_label`, `cta_url`) unchanged — `HeroTest` stays green.

### 4.5 Nav (`partials/nav.blade.php`, restyled, unchanged data)
**One** data-driven partial, included **once** in the layout for every page (no duplication, no conditional include). Appearance switches via a page class on `<body>`: `layouts/app.blade.php` renders `<body class="@yield('body_class')">`, Home sets `@section('body_class', 'page-home')`. Brand = `✦ {app.name} ✦` in `font-display`; uppercase letter-spaced links; conditional via `sectionVisible()`; locale-prefixed hrefs. Optional `.cta` pill at the right.
- **Home (`.page-home nav`):** `position: absolute`, transparent, no border — overlays the top of the stage above the cosmos.
- **Inner pages (default `nav`):** sticky, translucent dark (`color-bg` + alpha) with `backdrop-filter: blur` and a subtle bottom border (existing skin behavior).

### 4.6 Inner-page skin (`structure.css` + `skin.css`, token-driven)
- `body` on `--color-bg` with a lightweight **static** CSS starfield (layered `radial-gradient` dots) so About/Contact/Blog read as the same cosmos without JS or the orbit cluster.
- `.container`, headings, links, `.muted`, `.btn`/`.hero-cta`, `.card` restyled for the dark palette and readability (cards = `--color-bg-alt` panels, hairline border).
- **Test contract retained:** `skin.css` keeps literal `.hero`, `var(--hero-overlay)`, `var(--font-display)`, `var(--color-heading)`, and the CKEditor image-alignment rules (`SkinCssTest`).

## 5. Activation
`php artisan app:apply-theme solarsystem` writes the token set into `SiteSetting.branding`. `app:apply-theme default` clears it. No command code change (it already reads `config("themes.{name}")`).

## 6. Testing
- New `ApplyThemeCommandTest` case: applying `solarsystem` writes `--color-bg: #05060c` / `--color-primary: #9dc1e6` into branding and the token partial emits them.
- `fonts.css` references the new self-hosted Jost + Cormorant Garamond files (extend the existing fonts assertion or add one).
- `hero-solarsystem.css` exists and defines `.stage`, `.orbit`, `@keyframes spin`; `solarsystem.js` exists and is self-guarded.
- Hero renders `eyebrow` and the secondary CTA when set; omits them when empty.
- Regression: full suite (currently 37) stays green — `HeroTest`, `ThemeTokensTest` (light defaults incl. `--nav-height: 4rem`), `SkinCssTest`, `ApplyThemeCommandTest` (mystik), `PublicPagesTest`, `StyleTokensTest` unaffected.

## 7. Out of scope
- Admin theme manager / theme switcher UI (deferred theming feature).
- New hero-content admin editor (the three new fields are seed/`branding`-level for now).
- Per-page orbit animation on inner pages; carousel/cart/chat.
- Removing or altering the Mystik theme.
