# Project Rules

Persistent rules for working in this repository. Follow them on every change.

## Documentation upkeep

1. **Keep the style map in sync.** Whenever a style change occurs — editing
   `public/css/*.css`, the design tokens in `config/tokens.php`, a theme set in
   `config/themes.php`, the `@font-face`/fonts in `public/css/fonts.css`, or the
   hero/stage markup that consumes those styles — update
   [`docs/theme-style-map.json`](docs/theme-style-map.json) in the same change so it
   stays an accurate map of tokens → element types, stage literals, and fonts.

2. **Keep the infrastructure docs in sync.** Whenever infrastructure changes —
   routes, middleware, models/migrations, artisan commands, dependencies, the
   theming/token mechanism, build/deploy setup, or project layout — update
   [`README.md`](README.md) (and any affected file under `docs/`) in the same change.

> Rule of thumb: if a change would make a statement in `README.md` or
> `docs/theme-style-map.json` wrong, fix the doc as part of that change — never as
> a follow-up.
