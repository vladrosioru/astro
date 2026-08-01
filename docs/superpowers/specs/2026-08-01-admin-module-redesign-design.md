# Admin Module Redesign — Design

Date: 2026-08-01

Mock: <https://claude.ai/code/artifact/eff709e7-dfa1-484c-a81e-8ced511c7f78>

## Goal

Replace the admin module's borrowed, theme-driven appearance with a simple
functional design of its own. After this change, no admin page loads theme CSS,
theme tokens, theme fonts or theme JS: switching the active theme cannot alter
a pixel of the admin. The single exception is the post editor's **Preview**,
which must keep showing the real themed article and therefore renders the theme
inside an isolated frame.

The owner's original ask kept the themed site nav on top of the admin; that was
withdrawn once it turned out nothing required it. The admin now owns its full
chrome.

## Context and constraints

Properties of the existing code, not choices made here.

| Constraint | Source | Consequence |
| --- | --- | --- |
| Admin views extend `layouts/app.blade.php`, which loads `partials.tokens`, every theme CSS file, `theme::cosmos` and all theme JS | `resources/views/layouts/app.blade.php:7-24` | Every admin page today downloads the theme's fonts, starfield and hero JS it never uses. A separate layout removes all of it. |
| Theme nav rules are scoped to `body > nav`; global rules are not | `public/themes/theme_solarsystem/css/structure.css:12`, `.../skin.css:2-5` | Themes style bare `body`, `h1,h2,h3`, `a`, plus `.container` / `.card` / `.btn` / `.muted`. Any of those names reused in admin markup inherits theme appearance. The admin must avoid the shared class names entirely. |
| `layouts/app.blade.php:18` suppresses footer and back-to-top on `admin.*` routes | same file | With a dedicated admin layout that branch is dead and gets removed; the public layout renders footer and back-to-top unconditionally. |
| `public/css/article.css` makes 70 `var(--color-*)` / `var(--font-*)` / `var(--radius)` references | `public/css/article.css` | Article rendering is theme-token dependent by design. It cannot be loaded on a token-free admin page and still look right — hence the isolated preview frame. |
| `article.css` also overrides CKEditor's `--ck-color-base-*` chrome vars on `.article-paper`, using theme tokens | `public/css/article.css`, asserted by `tests/Unit/ArticleCssTest.php` | That block exists only for the admin editor. It moves to the admin stylesheet, restated in `--adm-*` terms, and its test moves with it. |
| The post form's Preview reads live CKEditor data and injects it into `.ck-content` inside the page | `resources/views/admin/posts/_form.blade.php:80-89,132-154` | Moving the preview into an iframe changes how that data is delivered, not where it comes from. |
| No test asserts the site nav, footer or theme assets on any `/admin*` route | `tests/Feature/AdminAccessTest.php`, `AdminAuthTest.php`, `NavBrandingTest.php` (hits `/en`) | Removing the nav from admin breaks no existing coverage. |
| The Themes screen needs only each package's `screenshot.png` | `resources/views/admin/themes/index.blade.php:22-24` | Theme previews stay intact with zero theme CSS loaded. |
| Admin login (`admin.login`) sits outside the `admin` middleware group | `routes/web.php:14-18` | The login view uses the admin layout but must render no navigation, since there is nothing to navigate to yet. |

## Decisions

### Isolation mechanism: separate layout, separate stylesheet, separate vocabulary

`resources/views/layouts/admin.blade.php` loads exactly one stylesheet,
`public/css/admin.css`, via `versioned_asset()`. It includes no
`partials.tokens`, no `theme.manager` asset loop, no `theme::cosmos`, no
`partials.nav`, no footer, no back-to-top.

Rejected: keeping `layouts/app` and neutralising the theme with overrides —
that leaves theme fonts and JS downloading on every admin page and makes the
admin's appearance a function of what the theme happens to declare.

Admin markup uses its own class names (`adm-` prefixed, listed below) and never
reuses `.container`, `.card`, `.btn`, `.btn-primary`, `.muted`, `.blog-grid`,
`.journal-hero`. This is belt-and-braces given the layout no longer loads theme
CSS at all, but it keeps the two vocabularies from colliding if a future screen
ever renders themed content.

`admin.css` defines its own palette as `--adm-*` custom properties on
`.adm-body` (the admin `<body>` class), not on `:root`, so nothing about the
admin can leak into a themed page or vice versa.

### Palette: dark neutral

Ground `#0f1216`, panel `#161b21`, raised `#1d242c`, hairline `#2a323c`, text
`#e3e8ef`, dim `#93a0b0`, accent `#6f9dff`. Neutrals are biased cool toward the
accent rather than pure grey. Semantic colours are separate from the accent:
published `#4bbf92`, draft `#cf9a4e`, destructive `#e2606b`.

Typography is the system UI stack, with `ui-monospace` for data that lines up
in columns (dates, sizes, filenames, counts) plus `font-variant-numeric:
tabular-nums`. No webfont: the point of the decoupling is that admin pages
download nothing the theme owns.

### Chrome: one admin-owned top bar

A single sticky bar: wordmark (`{app.name}` + `admin`), the five section links
(Dashboard, Posts, Authors, Themes, Database), then `View site ↗`, the signed-in
email, and Log out. The active section carries an accent underline. Section
links keep the existing `Route::has()` guards from the current dashboard so the
unbuilt Payments section appears only once its routes exist.

Rejected: a left sidebar (a second axis of chrome for five links) and a
dashboard-hub-only model (every screen a round trip through the dashboard).

Log out moves out of the dashboard body into this bar.

### Post editor: admin-styled surface, themed preview in an isolated frame

Typing happens on an admin-styled dark CKEditor surface. **Preview** replaces
the editor with an `<iframe>` whose `srcdoc` contains the tokens `<style>`
block, the active theme's CSS URLs, `article.css`, and the article markup
(`journal-hero` + `article-paper > ck-content`) built from the live editor data.
Inside that frame the theme applies in full and correctly; outside it, the
admin page stays token-free.

Rejected: keeping the typing surface themed (permanent partial coupling on the
one screen with the most CSS surface area), and a permanent side-by-side split
(crowds the form and needs its own phone fallback).

Consequence: the `.article-paper` CKEditor chrome overrides move from
`article.css` to `admin.css`, restated against `--adm-*` and scoped to the admin
editor wrapper. `article.css` is left as pure article-rendering CSS, which is
what the public page and the preview frame both want.

### Component set

Every screen is built from: panel (with head/body), row list, stat tile, status
pill, button (default / primary / danger, plus a small size), form field, filter
chip, tab strip, danger panel. Posts and Authors share one row partial
(`resources/views/admin/partials/_row.blade.php`) differing only in avatar
shape.

## Screens

| Screen | Change |
| --- | --- |
| Login | Centered card, no top bar, wordmark above the form. Validation error states what to fix. |
| Dashboard | Link list becomes four stat tiles (posts + draft count, authors, backups + age, active theme) over a recent-posts panel and a maintenance action row. |
| Posts index | Inline `style="…"` attributes become row classes. Status becomes a pill, dates and author become mono/tabular, Edit and Delete sit inline. Filter chips (All / Drafts / Published) and a title filter in the panel head. |
| Post create/edit | Two columns: content left (locale tab strip EN/RO replacing the two stacked fieldsets, title, slug, subtitle, editor), metadata in a sticky right rail (publishing, card image, delete). |
| Authors index | Same row partial as Posts, round avatar. |
| Authors create/edit | Admin form fields; no other change. |
| Themes | Card grid on admin styling; screenshots unchanged; active theme marked with a pill. |
| Database | Backups table in a panel; the prod → dev copy moves into its own danger-styled panel instead of sitting inline below the backup list. |

Behaviour is unchanged everywhere: same routes, same controllers, same
validation, same confirm dialogs, same CKEditor plugin set and upload endpoint.
This is a presentation change plus the preview-frame mechanism.

## Testing

Per the repo's TDD rule, each item below is a failing test first. Feature tests
unless noted.

1. An admin page loads `css/admin.css` and none of the active theme's CSS URLs.
2. An admin page's HTML contains no `--color-` custom property block (no
   `partials.tokens`), no `theme::cosmos` output and no theme JS `<script>`.
3. Rendering the same admin page under each installed theme produces identical
   HTML once the per-request CSRF token is normalised out — the theme pointer
   has no observable effect on the admin.
4. Admin pages render no site nav and no footer; public pages still render both
   (guards the removal of the `@unless` branch — existing `FooterTest` covers
   the public half).
5. The admin top bar lists exactly the sections whose routes exist, and marks
   the current one active.
6. `/admin/login` renders the admin layout with no top bar.
7. The post form's preview frame `srcdoc` includes the active theme's CSS URLs
   and `article.css` — i.e. the preview stays truthful after decoupling.
8. Unit: `admin.css` contains no `var(--color-`, `var(--font-`, `var(--radius`
   or other theme token reference.
9. Unit: `admin.css` overrides CKEditor's `--ck-color-base-*` chrome vars in
   `--adm-*` terms (the assertion moved out of `ArticleCssTest`).
10. Unit: `article.css` no longer declares CKEditor `--ck-color-base-*`
    overrides (the other half of that move).

Existing admin CRUD tests assert on text and routes rather than markup, so they
must keep passing untouched; if one breaks, the markup change went further than
intended.

## Documentation to update in the same change

Per `CLAUDE.md`:

- `README.md` — the new admin layout, `public/css/admin.css`, and the statement
  that themes do not apply to the admin module.
- `public/themes/AUTHORING.md` — theme authors no longer need to consider admin
  screens; the shared-view contract covers public views only.
- No `theme.json` or `theme.schema.json` change: no theme asset, token or view
  slot is added or removed.

## Out of scope

- Payments (Plan 3) admin screens — not built yet; the top bar only reveals the
  link once its routes exist.
- Any change to public-facing pages beyond removing the dead `@unless` branch in
  `layouts/app.blade.php`.
- A light/dark toggle for the admin. Dark neutral is fixed.
- Pagination, bulk actions or search on the index screens beyond the client-side
  filter chips shown in the mock.
