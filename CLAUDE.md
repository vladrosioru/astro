# Project Rules

Persistent rules for working in this repository. Follow them on every change.

## Documentation upkeep

1. **Keep each theme's manifest in sync.** Themes are self-contained packages
   under `public/themes/theme_<name>/`, each described by its own
   `theme.json` (token names/types/roles/values, fonts, ordered `assets.css`/`js`,
   and `views` slots). Whenever you change a theme's CSS/JS, its
   `@font-face`/fonts, the token names in `config/tokens.php`, or the hero/stage
   markup that theme ships — update that theme's `theme.json` in the same change so
   it stays an accurate map of tokens → element types and assets. `theme.json` is
   both the app's loader manifest and the portable spec handed to other apps to
   author a compatible theme; it must keep validating against
   [`public/themes/theme.schema.json`](public/themes/theme.schema.json) (enforced by
   `tests/Unit/ThemeJsonContractTest.php`).

2. **Keep the infrastructure docs in sync.** Whenever infrastructure changes —
   routes, middleware, models/migrations, artisan commands, dependencies, the
   theming mechanism (`ThemeManager`, `ThemeServiceProvider`, the `theme::`
   namespace, the `SiteSetting.theme` pointer, the admin Themes picker),
   build/deploy setup, or project layout — update
   [`README.md`](README.md) (and any affected file under `docs/`) in the same change.

3. **Keep the theme-authoring guide in sync.** The CSS class vocabulary in
   [`public/themes/AUTHORING.md`](public/themes/AUTHORING.md) is the contract
   between the shared views and every theme's CSS. Whenever you change the markup
   of a shared view a theme must style (`partials/nav.blade.php`, `blog/index`,
   `blog/show`, the `pages/*`), the token registry (`config/tokens.php`), or the
   `theme.json` shape, update `AUTHORING.md` and the inline field docs in
   [`public/themes/theme.schema.json`](public/themes/theme.schema.json) in the
   same change.

> Rule of thumb: if a change would make a statement in `README.md`, `AUTHORING.md`,
> or the active theme's `theme.json` wrong, fix the doc as part of that change —
> never as a follow-up.
