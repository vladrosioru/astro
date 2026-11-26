# 01 — Deep Technical Audit

**Audit Date:** 2026-11-26  
**Scope:** Application architecture, security & dependencies, functional bugs, database & backups, performance, frontend/a11y, CI/CD, and upgrade paths (Zero-Node / cPanel shared hosting).

---

## Severity Summary

| Severity | Count | Key Examples |
|---|---|---|
| **Critical** | 3 | 21 unignored production CVEs (`CVE-2026-114509` etc.) + local `vendor/` drift; Closure routes breaking `route:cache` in `deploy.php`; Admin editor wiping `seo_title`/`seo_description` on save |
| **High** | 11 | Unthrottled `/admin/login`; missing HTTP security headers & `public/storage/` PHP block; client-only restore confirm; `social_image` omitted on `PROD→DEV` rewrite; `SitemapController` ignoring hidden sections; `SiteSetting::current()` repeated queries; $O(N)$ queries & N+1 translations; Services dead contact CTAs; `blog/show.blade.php` CLS/LCP/null-author-pic bugs; fake `/ro` locale; a11y landmark/ARIA defects |
| **Medium** | 14 | Query-string deploy token fallback; missing admin `noindex`; Post validation gaps & unconditional social-image regeneration; `excerptFragments()` spacing/Unicode bugs; `journal-card.blade.php` missing author in text-only branch; `footer-social.js` `querySelector` single-match; flaky test isolation; `make-archive.php` leaking internal docs; 217 KB `ckeditor5.css` on public posts; unversioned JS assets; dead CSS & unused `public/img/about/` files; raw CSS values violating token contract; `seo-schema.blade.php` & `og:description` gaps; outdated theme screenshot |
| **Low** | 4 | `SESSION_ENCRYPT=false`; `published_date` hardcoded `2026-01-01..today` blocking scheduling; hardcoded `2025 - 2026` footer copyright; PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` deprecation prep |

---

## 1. Security & Dependency Supply Chain

### 1.1 [CRITICAL] 21 Unignored Production CVEs in `composer.lock` & Local `vendor/` Drift
- **Evidence:** Running `composer audit --no-dev --locked` against [composer.lock](../../composer.lock) reports **21 unignored advisories across 8 packages** (plus **12 ignored advisories** across 2 packages, out of 19 total ignore entries in [composer.json](../../composer.json#L72-L94)).
- **Key Unignored Production Advisories:**
  - `laravel/framework` (`v13.23.0` locked vs `13.44.1` available):
    - `CVE-2026-114509` (**critical**, `PKSA-b41t-wbzk-9q51`): TokenGuard authentication bypass through non-string API credentials (`>=13.0.0,<13.27.0`).
    - `CVE-2026-114508` (**medium**, `PKSA-4hy6-m98j-dz9f`): Relationship inclusion triggering unauthorized data destruction (`>=13.0.0,<13.27.0`).
    - `CVE-2026-128083` (**low**) & `CVE-2026-102279` (**low**, debug page XSS).
  - `symfony/http-foundation` (6 advisories): `CVE-2026-108082` (**high**, Cache-Control ReDoS in `HeaderUtils::split()`), `CVE-2026-135802` (client-chosen host URLs), `CVE-2026-135801` (path rule bypass via script-name suffix), `CVE-2026-121619`, `CVE-2026-133748`, `CVE-2026-118514`.
  - `symfony/routing` (3 advisories on `v7.4.13`): `CVE-2026-133746`, `CVE-2026-133754`, `CVE-2026-118516`.
  - `symfony/http-kernel` (2 advisories): `CVE-2026-133743`, `CVE-2026-123339`.
  - `symfony/mailer` (2 advisories): `CVE-2026-133751`, `CVE-2026-133755` (STARTTLS MitM vulnerabilities).
  - `symfony/mime` (1 advisory): `CVE-2026-45070` (**medium**, email header injection).
  - `league/commonmark` (`2.8.2` locked, 2 unignored advisories): `PKSA-m4t9-vsgq-8khn` (**high**, GFM table quadratic-time DoS) and `PKSA-m2dq-1fhr-29b1` (**medium**, `DisallowedRawHtml` bypass).
  - `league/flysystem` (1 advisory): `CVE-2026-102601` (**low**, malformed UTF-8 path normalizer bypass).
- **CI Impact:** `.github/workflows/cicd.yml:83` runs `composer audit --no-dev` as a **blocking** gate — builds are currently blocked until lockfile dependencies are updated.
- **Local Drift:** `composer outdated --direct` revealed local `vendor/` still contains `laravel/framework 11.54.0`, `phpunit/phpunit 11.5.55`, `laravel/tinker 2.11.1`, and `intervention/image 4.1.4`, meaning local tests do not execute against the `composer.lock` (`v13.23.0`) versions running in CI.

### 1.2 [HIGH] Unthrottled Admin Authentication
- **Evidence:** [routes/web.php](../../routes/web.php#L15-L17) and [AuthController.php](../../app/Http/Controllers/Admin/AuthController.php#L17-L33).
- **Issue:** While `POST /{locale}/contact` is protected by `throttle:5,1`, `POST /admin/login` (`admin.login.attempt`) has **no rate limiter**. On shared hosting, this exposes the admin login form to credential-stuffing and brute-force attacks.

### 1.3 [HIGH] Missing HTTP Security Headers & Upload Directory Execution Hardening
- **Evidence:** [public/.htaccess](../../public/.htaccess) and [public/deploy.php](../../public/deploy.php#L70).
- **Issue:**
  1. `public/.htaccess` sets only PHP handler + URL rewrite rules. It emits no `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, or `Strict-Transport-Security`.
  2. When `PUBLIC_DISK_IN_DOCROOT=true` (`.github/scripts/make-env.sh:46`), user-uploaded files live directly under `public/storage/media/` inside Apache/LiteSpeed's document root. Although uploads pass `image` validation and GD re-encoding (except animated GIFs, which write raw bytes in [AttachmentController.php](../../app/Http/Controllers/Admin/AttachmentController.php#L21-L30)), `public/storage/.htaccess` does not exist to disable script execution (`SetHandler none`, `php_flag engine off`, or `Options -ExecCGI`).

### 1.4 [HIGH] Client-Side-Only Database Restore Confirmation
- **Evidence:** [DatabaseController.php](../../app/Http/Controllers/Admin/DatabaseController.php#L78-L92) and [confirm.blade.php](../../resources/views/admin/database/confirm.blade.php#L60-L88).
- **Issue:** The confirmation view prompts the admin to *"Type `{host}` to confirm"*, enforced only by client-side JavaScript (`button.disabled = ...`). `DatabaseController::restore()` does not validate a submitted `confirm_host` field server-side. Additionally, neither `restore()` nor `pull()` calls `Artisan::call('view:clear')`, even though restoring `site_settings` can change the active `theme` pointer (`ThemeServiceProvider` caches view namespace/compiled Blade views).

### 1.5 [MEDIUM] Deploy Hook Query-String Token Fallback & Missing Admin `noindex`
- **Evidence:** [public/extract.php](../../public/extract.php#L45), [public/deploy.php](../../public/deploy.php#L50), [layouts/admin.blade.php](../../resources/views/layouts/admin.blade.php).
- **Issues:**
  - Both deploy hooks accept `$_GET['token']` as a fallback to `HTTP_X_DEPLOY_TOKEN`. Query parameters are logged in Apache/cPanel access logs and Referer headers. CI already sends `X-Deploy-Token`; remove the `$_GET['token']` fallback.
  - `layouts/admin.blade.php` lacks `<meta name="robots" content="noindex, nofollow">` as defense-in-depth alongside `public/robots.txt`.

### 1.6 [LOW] Unencrypted Database Sessions
- **Evidence:** [.github/scripts/make-env.sh](../../.github/scripts/make-env.sh#L36).
- **Issue:** `SESSION_ENCRYPT=false` leaves serialized session payloads readable in the shared-host MySQL `sessions` table. Setting `SESSION_ENCRYPT=true` protects admin session data at rest.

---

## 2. Functional Bugs & Contract Violations

### 2.1 [CRITICAL] Closure Routes Break `php artisan route:cache` in `public/deploy.php`
- **Evidence:** [routes/web.php](../../routes/web.php#L55) & [routes/web.php](../../routes/web.php#L75-L78), [public/deploy.php](../../public/deploy.php#L91-L118).
- **Issues:**
  1. Five routes in `routes/web.php` use Closures (`/`, `/{locale}/blog`, `/{locale}/blog/{slug}`, `/{locale}/articles`, `/{locale}/articles/{slug}`). Laravel's `route:cache` throws `LogicException: Unable to prepare route [...] for serialization. Uses Closure.`
  2. In `public/deploy.php`, `header('Content-Type: text/plain')` and `echo` run *before* `$run('route:cache')`. Because PHP already sent headers and output, calling `http_response_code(500)` inside `$run` triggers a warning / fails to change the HTTP status code, and line 118 still prints `"Deploy hook finished."` — causing `run-deploy-hook.sh` (which checks for `"Deploy hook finished."` on HTTP 200) to **pass silently even when `route:cache` or `migrate` throws**.
  3. The four legacy `/blog` and `/articles` redirects call `redirect(...)` with default status **302**, contradicting [README.md](../../README.md#L65) and [docs/SEO-AUDIT.md](../../docs/SEO-AUDIT.md#L138) which specify **301 Moved Permanently**.

### 2.2 [CRITICAL] Admin Post Editor Silently Wipes `seo_title` and `seo_description`
- **Evidence:** [PostController.php](../../app/Http/Controllers/Admin/PostController.php#L199-L200), [admin/posts/_form.blade.php](../../resources/views/admin/posts/_form.blade.php#L45-L85), [blog/show.blade.php](../../resources/views/blog/show.blade.php#L2-L7).
- **Issue:** `PostController::saveTranslations()` unconditionally sets `'seo_title' => $request->input("{$locale}_seo_title")` and `'seo_description' => $request->input("{$locale}_seo_description")`. However, `_form.blade.php` has **no inputs** for either field. Editing and saving any existing post in Admin overwrites its `seo_title` and `seo_description` with `null`.

### 2.3 [HIGH] `DatabaseRestoreService::rewriteMediaPaths()` Misses `posts.social_image`
- **Evidence:** [DatabaseRestoreService.php](../../app/Services/Database/DatabaseRestoreService.php#L106-L137), [2026_09_28_000001_add_social_image_to_posts_table.php](../../database/migrations/2026_09_28_000001_add_social_image_to_posts_table.php).
- **Issues:**
  1. `rewriteMediaPaths()` rewrites `authors.picture`, `media.url`, `posts.featured_image`, and `post_translations.body`, but **omits `posts.social_image`**. During `Copy PROD→DEV`, dev posts point `og:image` / `twitter:image` at nonexistent `/storage/media/social-*.jpg` paths on dev.
  2. When `$mediaFallbackUrl === null` (same-host restore), `rewriteMediaPaths()` still iterates every row across all four tables and executes hundreds of redundant no-op `UPDATE` queries instead of returning early.

### 2.4 [HIGH] `SitemapController` Ignores Section Visibility & `Post::published()` Scope
- **Evidence:** [SitemapController.php](../../app/Http/Controllers/SitemapController.php#L10-L28), [resources/views/sitemap.blade.php](../../resources/views/sitemap.blade.php).
- **Issues:**
  - Static routes (`about`, `services`, `contact`, `journal`) and blog posts are emitted in `sitemap.xml` even when disabled via `SiteSetting::current()->sectionVisible(...)`, feeding search engines URLs that return HTTP 404.
  - Queries `->where('status', 'published')` instead of `Post::published()` (missing `whereNotNull('published_at')`).
  - Sets `<lastmod>` on all static pages to `now()->format('Y-m-d')` on every request, signaling artificial daily updates.

### 2.5 [HIGH] Dead Contact CTAs & Missing Visibility Guards
- **Evidence:** [services.blade.php](../../resources/views/pages/services.blade.php#L228-L230), [services.blade.php](../../resources/views/pages/services.blade.php#L260-L262), [services.blade.php](../../resources/views/pages/services.blade.php#L279-L283).
- **Issue:** On Services, only the third CTA (`Schedule Your Session`) is guarded with `@if ($contactVisible)`. The first two `Book a Session` buttons render links to `/{locale}/contact` even when `contact` is disabled (causing 404s).

### 2.6 [HIGH] `blog/show.blade.php` Image Dimension Mismatch, LCP Regression & Null Author Picture
- **Evidence:** [blog/show.blade.php](../../resources/views/blog/show.blade.php#L86-L105), [PostController.php](../../app/Http/Controllers/Admin/PostController.php#L144), [AuthorController.php](../../app/Http/Controllers/Admin/AuthorController.php#L76-L78).
- **Issues:**
  - `featured_image` is square-cropped to **1200×1200**, but `blog/show.blade.php:88` declares `width="1200" height="630" loading="lazy"`. Incorrect intrinsic aspect attributes risk CLS while `loading="lazy"` delays the above-the-fold hero image (hurting LCP).
  - Line 104 renders `<img src="{{ asset($post->author->picture) }}">` unconditionally. Because `authors.picture` is nullable (`AuthorController` allows removing it), an author without a picture renders `<img src="https://astrotherapia.com/">`, triggering a broken image request to the homepage.

### 2.7 [MEDIUM] Post Validation Gaps, Orphaned Media & Duplicated Image Pipelines
- **Evidence:** [PostController.php](../../app/Http/Controllers/Admin/PostController.php#L60-L169), [AuthorController.php](../../app/Http/Controllers/Admin/AuthorController.php#L86-L118), [AttachmentController.php](../../app/Http/Controllers/Admin/AttachmentController.php#L15-L63), [SocialImageService.php](../../app/Services/SocialImageService.php).
- **Issues:**
  - `PostController` does not validate `status` (`Rule::in(['draft', 'published'])`), `author_id` (`exists:authors,id`), or ensure at least one locale title is supplied (allowing blank title-less posts that show `(untitled)`).
  - `postData()` calls `SocialImageService::generateForPost()` on **every** post update, even when `featured_image` is unchanged and `social_image` already exists — creating an orphaned `media/social-*.jpg` file and `Media` row on every edit.
  - Replacing or deleting a post's `featured_image`/`social_image` or author's `picture` never deletes old files from disk or `media`.
  - Identical GD decode/crop/alpha-preserving PNG-or-JPEG encode logic is duplicated across `PostController`, `AuthorController`, and `AttachmentController`.
  - `AttachmentController.php:30` unpacks `[$width, $height] = getimagesize(...)` without checking for `false` on malformed GIF uploads.
  - `published_date` validation hardcodes `after_or_equal:2026-01-01` and `before_or_equal:today`, preventing backdating pre-2026 archive posts or scheduling future posts.

### 2.8 [MEDIUM] Additional Functional, JS & Hygiene Defects
- **Contact Form Hardcoding ([PageController.php](../../app/Http/Controllers/PageController.php#L82-L106)):** Hardcodes `'office@astrotherapia.com'` instead of reading `SiteSetting::current()->contact['email']` with config fallback; error strings are hardcoded in English regardless of `$locale`.
- **Excerpt Extractor ([PostTranslation.php](../../app/Models/PostTranslation.php#L44-L62)):** `strip_tags()` concatenates `</p><p>` boundaries without spaces (`"...today.Next..."` instead of `"...today. Next..."`), and `preg_replace('/\s+/', ...)` lacks the `/u` flag so UTF-8 non-breaking spaces (`\xC2\xA0` from `&nbsp;`) remain uncollapsed.
- **Journal Card Consistency ([journal-card.blade.php](../../resources/views/partials/journal-card.blade.php#L14-L59)):** Text-only fallback cards (lines 43–59) omit `.card__author`, whereas media cards (lines 33–35) render it; card `<img>` lacks `width="1200" height="1200" loading="lazy"`.
- **Social Deep-Link Script ([footer-social.js](../../public/js/footer-social.js#L2)):** Uses `document.querySelector('[data-fb-page]')`, so on the Contact page (which renders both `.contact-social__link` and `.site-footer__social`), the footer Facebook icon is never bound.
- **Flaky Full-Suite Test ([AdminDatabaseRestoreTest.php](../../tests/Feature/AdminDatabaseRestoreTest.php#L97-L105)):** Failed with HTTP `404` during the full test run (`375 passed, 1 failed`) while passing in isolation (`1 passed, 2 assertions`). Indicates cross-test pollution in `Storage::fake('backups')` or `config('app.url')` state.
- **Deploy Archive & Public Asset Hygiene ([make-archive.php](../../.github/scripts/make-archive.php#L23-L33)):** `app.zip` excludes `.github`, `.claude`, `docs`, `tests`, `storage`, `CLAUDE.md`, `README.md`, but **fails to exclude** `.agents`, `AGENTS.md`, `AstroDynamics`, `admin_client_interface`, `content`, `.vscode`, and `database/backups` (if created locally). Furthermore, `public/img/about/` contains 7 unused demo images (`image-*-copyright-min-*.jpg`, ~45 KB) and [public/themes/theme_solarsystem/screenshot.png](../../public/themes/theme_solarsystem/screenshot.png) (413.7 KB) is outdated (still depicts old `HOME / ABOUT / BLOG / CONTACT` nav and removed hero buttons).

---

## 3. Performance, Scalability & Asset Optimization

### 3.1 [HIGH] Repeated `SiteSetting::current()` & `Schema::hasTable()` Queries
- **Evidence:** [SiteSetting.php](../../app/Models/SiteSetting.php#L20-L23), [ThemeServiceProvider.php](../../app/Providers/ThemeServiceProvider.php#L24-L37).
- **Issue:** On every public page request, `SiteSetting::current()` runs `firstOrCreate(['id' => 1])` 6–10 separate times (`ThemeManager::active()`, `ThemeManager::tokens()`, controller `sectionVisible()`, `nav.blade.php`, `footer.blade.php`, `hero.blade.php`, `home.blade.php`, `about.blade.php`, `services.blade.php`). Additionally, `ThemeServiceProvider` invokes `Schema::hasTable('site_settings')` on every HTTP request in production.
- **Cache Hardening Constraint:** Because [config/cache.php](../../config/cache.php#L120) enforces `'serializable_classes' => false`, cached data **must** be plain arrays/scalars (never serialized Eloquent models).

### 3.2 [HIGH] Unbounded Queries, N+1 Translation Lookups & Missing Index
- **Evidence:** [BlogController.php](../../app/Http/Controllers/BlogController.php#L16-L50), [PageController.php](../../app/Http/Controllers/PageController.php#L33-L45), [Post.php](../../app/Models/Post.php#L29-L33), [PostController.php](../../app/Http/Controllers/Admin/PostController.php#L24).
- **Issues:**
  - `BlogController::show()` loads **every published post and all translations (including full HTML `body`)** into PHP memory just to find the immediate `$previous` and `$next` post. Two targeted queries (`where('published_at', '<', ...)->limit(1)` and `> ...->limit(1)`) replace $O(N)$ memory/bandwidth with $O(1)$.
  - `PageController::home()` fetches every published post and translation rather than limiting/filtering in SQL (`whereHas('translations', ...)`).
  - Neither `BlogController::index()` nor `Admin\PostController::index()` paginates results.
  - `Post::translation($locale)` falls back to a live SQL query `$this->translations()->where('locale', $locale)->first()` whenever the eager-loaded collection contains no match for `$locale` — triggering an N+1 query per single-locale post. Check `$this->relationLoaded('translations')` first!
  - `posts` table lacks a composite index on `(status, published_at)`.

### 3.3 [MEDIUM] Heavy Public CSS, Unversioned JS & Missing HTTP Caching/Compression
- **217 KB `ckeditor5.css` on Public Articles ([blog/show.blade.php](../../resources/views/blog/show.blade.php#L42)):** Every public blog article downloads `public/vendor/ckeditor/ckeditor5.css` (**217.0 KB**), most of which is CKEditor toolbar/modal/balloon UI CSS unused on the public site. Extracting the ~2–3 KB of `.ck-content` structural rules (lists, blockquotes, tables, media alignment) into `article.css` eliminates >210 KB of render-blocking CSS per article view.
- **Missing Cache-Busting on JS Assets:** `ThemeManager::jsAssets()` ([ThemeManager.php](../../app/Services/ThemeManager.php#L75-L82)) and `about.js`, `services.js`, `article-share.js`, `ckeditor5.css` use `asset()` instead of `versioned_asset()`, leaving visitors on stale JS after deploys.
- **Font Preloads & Unused Weights:** `theme_solarsystem` ships 10 WOFF2 files (~175 KB total); `cinzel-700.woff2` and `jost-600.woff2` are lightly or rarely used, and critical above-the-fold fonts (`cormorant-garamond-400.woff2`, `jost-400.woff2`, `cinzel-400.woff2` / `cinzel-600.woff2`) lack `<link rel="preload" as="font" crossorigin>` hints.
- **`.htaccess` Caching & Compression:** `public/.htaccess` lacks `mod_deflate` / `mod_brotli` text compression and `mod_expires` long-TTL headers for `.woff2`, `.css`, `.js`, `.png`, `.jpg`, `.webp`.
- **Dead CSS & JS Overhead:**
  - `solarsystem.js:41,59-63` queries `[data-depth]` elements that do not exist in any view (`cosmos.blade.php` has no `data-depth`), while generating 40–75 `box-shadow` twinkle `<span>` nodes on every page.
  - `about.css:413-491` carries ~80 lines of dead `.about-zodiac` / `.about-horoscopes` CSS from removed sections.
  - `hero.css:148-200` carries unused `.stage .nav`, `.stage .brand`, `.stage .cta` selectors.
  - `skin.css:2-61` contains duplicate base rules (`body`, `h1, h2, h3`, `.hero`) immediately overridden at lines 71–74 and `hero.css:25-26`.
  - `article.css:33-57` ships `--ck-color-*` editor chrome tokens to public visitors (admin editor doesn't load `article.css`).

---

## 4. Accessibility, Design-Token Integrity & i18n/SEO Technical Hygiene

### 4.1 [HIGH] "Fake Romanian Locale" (`lang="ro"` Serving English Content)
- **Evidence:** [layouts/app.blade.php](../../resources/views/layouts/app.blade.php#L4-L39), [pages/home.blade.php](../../resources/views/pages/home.blade.php), [pages/about.blade.php](../../resources/views/pages/about.blade.php), [pages/services.blade.php](../../resources/views/pages/services.blade.php), [pages/contact.blade.php](../../resources/views/pages/contact.blade.php), [blog/index.blade.php](../../resources/views/blog/index.blade.php).
- **Issue:** `/ro/*` routes emit `<html lang="ro">`, `og:locale="ro_RO"`, `hreflang="ro"`, and translated `<title>` + `<meta name="description">`, yet **100% of visible UI and body copy** (navigation, hero, manifesto, services, FAQs, testimonials, footer, contact form, Journal headings) is hardcoded English, and there is **no `EN | RO` language switcher** anywhere in the header or footer.

### 4.2 [HIGH] Accessibility (WCAG 2.1 AA) Findings
1. **Duplicate `<main>` Landmarks on Home:** Both [hero.blade.php](../../public/themes/theme_solarsystem/views/hero.blade.php#L26) (`<main class="hero">`) and [home.blade.php](../../resources/views/pages/home.blade.php#L23) (`<main class="about">`) render `<main>` elements on `/en` and `/ro` (violates HTML5 / WCAG 1.3.1 single visible `<main>` landmark rule).
2. **Focusable Buttons Inside `aria-hidden="true"` Containers:** [services.blade.php](../../resources/views/pages/services.blade.php#L202-L206), [services.blade.php](../../resources/views/pages/services.blade.php#L221-L225), [services.blade.php](../../resources/views/pages/services.blade.php#L253-L257), and [services.blade.php](../../resources/views/pages/services.blade.php#L312-L316) place interactive `<button>` pagination dots inside `<div ... aria-hidden="true">`, failing WCAG 4.1.2 / Lighthouse accessibility audits.
3. **Incomplete ARIA Tabs Pattern:** `services.blade.php:176-209` uses `role="tab"` and `aria-selected`, but panels lack `role="tabpanel"`, `aria-labelledby`, and `aria-controls`, and `services.js` lacks `ArrowLeft`/`ArrowRight` keyboard switching.
4. **Pure-CSS Mobile Nav Accessibility:** Checkbox `#nav-toggle` lacks `aria-expanded` state updates, Escape-key dismissal, and focus management; nav links lack `aria-current="page"`.
5. **Disabled Text Selection:** `skin.css:292` applies `user-select: none` to all `h1, h2, h3` globally, and `hero.css:21` applies it to `.stage`, preventing visitors from copying titles or quotes.
6. **Extreme Mobile Body Font Scaling (`+60%`):** At `max-width: 720px`, `hero.css:313`, `structure.css:207`, `about.css:610-618`, `services.css:113-115`, `contact.css:244-247`, and `article.css:254-256` inflate body copy to `1.47rem–1.84rem` (~24–29px), breaking hierarchy (body copy becomes larger than `h2`/`h3` headings) and forcing `.flip-card .svc-card__desc` to override back down to `1rem` so it fits the fixed `height: 14rem` card.

### 4.3 [MEDIUM] Design-Token & Structured Data Drift
- **Raw Font/Color Literals Violating `config/tokens.php` Contract:**
  - `skin.css:243` and `article.css:61` hardcode `font-family: Helvetica, Arial, Tahoma, Verdana, sans-serif;` instead of `var(--font-base)`.
  - `contact.css:155,156,181,211` hardcode `#ef4444` (missing a `--color-danger` token).
  - `article.css:209` hardcodes `#f6e6a8`, `#c9a227`, `#7a5215` for the author-name gold gradient instead of `var(--color-gold)`.
- **Schema & Meta Edge Cases:**
  - [seo-schema.blade.php](../../resources/views/partials/seo-schema.blade.php#L8-L10) omits `https://www.instagram.com/astrotherapia/` from `Organization.sameAs` and omits `email`/`contactPoint`.
  - [layouts/app.blade.php](../../resources/views/layouts/app.blade.php#L36) & [L47](../../resources/views/layouts/app.blade.php#L47) output empty `<meta property="og:description" content="">` when `@section('meta_description')` is unset (e.g., on blog posts without `seo_description` or `subtitle`).
  - [footer.blade.php](../../resources/views/partials/footer.blade.php#L8) hardcodes `AstroTherapia © 2025 - 2026` instead of `2025 – {{ date('Y') }}`.

---

## 5. Upgrade & Modernization Opportunities (Zero-Node Compatible)

| Area | Opportunity | Benefit | Constraints / Compatibility |
|---|---|---|---|
| **Core Packages** | Update `composer.lock` to Laravel `13.44+`, Symfony `7.4.24+`, `intervention/image 4.4+`, patched `league/commonmark` | Clears 21 unignored CVEs; unblocks CI `security` job | 100% compatible with PHP 8.3/8.4 & cPanel |
| **PHP Floor & PHPUnit 13** | Raise `composer.json` `"php": "^8.4"` (matching prod/CI/local `8.4.23`) and adopt PHPUnit 13 | Unlocks PHPUnit 13 & PHP 8.4 features; eliminates local/CI PHP mismatch | Verify local devs only use PHP 8.4+ |
| **PHP 8.5 Readiness** | Update `config/database.php:61,88` to support `Pdo\Mysql::ATTR_SSL_CA` alongside `PDO::MYSQL_ATTR_SSL_CA` | Prevents deprecation warnings on PHP 8.5+ | Guard with `defined('Pdo\Mysql::ATTR_SSL_CA')` |
| **Static Analysis** | Add `larastan/larastan` (`require-dev`) + `phpstan.neon` level 5 to CI `lint` job | Catches null-safety & type bugs (like null `author->picture` or `seo_title` mismatch) pre-runtime | Dev-only; zero impact on `app.zip` |
| **Responsive WebP Media** | Extend GD pipeline (`imagewebp`) to emit `.webp` + `400w`/`800w`/`1200w` sizes with `<picture>` / `srcset` | 30–60% image payload savings on Journal cards & articles | Pure PHP GD (`gd` already required); test `function_exists('imagewebp')` |
| **Admin Completeness** | Build `/admin/settings` UI for `SiteSetting` (`hero` per locale, `contact.email`/`phone`, `sections` toggles, `branding`) + optional TOTP 2FA | Eliminates manual DB/tinker edits for copy & contact details; hardens admin | Pure Laravel/Blade; respects `--adm-*` layout isolation |
| **Shared Growth Plumbing** | Build `App\Mailing\MailingProvider` (Brevo) & `App\Payments\PaymentProvider` (Stripe, from dormant Plan 3) | Powers newsletter, Archetype Quiz funnel, and AstroDynamics Step 1 | Pure PHP SDK/HTTP + fake drivers for offline TDD |
