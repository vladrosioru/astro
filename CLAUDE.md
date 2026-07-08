# Project Rules

Persistent rules for working in this repository. Follow them on every change.

## Test-driven development

**No production code without a failing test first.** Every feature, bug fix,
and behavior change starts with a test that fails, then the minimal code to
pass it (see the `superpowers:test-driven-development` skill for the full
discipline). Repo-specific points:

1. **Red.** Add one PHPUnit test in `tests/Feature` or `tests/Unit` for the
   single behavior you're adding or fixing. Run it scoped
   (`php artisan test --filter=test_name`) and confirm it fails for the
   expected reason, not a typo or setup error.
2. **Green.** Write the minimal code to pass, then run the **full** suite
   (`php artisan test`) — a new test passing must not break unrelated
   coverage. This repo has cross-cutting state (`SiteSetting.sections`
   visibility toggles, the active theme, locale) that many tests assert
   against from different angles — e.g. a section being disabled must hold
   for its route (404), its nav link, and any in-page CTAs that link to it.
3. **Refactor** only once green, without changing behavior.

Bug fixes always get a regression test that reproduces the bug before the fix
lands. Exceptions (throwaway prototypes, generated code, config-only changes)
— ask before skipping.

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
