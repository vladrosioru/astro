# Backlog — recommendations & follow-ups

A living list of improvements and ideas noted during development. Not part of the
committed 4-plan scope; pull items from here into a future plan when prioritized.

Status: 🔵 idea · 🟡 planned · 🟢 done

| # | Area | Recommendation | Why | Status |
|---|------|----------------|-----|--------|
| 1 | Admin / Blog UX | Auto-generate the **slug** from the title (e.g. "Blog article 1" → `blog-article-1`), still allowing manual override. Slug-ify per locale. | Right now the slug is a manual field; leaving it blank produces a post with no working URL. Auto-fill removes a common foot-gun. | 🔵 idea |
| 2 | Admin / Blog UX | Auto-derive **excerpt** from the first ~160 chars of the body when left empty. | Saves editors a step; ensures the blog list always has a teaser. | 🔵 idea |
| 3 | Security | Add optional **2FA** for admin login (noted as "later" in the spec). | Stronger admin account protection. | 🔵 idea |
| 4 | Theme / UX | Home hero CTA defaults to `/en/contact`; if an admin disables the Contact section the CTA leads to a 404. Make the hero CTA aware of section visibility (or validate the URL) when the hero becomes admin-editable. | Avoid dead CTA when sections are toggled. | 🔵 idea |
| 5 | Maintenance | Bump `phpunit/phpunit` to `^12`. Laravel 13's upgrade guide recommends it; the lock is on 11.5.55, which the framework still accepts (`^11.5.50 \|\| ^12.5.8 \|\| ^13.0.3`), so this is not urgent. No test file uses doc-comment metadata (`@test`, `@dataProvider`) — all 51 use `test_*` naming, which PHPUnit 12 keeps. | Stay on the version Laravel tests against; the migration cost is near zero today and grows with the suite. | 🔵 idea |
| 6 | Admin / Editor | Premium CKEditor features (Import from Word, Export PDF/Word, CKBox, merge fields, AI, comments/track-changes) are **paid** and excluded. Revisit only if a real need + budget appears. | Sets expectations vs. the CKEditor marketing demo. | 🔵 idea |
| 7 | Maintenance | Upgrade to **Laravel 13** — done 2026-08-01 (`^13.0`, locked 13.23.0), together with `laravel/tinker` `^3.0` (2.x capped at illuminate `^12` and was the only blocker) and `intervention/image` `^4.2`. No app code changed: the guide's high/medium-impact items don't apply here (no `VerifyCsrfToken`/`withoutMiddleware` references, no `upsert`, no cache or queue usage, no `array_first`/`array_last` helpers, and `config/cache.php`/`config/session.php` already define the prefix and cookie name so the renamed framework fallbacks never fire). Adopted the new `cache.serializable_classes => false` default. All 271 tests pass unchanged. | Avoided a future double-major jump; done deliberately with a clean `composer audit`, not under advisory pressure. | 🟢 done |
| 8 | Security / maintenance | Upgrade off the flagged Laravel 11 line — done 2026-07-08 by moving to **Laravel 12** (`^12.0`, locked 12.63.0), clearing three advisories with no patched 11.x release (notably CVE-2026-48019, CRLF injection in the default `email` rule, reachable from the contact form). `composer audit --no-dev` is a **blocking** CI gate. | Dependencies off a flagged version; regressions now fail the pipeline instead of shipping. | 🟢 done |
| 9 | Repo hygiene | Drop the stale `config.policy.advisories.block: false` override from `composer.json` — a leftover from the Laravel 11 era. | Re-arms Composer's install-time advisory guard, so a flagged package fails locally at `composer require` instead of only in CI. | 🟢 done |
| 10 | Repo hygiene | Remove unused **Node frontend scaffolding** (`package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `resources/js`, `resources/css`) shipped by the Laravel scaffold. Also removed the unrouted `welcome.blade.php` (sole `@vite` consumer), the ported-away `resources/theme_solarsystem/` reference, and de-Noded the `composer.json` `dev` script. | The project is intentionally Node-free (plain CSS). These files were dead weight and could mislead. | 🟢 done |
| 11 | Admin / Editor | CKEditor is **self-hosted** from the npm package's `dist/browser/ckeditor5.umd.js` (distribution channel `"sh"`), valid with the GPL key — no CDN, no Node build. When upgrading CKEditor, re-pull the `dist/browser/` UMD from the npm tarball, not the CDN. | Earlier attempts failed: the GPL key is invalid on the CDN/"cloud" build. Records the constraint so a future upgrade doesn't regress to the CDN. | 🟢 done |

## How to use this file
- Add a row whenever a "nice-to-have" or recommendation surfaces mid-build.
- Keep entries short; the *Why* column is the value, not the implementation detail.
- When an item is scheduled, write a spec/plan for it and flip status to 🟡, then 🟢 when shipped.
