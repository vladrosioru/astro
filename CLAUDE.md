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

## Run the whole CI gate before committing

`php artisan test` is not the gate — it is one of three jobs. Run **all** of them
locally before every commit, in the order CI does, and don't commit on a red one:

```
vendor/bin/pint --test     # style; `vendor/bin/pint` to fix, then re-run --test
php artisan test           # full suite
composer audit --no-dev    # blocking advisory scan on prod dependencies
```

`lint` runs first in the pipeline and `test` / `security` need it, so a style nit
in a test file fails the run before a single test executes and costs a whole
round trip. Pint reformats new test files (import order, `!` spacing) far more
often than it touches application code — new tests are exactly where this bites.

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

The host's WAF (LiteSpeed + Imunify360) does not answer plain `curl`, and it has
cost three separate round trips already. **Nothing may speak HTTP to
`astrotherapia.com` / `dev.astrotherapia.com` directly** — not CI, not a script,
not a one-off probe the owner approved. There are exactly two doors, and new code
uses one of them rather than rolling its own:

| Need | Use |
|---|---|
| Call a deploy hook (`extract.php`, `deploy.php`) | [`.github/scripts/run-deploy-hook.sh`](.github/scripts/run-deploy-hook.sh) `URL MARKER TOKEN [MAX_TIME]` |
| Any other request (health check, smoke test, probe) | [`.github/scripts/fetch-site.sh`](.github/scripts/fetch-site.sh) `URL OUT_FILE [MAX_TIME]` — prints the status code, body goes to `OUT_FILE` |

FTPS uploads are the one exception: different protocol, no WAF in front of it.

Both scripts encode the same four hard-won rules. If you ever must write a new
request path, it satisfies all four or it will fail in CI and waste a deploy:

1. **Browser `User-Agent` *and* explicit `Accept`.** No `Accept` from a
   datacenter IP is answered `415 Unsupported Media Type`; a bot-shaped UA is
   always challenged.
2. **One cookie jar, shared by every request in the job.** This is what actually
   clears the challenge — `-c/-b "$WAF_COOKIE_JAR"`. A fresh curl per request is
   challenged from scratch, which is how a 12-attempt retry loop produced 12
   challenge pages while a cookie-carrying call in the same minute went straight
   through.
3. **Never trust the status code.** The "One moment, please…" challenge is an
   **HTTP 200**, so `curl -fsS` and `-w "%{http_code}"` both call it success.
   Write the body to a file and grep it — for the caller's own expected marker,
   or at minimum for `One moment, please`.
4. **Retry, then fail loudly.** One challenge is not a verdict; retry a few times
   spaced apart. But when the retries run out, exit non-zero — never let a
   challenged request pass for a healthy one.

`tests/Feature/DeployHookVerificationTest.php`, `DeployHookRetryTest.php` and
`SiteFetchRetryTest.php` fail the suite if any of this is undone — including a
test that greps both workflows for a raw `curl` at the site.
Measurements and history: [`docs/OPERATIONS.md`](docs/OPERATIONS.md).

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
