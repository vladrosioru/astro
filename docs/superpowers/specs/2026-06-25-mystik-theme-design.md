# "Mystik" Theme — Design Spec

**Date:** 2026-06-25
**Status:** Approved design
**Context:** First real theme for the template — a dark, mystical astrology look (inspired by the "mystik" reference) — authored entirely through the existing **token style-isolation contract** so it becomes a selectable `Theme` record when the admin theming feature ships later.

## 1. Goal

Give the site a faithful "Mystik" look: dark starfield aesthetic, gold serif display headings, light body text, a sticky translucent dark nav, and a Home hero — applied across all pages, swappable via tokens.

## 2. Constraints & principles

- **Theming-ready, not the theming feature.** No admin Theme manager yet. The theme is a **token value set** applied via `SiteSetting.branding` (the same `:root` override path the future `Theme` model will use). Default light theme remains as `config/tokens.php` defaults.
- **Node-free / cPanel-safe:** self-hosted OFL fonts in `public/fonts/`; CSS-only starfield (no binary asset, no JS animation library).
- **Style isolation holds:** all appearance comes from token-driven skin CSS; no raw color/font literals in templates. Structure vs. skin split preserved.

## 3. Token vocabulary (extended, additive)

New tokens added to `config/tokens.php` with **light defaults** (so the default theme still works), overridden by Mystik values:

| Token | Light default | Mystik value |
|---|---|---|
| `color-bg` | `#ffffff` | `#0a0a0f` |
| `color-bg-alt` (new) | `#f5f5f7` | `#14141f` |
| `color-fg` | `#111827` | `#e8e6f0` |
| `color-heading` (new) | `#111827` | `#f0a23c` (gold) |
| `color-primary` | `#2563eb` | `#f0a23c` |
| `color-accent` | `#7c3aed` | `#c9962f` |
| `color-muted` | `#6b7280` | `#9a96ad` |
| `font-display` (new) | system serif | `'Cinzel', serif` |
| `font-base` | system sans | `'EB Garamond', serif` |
| `nav-height` (new) | `4rem` | `4.5rem` |
| `hero-overlay` (new) | `rgba(0,0,0,0.0)` | `rgba(0,0,0,0.55)` |

## 4. Components

### 4.1 Fonts
- Self-host **Cinzel** (display) and **EB Garamond** (body) WOFF2 in `public/fonts/`; `public/css/fonts.css` with `@font-face`; linked in the layout head. Tokens `font-display`/`font-base` reference them.

### 4.2 Activation
- Artisan command `app:apply-theme {name}` writes a named token set into `SiteSetting.branding` (and clears it for `default`). Mystik token values live in `config/themes.php` (`themes.mystik`). This mirrors the future Theme-record activation exactly.

### 4.3 Sticky nav
- `partials/nav.blade.php` restyled: sticky, translucent dark (`color-bg` + alpha), centered gold wordmark (`✦ {site name} ✦`), uppercase letter-spaced links. Structure in `structure.css`, appearance in `skin.css`, all token-driven. Height = `var(--nav-height)`.

### 4.4 Home hero (reusable block)
- `partials/hero.blade.php` — full-bleed section: **CSS starfield** (layered `radial-gradient` dots) under a `hero-overlay`, gold Cinzel headline, muted subhead, gold CTA button linking to a configurable target. Content (headline/subhead/CTA label+url) read from `SiteSetting` (`hero` JSON) with sensible defaults. Authored as a standalone partial so it maps to a future theming "Hero block."
- Home view includes the hero above its container.

### 4.5 Skin
- Headings: `font-display`, `color-heading`, letter-spacing. Body: `font-base`, `color-fg` on `color-bg`. Links/buttons: gold. Cards: `color-bg-alt` panels with subtle border. Blog `figure`/image rules (from CKEditor spec) inherit these.

## 5. Data
- `SiteSetting` gains a `hero` JSON column (headline, subhead, cta_label, cta_url) — editable later in admin; seeded with Mystik copy now. `branding` already exists for token overrides.

## 6. Testing
- `app:apply-theme mystik` writes expected gold/dark tokens into `branding`; `app:apply-theme default` clears them.
- Token partial emits Mystik values when active (`--color-heading: #f0a23c`).
- Home renders the hero headline; nav renders the gold wordmark.
- Fonts.css references the self-hosted files.

## 7. Out of scope
- Admin theme manager / upload / duplication (deferred theming feature).
- Hero slider/carousel, e-commerce cart sidebar, chat widget, parallax/JS animation (present in the reference, not needed here).
- Per-block layout editing.
