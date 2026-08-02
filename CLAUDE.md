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

## Live infrastructure: ask first

**Never connect to the live host or take an outward-facing action without
explicit, per-action confirmation** — no FTP/FTPS, SSH, cPanel, SMTP or live-URL
probing, not even read-only checks, and not even when credentials are at hand
(they are in the git-ignored `docs/DEPLOY-SECRETS.local.md` and in GitHub
environment secrets).

Diagnose from evidence the owner supplies — logs, screenshots, output of commands
they ran. When a direct check would genuinely help, *offer* it and wait for an
explicit yes. Deploys are pushed manually by the owner: commit the code and stop
there. Approval for one probe does not carry to the next.

Operational facts already learned about the live host — panel URL, the WAF's
`Accept`-header requirement, how contact-form mail is verified — are written down
in [`docs/OPERATIONS.md`](docs/OPERATIONS.md); read that before proposing a probe.

## Never point a bare `curl` at the live site

The host's WAF (LiteSpeed + Imunify360) does not answer plain `curl`. It has two
distinct failure modes, both already paid for once, so any `curl` at
`astrotherapia.com` / `dev.astrotherapia.com` — in CI, in a script, or in a
one-off probe the owner approved — must satisfy **all four** rules:

1. **Send a browser `User-Agent` *and* an explicit `Accept`.** No `Accept` header
   from a datacenter IP is answered `415 Unsupported Media Type`; a bot-shaped
   UA gets the challenge page. Use the `$UA` / `Accept: text/html,…` pair the
   workflows already define.
2. **Never trust the status code.** The WAF's "One moment, please…" JS challenge
   is served as **HTTP 200**, so `curl -fsS` and `-w "%{http_code}"` both call it
   success. Always write the body somewhere and grep it — for the command's own
   expected marker, or at minimum for `One moment, please`.
3. **Keep a cookie jar and retry.** The challenge sets a cookie and its own JS
   reloads after 5 s; the reload passes. A single challenged request is not a
   verdict — `-c/-b <jar>` plus a few spaced retries is. Only give up (loudly)
   after the retries.
4. **For deploy hooks, don't hand-roll any of it** — call
   [`.github/scripts/run-deploy-hook.sh`](.github/scripts/run-deploy-hook.sh),
   which does all three above and fails the step unless the hook's own success
   marker comes back.

`tests/Feature/DeployHookVerificationTest.php` and
`tests/Feature/DeployHookRetryTest.php` fail the suite if any of this is undone.
Details and the measurements behind it: [`docs/OPERATIONS.md`](docs/OPERATIONS.md).

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
