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
| 5 | Maintenance | Consider **Laravel 13** (13.x is out; lock is on 12.63.0). Also `laravel/tinker` 3.x and `intervention/image` 4.2. No security driver — 12.x is supported and `composer audit --no-dev` is clean. Prod/CI PHP is 8.4, so no platform blocker. | Avoid a multi-major jump later; do it deliberately, not under advisory pressure. | 🔵 idea |
| 6 | Admin / Editor | Premium CKEditor features (Import from Word, Export PDF/Word, CKBox, merge fields, AI, comments/track-changes) are **paid** and excluded. Revisit only if a real need + budget appears. | Sets expectations vs. the CKEditor marketing demo. | 🔵 idea |
| 7 | Security / maintenance | Upgrade off the flagged Laravel 11 line — done 2026-07-08 by moving to **Laravel 12** (`^12.0`, locked 12.63.0), clearing three advisories with no patched 11.x release (notably CVE-2026-48019, CRLF injection in the default `email` rule, reachable from the contact form). `composer audit --no-dev` is a **blocking** CI gate. | Dependencies off a flagged version; regressions now fail the pipeline instead of shipping. | 🟢 done |
| 8 | Repo hygiene | Drop the stale `config.policy.advisories.block: false` override from `composer.json` — a leftover from the Laravel 11 era. | Re-arms Composer's install-time advisory guard, so a flagged package fails locally at `composer require` instead of only in CI. | 🟢 done |
| 9 | Repo hygiene | Remove unused **Node frontend scaffolding** (`package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `resources/js`, `resources/css`) shipped by the Laravel scaffold. Also removed the unrouted `welcome.blade.php` (sole `@vite` consumer), the ported-away `resources/theme_solarsystem/` reference, and de-Noded the `composer.json` `dev` script. | The project is intentionally Node-free (plain CSS). These files were dead weight and could mislead. | 🟢 done |
| 10 | Admin / Editor | CKEditor is **self-hosted** from the npm package's `dist/browser/ckeditor5.umd.js` (distribution channel `"sh"`), valid with the GPL key — no CDN, no Node build. When upgrading CKEditor, re-pull the `dist/browser/` UMD from the npm tarball, not the CDN. | Earlier attempts failed: the GPL key is invalid on the CDN/"cloud" build. Records the constraint so a future upgrade doesn't regress to the CDN. | 🟢 done |

## How to use this file
- Add a row whenever a "nice-to-have" or recommendation surfaces mid-build.
- Keep entries short; the *Why* column is the value, not the implementation detail.
- When an item is scheduled, write a spec/plan for it and flip status to 🟡, then 🟢 when shipped.
