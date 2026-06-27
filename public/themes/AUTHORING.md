# Authoring a theme

This guide is the portable spec for building a theme that "matches everything"
in this site. A theme is a **self-contained package** under
`public/themes/theme_<name>/`, described by its own
[`theme.json`](theme.schema.json) and made of plain CSS (plus optional fonts, JS,
and Blade view slots). No PHP, no build step.

Two shipped themes are worked examples:

- [`theme_default/`](theme_default/) — minimal **light** base theme (system fonts,
  the token defaults). Start here; copy it as your skeleton.
- [`theme_solarsystem/`](theme_solarsystem/) — **dark** theme with self-hosted
  fonts, an animated Home hero, and a site-wide cosmos backdrop. The richer example.

> The single rule that makes everything else work: **the shared Blade views
> (nav, blog, pages, article) emit a fixed set of CSS classes that your theme
> does not control. Your job is to style those classes, using only design
> tokens (`var(--token)`) for every color, font, space, and radius.**

---

## How a theme loads

1. `SiteSetting.theme` holds the active theme name (the folder is `theme_<name>`).
   `App\Services\ThemeManager` resolves it, falling back to `config('theme.fallback')`
   (`default`) if the folder is missing.
2. The layout injects every token as a CSS custom property into `:root`
   (`partials/tokens.blade.php`), then links your `assets.css` in order and adds
   your `assets.js`.
3. Your `views/` directory is registered under the `theme::` Blade namespace, so
   the layout can pull in `theme::hero` and `theme::cosmos`.

Token values are merged: `config/tokens.php` defaults ← your `theme.json` values
← `SiteSetting.branding` (optional per-install overrides). Branding is **per
theme**: switching themes clears it, so one theme's palette can't bleed into the
next.

---

## Package anatomy

```
public/themes/theme_<name>/
├── theme.json            # manifest: metadata, tokens, fonts, assets, view slots
├── css/
│   ├── structure.css     # layout only (no colors/fonts) — see "Layering"
│   └── skin.css          # appearance only, fully token-driven
├── views/
│   └── hero.blade.php    # the Home hero slot (theme::hero)
├── fonts/                # optional self-hosted font files
├── screenshot.png        # optional, shown in the admin Themes picker
└── .htaccess             # optional
```

`theme.json` is validated against [`theme.schema.json`](theme.schema.json) and by
`tests/Unit/ThemeJsonContractTest.php` (every shipped theme must pass). Keep the
manifest in sync with your CSS/fonts/hero markup — it is both the app's loader
manifest **and** the spec another app/agent reads to author a compatible theme.

---

## Design tokens

Every color, font, spacing step, radius, and shadow in your CSS **must** come
from a token: `var(--color-bg)`, `calc(var(--space-unit) * 4)`, etc. Never write a
raw literal — that's what makes a theme retargetable and what `SiteSetting.branding`
overrides hook into.

The canonical registry (names + safe light defaults) is
[`config/tokens.php`](../../config/tokens.php). A theme may override any value in
its `theme.json`; a token it omits falls back to the default, so the site always
renders.

| Token | Type | Default | Typical role |
|---|---|---|---|
| `color-primary` | color | `#2563eb` | Links, eyebrow, primary/ghost buttons |
| `color-accent` | color | `#7c3aed` | Hover / active highlight |
| `color-bg` | color | `#ffffff` | Page background; nav bar tint; primary-button text |
| `color-bg-alt` | color | `#f5f5f7` | Cards / panels; nav border |
| `color-fg` | color | `#111827` | Body text; card border tint |
| `color-heading` | color | `#111827` | Headings; hero title |
| `color-muted` | color | `#6b7280` | Muted text; idle nav links; hero lede |
| `font-base` | font-stack | system sans | Body / UI text |
| `font-heading` | font-stack | system sans | Headings |
| `font-display` | font-stack | system serif | Button / CTA chrome |
| `space-unit` | length | `0.25rem` | Spacing scale base (multiply with `calc()`) |
| `radius` | length | `0.5rem` | Corner radius |
| `shadow` | shadow | `0 1px 3px rgba(0,0,0,0.1)` | Card elevation |
| `container-width` | length | `64rem` | Max content width |
| `nav-height` | length | `4rem` | Nav min height |
| `hero-overlay` | color | `rgba(0,0,0,0)` | Optional hero scrim color |

In `theme.json` each token carries a `type`, a human `role`, and a `value`. The
`role` is documentation — keep it accurate to where the token is actually used, so
the manifest stays a true map.

---

## The class vocabulary (style all of these)

These selectors are emitted by **shared** views you can't edit, so your theme is
responsible for styling them. The Home hero is the one block of markup you author
yourself (`views/hero.blade.php`); follow the convention below so it matches the
rest of the site and so a persisted hero (`SiteSetting.hero`) renders.

### Global / layout — every page

| Selector | Where | Notes |
|---|---|---|
| `body` | layout | set `background`, `color`, `font-family` |
| `.container` | every page | the centered content column (max-width is `--container-width`, set in `structure.css`) |
| `h1, h2, h3` | everywhere | heading font + color |
| `a` | everywhere | link color (+ `:hover`) |
| `.muted` | pages, blog | secondary / dimmed text |

### Navigation — `resources/views/partials/nav.blade.php`

Markup is **2 links · centered logo · 2 links** and is shared site-wide:

```html
<nav><div class="container">
  <ul class="nav-left"> <li><a>…</a></li> … </ul>
  <a class="nav-logo"><img src="img/logo-nav.png"></a>
  <ul class="nav-right"> <li><a>…</a></li> … </ul>
</div></nav>
```

| Selector | Notes |
|---|---|
| `nav` | the bar background / border / sticky appearance |
| `nav .container` | flex row holding the two link groups + logo |
| `nav ul`, `nav .nav-left`, `nav .nav-right` | the two equal-width link groups flanking the logo |
| `nav .nav-logo`, `nav .nav-logo img` | the centered logo link + image sizing |
| `nav a` | idle link style (+ `:hover`) |
| `.page-home` | body class on the Home page — use it to special-case the Home nav/hero |

### Home hero — `views/hero.blade.php` (you author this)

Read the hero copy from settings, then emit the conventional classes:

```blade
@php $hero = array_merge(\App\Models\SiteSetting::heroDefaults(),
                         \App\Models\SiteSetting::current()->hero ?? []); @endphp
<section class="stage">
  <div class="container">
    <p class="eyebrow">{{ $hero['eyebrow'] }}</p>
    <h1 class="title">{{ $hero['headline'] }}</h1>
    <p class="lede">{{ $hero['subhead'] }}</p>
    <div class="actions">
      <a class="btn btn-primary" href="…">{{ $hero['cta_label'] }}</a>
      <a class="btn btn-ghost"   href="…">{{ $hero['cta2_label'] }}</a>
    </div>
  </div>
</section>
```

| Selector | Notes |
|---|---|
| `.stage` | the hero band (padding, centering, or a full-viewport stage) |
| `.stage .eyebrow` | small label above the headline |
| `.stage .title` | the `<h1>` headline |
| `.stage .lede` | the subhead paragraph |
| `.stage .actions` | the CTA row |
| `.btn`, `.btn-primary`, `.btn-ghost` | primary CTA + secondary "ghost" CTA |

Hero data keys come from `SiteSetting::heroDefaults()`: `eyebrow`, `headline`,
`subhead`, `cta_label`/`cta_url`, `cta2_label`/`cta2_url`. Guard optional keys with
`@if(!empty(...))`.

### Blog listing — `resources/views/blog/index.blade.php`

```html
<div class="blog-grid">
  <a class="card card--media">
    <img class="card__media">
    <div class="card__body"> <h2>…</h2> <p class="muted">…</p> </div>
  </a>
</div>
```

| Selector | Notes |
|---|---|
| `.blog-grid` | responsive grid wrapper |
| `.card` | a card (also used as a plain text panel for image-less posts) |
| `.card--media` | card variant with a top image (flush edges, clipped corners) |
| `.card__media` | the image — give it a square `aspect-ratio` + `object-fit: cover` |
| `.card__body`, `.card__body h2`, `.card__body .muted` | the title + excerpt area |

### Article body — `resources/views/blog/show.blade.php`

The published article body is rendered inside `.article-paper > .ck-content` and
styled by the **app-level** [`public/css/article.css`](../css/article.css) (so it
matches the CKEditor editing surface). **Don't restyle `.ck-content`** — the theme
only owns:

| Selector | Notes |
|---|---|
| `article > h1` | the article title above the paper (themed like the rest of the site) |
| `figure.image`, `figure.image.image-style-*`, `figure.image.image_resized`, `figcaption` | published image layout/alignment — mirror the editor so images render as authored (token-driven radius/spacing). Copy this block from `theme_default/css/skin.css`. |

---

## View slots

Declared under `theme.json` → `views` (with `"namespace": "theme"`):

| Slot | Rendered by | When |
|---|---|---|
| `hero` | `theme::hero` in `pages/home.blade.php` | **Home only.** Provide it for a Home hero. |
| `cosmos` | `@includeIf('theme::cosmos')` in the layout | **Every page**, behind all content. Optional — a fixed full-viewport backdrop (see `theme_solarsystem`). Omit it for a flat background. |

> Reserved / not yet wired: the schema also accepts a `views.nav` slot and a
> top-level `page_home_class`, but the app currently always renders the shared
> `partials/nav.blade.php` and hardcodes the `page-home` body class on Home. Style
> `.page-home`; don't rely on those two fields doing anything yet.

---

## Layering convention

Split your CSS into two manifest entries, in this order:

1. **`structure.css`** — layout only: `display`, `flex`/`grid`, positioning,
   spacing, `max-width`, media queries. No colors or font families. (Lengths/spacing
   may still use `var(--space-unit)`, `var(--container-width)`, `var(--nav-height)`.)
2. **`skin.css`** — appearance only: backgrounds, text colors, fonts, borders,
   shadows, radii — all via tokens.

Keeping them separate makes a re-skin a one-file change and keeps tokens out of the
layout layer.

---

## Fonts

To self-host fonts: drop the files under `fonts/`, declare `@font-face` in a CSS
file listed in `assets.css` (e.g. a `fonts.css` loaded first), point the
`font-base` / `font-heading` / `font-display` tokens at those families, and list
each face in `theme.json` → `fonts[]` (`family`, `files`, optional
`weights`/`style`/`used_for`). The default theme just uses system fonts and ships
no `fonts/`.

---

## Build & validate

1. **Apply it:** `php artisan app:apply-theme <name>` (or Admin → Themes). A real
   switch resets `SiteSetting.branding`.
2. **Validate the manifest + assets:**
   `php artisan test --filter ThemeJsonContractTest` — checks `theme.json` is
   structurally valid, every token name is known, and every referenced CSS/JS/view
   file exists on disk.
3. **Check rendering:** `php artisan test --filter "ThemeRendering|ThemeTokens|StyleTokens"`,
   then load `/en`, `/en/blog`, a post, `/en/about`, `/en/contact` and confirm the
   nav, hero, cards, and article all pick up your skin.

## Authoring checklist

- [ ] `theme.json` validates and lists your `assets.css` in load order.
- [ ] Every color/font/space/radius in CSS is a `var(--token)` — no literals.
- [ ] All shared-view classes above are styled (nav, hero, blog grid, article images).
- [ ] `views/hero.blade.php` reads `SiteSetting::heroDefaults()` and guards optional keys.
- [ ] `structure.css` has no colors/fonts; `skin.css` has no layout.
- [ ] Token `role` strings in `theme.json` match where you actually use each token.
- [ ] The contract test passes and `/en` + blog + article render correctly.
