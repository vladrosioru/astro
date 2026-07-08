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

Markup is **2 links · centered brand · 2 links** and is shared site-wide. The
centered brand stacks the logo over the ASTROTHERAPIA eyebrow wordmark (both link
Home); the wordmark text comes from `SiteSetting.hero.eyebrow`. A checkbox +
two `<label>`s implement a phone hamburger menu with **pure CSS** (the
"checkbox hack" — no JS). The checkbox is a sibling *before* `<nav>`, not
nested inside it — this lets CSS restyle `<nav>` itself from the checkbox's
`:checked` state using a plain `~` sibling combinator, without needing the
`:has()` relational selector (which isn't supported by every browser):

```html
<input type="checkbox" id="nav-toggle" class="nav-toggle-input">
<nav><div class="container">
  <ul class="nav-left"> <li><a>…</a></li> … </ul>
  <div class="nav-brand">
    <a class="nav-logo"><img src="img/logo-nav.png"></a>
    <a class="nav-eyebrow"><span class="rule"></span>ASTROTHERAPIA<span class="rule"></span></a>
  </div>
  <ul class="nav-right">
    <li><a>…</a></li>
    <li class="nav-dropdown">
      <a>Services</a>
      <input type="checkbox" id="services-toggle" class="nav-dropdown-toggle-input">
      <label for="services-toggle" class="nav-dropdown-btn"></label>
      <ul class="nav-dropdown-menu"> <li><a>…</a></li> … </ul>
    </li>
  </ul>
  <label for="nav-toggle" class="nav-toggle-btn"><span></span><span></span><span></span></label>
  <label for="nav-toggle" class="nav-scrim"></label>
</div></nav>
```

| Selector | Notes |
|---|---|
| `nav` | the bar background / border / sticky appearance |
| `nav .container` | flex row holding the two link groups + centered brand |
| `nav ul`, `nav .nav-left`, `nav .nav-right` | the two equal-width link groups flanking the brand |
| `nav .nav-dropdown` | a `nav-right` item (currently Services) that also carries a submenu. `position: relative`; desktop shows `.nav-dropdown-menu` on `:hover`/`:focus-within`. The parent link (`nav .nav-dropdown > a`) stays a normal, independently clickable link — the dropdown is an additional affordance, not a replacement for it. |
| `#services-toggle` / `.nav-dropdown-toggle-input` | same always-hidden-but-focusable checkbox trick as `.nav-toggle-input`, scoped to one `.nav-dropdown`. Inert on desktop (nothing reacts to `:checked` there); on phone it's what the caret button toggles. |
| `nav .nav-dropdown-btn` | the caret/expand control for the submenu — `display: none` on desktop (hover already reveals the menu there), shown only under the phone breakpoint since touch has no hover state. Style it (e.g. a rotating `▾` glyph) and flip it via `.nav-dropdown-toggle-input:checked ~ .nav-dropdown-btn`. |
| `nav .nav-dropdown-menu` | the submenu itself — `display: none` by default. Desktop (`min-width: 721px`): an absolutely positioned panel (`top: 100%`, `margin: 0` — no gap) shown via the parent's `:hover`/`:focus-within` (or the menu's own `:hover`, so the cursor can rest on it). Use `padding-top` (not `margin-top`) to put visual space between the link and the menu's text — `margin` sits outside the box and isn't hoverable, so it creates a dead strip the cursor can wander into while moving from the link down to the menu, breaking `:hover` before it arrives; `padding` is part of the box, so that same strip stays hoverable and keeps the menu open through the transit. Reveal should also use `opacity`/`visibility`/`pointer-events` (not `display`, which can't be transition-delayed) with a close-side `transition-delay` (shipped themes use 1s) as a second line of defense for pointer paths the zero-gap trick doesn't cover (e.g. moving off through the sides). Put the delay on the `opacity` transition too, not just `visibility`/`pointer-events` — otherwise the menu fades to invisible on its normal (short) duration immediately, while remaining merely *technically* "visible" underneath, which looks and feels identical to no delay at all. Phone (`max-width: 720px`): laid out in normal flow (`position: static`, full width) so it pushes the stacked panel down instead of overlaying it, shown purely via `.nav-dropdown-toggle-input:checked ~ .nav-dropdown-menu` — do **not** also let hover/focus-within reveal it at this width, or the two triggers fight and the accordion flickers/collapses. Its `<a>` items inherit the generic `nav a` styling. |
| `nav .nav-brand` | centered column that stacks the logo over the eyebrow wordmark |
| `nav .nav-logo`, `nav .nav-logo img` | the logo link + image sizing |
| `nav .nav-eyebrow` | the ASTROTHERAPIA wordmark under the logo — style it as a small brand label (it also carries the generic `nav a` style, so override color/size as needed). Its two `.rule` spans are optional decorative flanking lines you may style or leave invisible. **Layout:** it's given `width: 0; overflow: visible` so only the narrow logo sizes the centered brand column (keeping the flanking link groups hugged to the logo); the wider wordmark overflows symmetrically onto its own row, partly *under* the side links. On phones (≤720px) it reverts to `width: auto`, centered under the logo. |
| `#nav-toggle` / `.nav-toggle-input` | the hidden checkbox driving the phone menu's open/closed state, placed *before* `<nav>` in the DOM. Always visually hidden (never `display:none`, so it stays focusable/keyboard-operable) — inert on desktop since nothing reacts to `:checked` there. Reference it by its stable `#nav-toggle` id, not `nav .nav-toggle-input` (it's no longer a descendant of `nav`). |
| `nav .nav-toggle-btn`, `nav .nav-toggle-btn span` | the "≡" trigger (3 bars) — `display:none` on desktop, shown only under the phone breakpoint. Style the bars' color/thickness; theme CSS typically animates them into a "×" via `#nav-toggle:checked ~ nav .nav-toggle-btn span:nth-child(n)` transforms. |
| `nav .nav-scrim` | a `<label>` for the same checkbox that dims the page behind the open phone menu — clicking it closes the menu. `display:none` until `:checked`, phone only. Give it a translucent theme-colored background. |
| `nav a` | idle link style (+ `:hover`) |
| `.page-home` | body class on the Home page — use it to special-case the Home nav/hero |

**Phone behavior (≤720px, both shipped themes):** only the brand (logo +
eyebrow) stays visible in the closed bar; `nav .nav-left`/`nav .nav-right` are
hidden and the "≡" trigger appears on the right. Checking the input (tapping
the trigger) turns `nav` into a `position: fixed` panel that overlays the page
(via `#nav-toggle:checked ~ nav` — instead of pushing content down) and
reflows its children into one column: brand first, then the link groups in
their original left-to-right order top-to-bottom, with the scrim dimming
whatever of the page remains visible behind it. A theme is free to restyle
colors/animation but should preserve this open/closed structure — and the
"checkbox precedes `<nav>`" DOM order, which the `~` selectors depend on — so
the pattern stays consistent and broadly browser-compatible across themes.

### Home hero — `views/hero.blade.php` (you author this)

Read the hero copy from settings, then emit the conventional classes:

```blade
@php $hero = array_merge(\App\Models\SiteSetting::heroDefaults(),
                         \App\Models\SiteSetting::current()->hero ?? []); @endphp
<section class="stage">
  <div class="container">
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
| `.stage .title` | the `<h1>` headline |
| `.stage .lede` | the subhead paragraph |
| `.stage .actions` | the CTA row |
| `.btn`, `.btn-primary`, `.btn-ghost` | primary CTA + secondary "ghost" CTA |

Hero data keys come from `SiteSetting::heroDefaults()`: `headline`, `subhead`,
`cta_label`/`cta_url`, `cta2_label`/`cta2_url`. Guard optional keys with
`@if(!empty(...))`. (`eyebrow` is also a hero key, but it's consumed by the shared
nav's `.nav-eyebrow` wordmark — see Navigation — not emitted by the hero.)

> **Shared with the admin Themes picker:** `resources/views/admin/themes/index.blade.php`
> (an authenticated admin screen, not a public page) also renders through this
> same `layouts.app` and reuses the bare `.blog-grid` / `.card` / `.card__media` /
> `.card__body` classes unmodified for its own 3-up theme-switcher grid. Restyle
> those four base classes carefully — anything you change there also reflows the
> admin picker. The journal-specific look below is layered on **top** via a
> `.blog-grid--journal` modifier and new `.card__*` elements the admin page never
> emits, so it can't affect that screen.

### Journal / Article hero — `blog/index.blade.php` and `blog/show.blade.php`

Both the Journal list and every article page open with the same full-bleed
photo-band hero (title overlaid on an image), authored directly in the shared
view — there's no `theme::` slot for it, just two fixed classes every theme styles:

```html
<header class="journal-hero">
  <h1 class="journal-hero__title">Cosmic Journal</h1> <!-- or the article's title -->
</header>
```

| Selector | Notes |
|---|---|
| `.journal-hero` | full-bleed band — background image/color, centering (layout in `structure.css`; background + `::before` scrim in `skin.css`). **Height is not fixed** — see below. |
| `.journal-hero__title` | the overlaid `<h1>` — color, optional `text-shadow` for legibility over a photo. **Don't set its own `font-size`/`line-height`** — see below. |

`theme_solarsystem` sets `.journal-hero`'s `background-image` to a photo it ships
in its own `img/` folder (referenced with a CSS-relative `url(../img/...)`, so
nothing outside that theme's folder needs to know the filename) plus a
`.journal-hero::before` scrim using the `hero-overlay` token for legibility.
`theme_default` has no photo asset, so it renders `.journal-hero` as a flat
`color-bg-alt` band instead — a theme is free to do either; there's no
requirement that a hero have a photo.

**Sizing contract — band height is always 3x the title's text size:** put the
title's `font-size` (e.g. a `clamp(...)`) on `.journal-hero` itself in `skin.css`,
not on `.journal-hero__title` — give the title `font-size: 1em; line-height: 1;`
instead so it inherits that value exactly. `structure.css` then gives
`.journal-hero` `padding: 1em ...` top/bottom, which resolves against that same
font-size: 1em padding + 1em title line + 1em padding = 3x the title's text size,
at every breakpoint the `clamp()` produces. This also keeps the empty space
above/below the title symmetric on article pages where a longer title wraps to
two lines — the padding is a fixed multiple of the font-size, not of the number
of lines, and `align-items: center` centers whatever the title's box turns out
to be. Follow this pattern rather than setting a `min-height` on `.journal-hero`.

### Blog listing — `resources/views/blog/index.blade.php`

```html
<div class="blog-grid blog-grid--journal">
  <article class="card card--media card--first">
    <div class="card__meta">
      <p class="card__date">…</p>
      <h2 class="card__title"><a>…</a></h2>
      <p class="card__subtitle">…</p>
    </div>
    <a class="card__media-link"><img class="card__media"></a>
    <div class="card__body">
      <p class="card__excerpt">… <a class="card__ellipsis">[...]</a></p>
      <a class="card__more btn btn-primary">Read more</a>
    </div>
  </article>
</div>
```

The card is an `<article>`, not a single wrapping `<a>` — the image, the title,
the `[...]` excerpt link, and the "Read more" button are four separate links
(all pointing at the same article) so none of them nest inside another.

This exact markup lives in one shared partial,
[`resources/views/partials/journal-card.blade.php`](../../resources/views/partials/journal-card.blade.php),
so it doesn't drift between call sites. The Journal listing loops it once per
post (`card--first` on the newest); `pages/home.blade.php` also renders it
once, for the newest post only, in its own "From the Journal" section — so a
theme's `.blog-grid--journal`/`.card*` styling now applies there too, not just
on `/journal`.

`.card__meta` (date + title + subtitle) is a **separate block stacked above**
the image, not overlaid on top of it — the square image always renders fully,
uncropped by any text panel. `.card__body` below the image holds the
auto-generated `.card__excerpt` teaser + "Read more". For cards **without** a
featured image, there's no image to put a block above, so `.card__date`/
`.card__title`/`.card__subtitle` render directly inside `.card__body` instead,
followed by `.card__excerpt` and "Read more" — same classes, different parent.

| Selector | Notes |
|---|---|
| `.blog-grid` | base wrapper — see the admin-sharing note above |
| `.blog-grid--journal` | modifier added alongside `.blog-grid` on the public Journal page only — turns the base grid into a single-column stacked list of full-width cards, capped to a readable max-width |
| `.card` | a card (also used as a plain text panel for image-less posts) |
| `.card--media` | card variant with a top image (flush edges, clipped corners) |
| `.card--first` | modifier on the newest/first card in the list — give it a visually stronger border |
| `.card__meta` | date + title + subtitle block, stacked **above** the image (media cards only) — give it the **same opaque background as `.card__body`** (`color-bg-alt`) so it duplicates that panel's look |
| `.card__media-link`, `.card__media` | the image link + image — give the image a square `aspect-ratio` + `object-fit: cover` |
| `.card__body` | below the image: excerpt + "Read more" (media cards) or date + title + subtitle + excerpt + "Read more" (text-only cards) |
| `.card__date` | the publish date, above the title, wherever it renders |
| `.card__title`, `.card__title a` | the title heading + its link |
| `.card__subtitle` | the post's admin-entered `subtitle` field (`post_translations.subtitle`, empty → omitted), right under the title, no link — **same font-family as the article body text** (`public/css/article.css`), but italic + bold |
| `.card__excerpt` | auto-generated teaser (first sentence + first few words of the next, derived from the article body), ending in the `.card__ellipsis` link — plain style, distinct from `.card__subtitle` |
| `.card__ellipsis` | the trailing `[...]` link into the article |
| `.card__more` | the "Read more" button — markup pairs it with `.btn.btn-primary` so it reuses the Home hero's CTA styling; add card-specific spacing only |

### Article body — `resources/views/blog/show.blade.php`

The article's title now renders inside `.journal-hero__title` (see above), not
as a bare `article > h1` — there's no separate title element for the theme to
style here. (The excerpt/teaser lives on the **Journal listing**, right under
the card title — see `.card__excerpt` above — not on this page.) Below the
hero, in order: the post's own `.article-image` (if it has one), the body in
`.article-paper > .ck-content`, an `.article-footer` (date + share icons), and
an `.article-adjacent` previous/next nav — the chronologically adjacent published posts in the same locale (`BlogController@show`), each link omitted when there isn't one (i.e. on the oldest/newest post).

All of it — including `.article-paper`/`.ck-content` — is styled by the
**app-level** [`public/css/article.css`](../css/article.css), **not** per-theme
CSS. It used to hardcode a white "paper" with black CKEditor-matching text;
it's now fully `var(--token)`-driven instead, so it automatically matches
whichever theme is active (same panel look as a Journal `.card`) — there is
**no more requirement that the article body reproduce the CKEditor editing
surface's literal colors**. `public/css/article.css` still loads the editor's
own `ckeditor5.css` so structural rich-text rules (lists, tables, spacing)
match, only the colors are re-themed. Because this file is app-level and
token-driven, adding a theme does **not** require touching it — the theme
only owns:

| Selector | Notes |
|---|---|
| `figure.image`, `figure.image.image-style-*`, `figure.image.image_resized`, `figcaption` | published image layout/alignment — mirror the editor so images render as authored (token-driven radius/spacing). Copy this block from `theme_default/css/skin.css`. |

---

## View slots

Declared under `theme.json` → `views` (with `"namespace": "theme"`):

| Slot | Rendered by | When |
|---|---|---|
| `hero` | `theme::hero` in `pages/home.blade.php` | **Home only.** Provide it for a Home hero. |
| `cosmos` | `@includeIf('theme::cosmos')` in the layout | **Every page**, behind all content. Optional — a fixed full-viewport backdrop (see `theme_solarsystem`). Omit it for a flat background. |
| `solar-motif` | `@includeIf('theme::solar-motif')` in `pages/about.blade.php` | **About only**, as a section divider. Optional — a compact, non-interactive reuse of the Home hero's motif with no title/copy (see `theme_solarsystem`). Omit it to leave that spot empty. |

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
- [ ] All shared-view classes above are styled (nav, hero, journal hero, blog grid, article images).
- [ ] `views/hero.blade.php` reads `SiteSetting::heroDefaults()` and guards optional keys.
- [ ] `structure.css` has no colors/fonts; `skin.css` has no layout.
- [ ] Token `role` strings in `theme.json` match where you actually use each token.
- [ ] The contract test passes and `/en` + blog + article render correctly.
