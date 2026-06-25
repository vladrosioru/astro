# Backlog — recommendations & follow-ups

A living list of improvements and ideas noted during development. Not part of the
committed 4-plan scope; pull items from here into a future plan when prioritized.

Status: 🔵 idea · 🟡 planned · 🟢 done

| # | Area | Recommendation | Why | Status |
|---|------|----------------|-----|--------|
| 1 | Admin / Blog UX | Auto-generate the **slug** from the title (e.g. "Blog article 1" → `blog-article-1`), still allowing manual override. Slug-ify per locale. | Right now the slug is a manual field; leaving it blank produces a post with no working URL. Auto-fill removes a common foot-gun. | 🔵 idea |
| 2 | Admin / Blog UX | Auto-derive **excerpt** from the first ~160 chars of the body when left empty. | Saves editors a step; ensures the blog list always has a teaser. | 🔵 idea |
| 3 | Repo hygiene | Remove unused **Node frontend scaffolding** (`package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `resources/js`, `resources/css`) shipped by the Laravel scaffold. | The project is intentionally Node-free (plain CSS + Trix). These files are dead weight and can mislead. | 🔵 idea |
| 4 | Security / maintenance | Revisit the Laravel version: the advisory **block** was disabled to install latest 11.x (issues are debug-mode-only, prod runs `APP_DEBUG=false`). Upgrade to a patched release (or Laravel 12) when available. | Keep dependencies on a non-flagged version once one exists. | 🔵 idea |
| 5 | Security | Add optional **2FA** for admin login (noted as "later" in the spec). | Stronger admin account protection. | 🔵 idea |

## How to use this file
- Add a row whenever a "nice-to-have" or recommendation surfaces mid-build.
- Keep entries short; the *Why* column is the value, not the implementation detail.
- When an item is scheduled, write a spec/plan for it and flip status to 🟡, then 🟢 when shipped.
