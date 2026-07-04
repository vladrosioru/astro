# Site Architecture — astrotherapia.com

A living document describing the intended information architecture (pages +
navigation) for the site. Edit this file directly whenever the structure
changes — it's meant to grow with the project, not be replaced.

Status: 🔵 idea · 🟡 planned · 🟢 built

## Navigation layout

Centered brand always links back to Home: the logo image with the
**ASTROTHERAPIA** eyebrow wordmark stacked beneath it (both link Home). Two
top-level menu items sit to its left, two to its right. **About** and
**Services** are intended as dropdown (multi-level) menus:

```
About ▾        Articles      [ LOGO → Home ]      Services ▾        Contact
 ├ Concept                   ASTROTHERAPIA          ├ (service category/item)
 └ About astrology            → Home                ├ (service category/item)
                                                     └ ...
```

**Currently built** as flat links (no dropdowns yet):
`About · Articles  |  [logo + ASTROTHERAPIA]  |  Services · Contact`.
The old standalone **Home** text link is gone — the centered brand is the link
Home. The dropdowns for About / Services remain a future step (see open
questions).

**Phone (≤720px):** the two link groups collapse behind a "≡" hamburger, so
the closed bar shows only the brand; tapping it opens all four links (About,
Articles, Services, Contact, in that order) stacked in one column below the
brand, over a dimming scrim. Pure CSS, no JS — see `AUTHORING.md`'s nav
contract for the mechanism.

## Pages

| # | Page | Type | Description | Status |
|---|------|------|--------------|--------|
| 1 | **Home** | Landing | Centered logo/image; hero content. Logo is the permanent link back to Home from every other page. | 🟢 built |
| 2 | **About** | Nav dropdown (parent) | Top-level menu item; opens a dropdown to its two sub-pages below. Not necessarily a page of its own — see open question. | 🔵 idea |
| 2a | ↳ **Concept** | Single page | Presentation page about astrology and the idea behind AstroTherapy. | 🟡 planned — maps to the existing `/about` page/route; content already matches this framing |
| 2b | ↳ **About astrology** | Single page | Presentation page about astrology in general (distinct from the AstroTherapy-specific "Concept" page). | 🔵 idea — no route/controller yet |
| 3 | **Articles** | Blog listing + post | One page listing articles, blog-style, plus individual article pages. | 🟢 built — the existing Blog feature relabelled **Articles** and served at `/{locale}/articles` (route names stay `blog.*`); legacy `/{locale}/blog` 301-redirects |
| 4 | **Services** | Nav dropdown → single page | Listing of astrological services available. The dropdown lists service categories/items; each entry links to a section on one single Services page (not separate pages). | 🟡 planned — route + page now exist (`PageController@services`, blank placeholder). Dropdown + static service content still to come |
| 5 | **Contact** | Single page | Contact form. | 🟢 built |

## Current implementation vs. target

| Nav slot (target) | Route today | Controller | Gap |
|---|---|---|---|
| About ▾ (parent) | *(none)* | *(none)* | New dropdown trigger in nav; see open question on whether it needs its own landing content |
| ↳ Concept | `/{locale}/about` | `PageController@about` | Move under the About dropdown as "Concept"; content already fits |
| ↳ About astrology | *(none)* | *(none)* | New route + controller + view needed |
| Articles | `/{locale}/articles` (+ `/blog` redirect) | `BlogController@index` / `@show` | **Done** — nav label + URL are "Articles"; route names stay `blog.*`, controller/views unchanged. Dropdown not planned for Articles |
| Services ▾ | `/{locale}/services` | `PageController@services` | Route + blank page **built**. Still needs a dropdown in nav linking to in-page sections and static service content (see Roadmap) |
| Contact | `/{locale}/contact` | `PageController@contact` | None — matches target |

Current nav order in [nav.blade.php](../resources/views/partials/nav.blade.php)
is `About · Articles  ·  [logo + ASTROTHERAPIA]  ·  Services · Contact`
(flat links, no dropdowns). The standalone "Home" text link was removed; the
centered brand (logo + eyebrow) is the link Home. The remaining step toward the
target is adding dropdown behavior:
`About ▾ | Articles  ·  [brand]  ·  Services ▾ | Contact`.

## Roadmap / future scope

- **Services content:** static markup for now. Later, this may grow
  interactive elements — planners and POST-submitted forms (e.g. booking or
  consultation request flows) — which will likely need a `Service`-style
  model and dedicated controller once that work is scheduled.

## Open questions

- **About parent page:** does clicking "About" itself (not a dropdown item)
  need to go anywhere (e.g. default to Concept), or is it purely a
  hover/click dropdown trigger with no own URL?
- **Dropdown implementation:** confirm the multi-level nav (About ▾,
  Services ▾) is built as a CSS/JS hover-or-click dropdown in
  `nav.blade.php`, consistent across themes (see `AUTHORING.md`'s nav
  contract) rather than a per-theme custom widget.
- **Services list:** what are the actual service
  categories/items to show as dropdown entries and page sections? Needed
  before building the static content.

## How to use this file

- Update the status column as pages move from idea → planned → built.
- Add new open questions as they come up; remove them once answered/decided.
- When architecture changes affect shared views or the theme contract, also
  update `README.md` and `public/themes/AUTHORING.md` per the rules in
  [CLAUDE.md](../CLAUDE.md).
