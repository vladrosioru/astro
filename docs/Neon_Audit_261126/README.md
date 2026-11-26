# AstroTherapia — Comprehensive Project Audit (`Neon_Audit_261126`)

**Audit Date:** 2026-11-26  
**Repository:** `Astrotherapia_AI` (HEAD `e7bee73`)  
**Runtime Observed Locally:** PHP `8.4.23` (CLI)  
**Target Stack:** Laravel 13 (`v13.23.0` locked / `11.54.0` installed in local `vendor/`), PHPUnit 12, Vanilla CSS + JS (Zero-Node / cPanel shared hosting)

---

## Executive Summary

AstroTherapia is a well-architected, Node-free Laravel 13 marketing and Journal platform with a distinctive dark celestial aesthetic, a clean design-token contract (`public/themes/`), pure-PHP PDO backup/restore tooling built for shell-less cPanel hosting, and a strong intellectual brand foundation (*"Understanding the Why Behind Your Choices"*).

Between July and November 2026, significant SEO and editorial engineering shipped (brand `<title>` tags, meta descriptions, canonical/hreflang links, dynamic `sitemap.xml`, JSON-LD schemas, a 1200×630 social card generator, logo optimization from 419 KB to 55.7 KB, and deep specs for a 25-archetype Jungian system and AstroDynamics). However, this audit identified **critical technical regressions** (stale vulnerable dependencies failing `composer audit`, Closure routes breaking `route:cache`, silent wipe of post SEO fields in the admin editor, missing `posts.social_image` rewrite on `PROD→DEV`, and a flaky test in full-suite runs) alongside **high-impact conversion blockers** (an above-the-fold hero dead-end with no CTAs, an invisible practitioner bio/credential, hardcoded English copy across `/ro`, zero pricing transparency, and zero audience capture).

---

## Baseline Diagnostics (Observed 2026-11-26)

| Check | Command | Result | Notes |
|---|---|---|---|
| **Code Style** | `vendor/bin/pint --test` | **Passed** | Zero formatting violations |
| **Test Suite (Full)** | `php artisan test --compact` | **375 passed, 1 failed** (1,376 assertions, 63.85s) | `AdminDatabaseRestoreTest::test_a_backup_without_row_counts_says_so_and_stays_restorable` failed with `404` in full run, **passed in isolation** (`1 passed, 2 assertions`) |
| **Security Audit** | `composer audit --no-dev --locked` | **Failing** | **21 unignored advisories** across 8 packages + **12 ignored advisories** across 2 packages |
| **Local `vendor/` Sync** | `composer outdated --direct` | **Out of Sync** | Local `vendor/` holds `laravel/framework 11.54.0` / `phpunit/phpunit 11.5.55` / `intervention/image 4.1.4`, diverging from `composer.lock` (`v13.23.0` / `12.5.33` / `4.2.0`) |

---

## Overall Health Scorecard

| Dimension | Score | Verdict |
|---|---|---|
| **Security & Supply Chain** | **4.5 / 10** | Clean auth/XSS baseline (HTMLPurifier + `serializable_classes => false`), but blocked by 21 unignored production CVEs, unthrottled `/admin/login`, missing HTTP security headers, and unprotected `public/storage/`. |
| **Backend & Data Integrity** | **6.5 / 10** | Pure-PHP PDO backup/restore and slug locking are thoughtful, but Closure routes break `route:cache`, admin editor wipes `seo_title`/`seo_description`, and `social_image` is missed during `PROD→DEV` sync. |
| **Performance & Scalability** | **6.0 / 10** | Vanilla CSS/JS keeps runtime light, but `SiteSetting::current()` runs 6–10× per request, `BlogController::show()` loads all posts into memory, and `public/vendor/ckeditor/ckeditor5.css` (217 KB) ships on every public article. |
| **Frontend & Accessibility** | **5.5 / 10** | Responsive carousels and token decoupling are clean, but Home has duplicate `<main>` tags, Services tabs/dots lack full ARIA semantics, `user-select: none` blocks heading selection, and `+60%` phone text scaling causes cramped wrapping. |
| **DevOps & Shared Hosting** | **7.5 / 10** | WAF-aware CI/CD cascade (`lint → test → security → build → dev → approve → prd`) is mature, though `deploy.php` masks `route:cache` errors and `make-archive.php` leaks internal markdown/agent folders into `app.zip`. |
| **Concept & Brand Positioning** | **8.5 / 10** | Agency-first *"why, not what"* philosophy, 5-pillar house mapping, Jungian shadow-paired archetypes, and evidence-respecting Journal voice create genuine differentiation. |
| **Visual Look & Feel** | **7.0 / 10** | Memorable orbiting-planet stage and teal/gold emblem, weakened by a gold-vs-icy-blue palette split, duplicate hero logo with zero CTAs, and an abrupt `Helvetica` font fallback inside Journal articles. |
| **Marketing & Conversion Funnel** | **4.0 / 10** | Zero lead capture (no newsletter/quiz), invisible practitioner credential (Faculty of Astrological Studies, London), hidden pricing, generic contact form, and English-only `/ro` body content. |

---

## Top 10 Highest-Leverage Actions

### Technical Priorities
1. **Unblock CI Security & Sync Dependencies:** Run `composer update` (aligning `laravel/framework` to `13.44+`, Symfony `7.4.24+`, `league/commonmark`, `intervention/image 4.4+`), remove stale `audit.ignore` entries from `composer.json`, and run `composer install` locally.
2. **Fix `route:cache` & Legacy Redirect Codes:** Convert the 5 Closure routes in `routes/web.php` to `Route::redirect(..., 301)` / `RedirectController` so `public/deploy.php` stops failing `route:cache`, and fix `deploy.php` exit/marker ordering.
3. **Stop Silent SEO Data Loss in Admin:** Add `seo_title` and `seo_description` inputs to `resources/views/admin/posts/_form.blade.php` so saving a post no longer overwrites those columns with `null`.
4. **Harden Admin Auth, Restore, & HTTP Headers:** Add `throttle:5,1` on `POST /admin/login`, enforce server-side `confirm_host` + `view:clear` on restore, add security/cache/compression headers in `public/.htaccess`, and disable PHP execution inside `public/storage/.htaccess`.
5. **Eliminate Per-Request DB Overhead & Heavy Article CSS:** Memoize `SiteSetting::current()` (and cache array payload safely), replace `BlogController::show()` $O(N)$ neighbor scan with two `limit(1)` queries, fix `Post::translation()` N+1, and replace the 217 KB `ckeditor5.css` on public posts with a ~3 KB content stylesheet.

### Marketing, UX & Concept Priorities
6. **Restore Above-the-Fold Hero CTAs:** Replace the duplicate 150×150px `logo-nav.png` in `theme_solarsystem/views/hero.blade.php` with primary/secondary CTAs (*Explore Services / Take the Archetype Quiz* and *Read the Journal*).
7. **Surface Practitioner Authority Immediately:** Add Andrei's story, portrait/emblem, and **Associate Member of the Faculty of Astrological Studies (London, UK)** credential to About (and a compact trust strip on Home/Services).
8. **Publish Entry Pricing & Upgrade Contact into a Session Request Flow:** Add transparent price ranges ("Sessions from …") on Services/FAQ and add a service picker + optional birth-data fields + visible direct email on Contact.
9. **Deploy Audience Capture (Newsletter + 5-Hero Archetype Quiz):** Integrate Brevo email capture in the footer and at the end of every Journal post, paired with a 5-hero Archetype Quiz (*Phoenix, Guardian, Strategist, Builder, Healer*).
10. **Resolve the Romanian (`/ro`) Credibility Gap:** Either translate static pages (`lang/ro/`) with an `EN | RO` switcher or temporarily remove `/ro` static pages from `sitemap.xml`/`hreflang` until Romanian copy is ready.

---

## Report Index

1. [**01 — Deep Technical Audit**](01-technical-audit.md)  
   Full inventory of security advisories, functional bugs, data-integrity gaps, performance bottlenecks, accessibility violations, design-token drift, DevOps hazards, and modernization opportunities.
2. [**02 — Technical Implementation Plan**](02-technical-implementation-plan.md)  
   5-phase, TDD-first engineering roadmap (`Red → Green → Refactor`) specifying exact files, test cases, effort estimates, cache-safety constraints, and CI verification gates.
3. [**03 — Concept, Brand, Look-and-Feel & Marketing Report**](03-concept-marketing-ux-report.md)  
   Strategic evaluation of positioning, visual identity, UX/conversion flow, copy quality, updated SWOT (November 2026), and Tier 1–3 growth recommendations.
