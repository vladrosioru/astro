# 🔍 Astrotherapia — Comprehensive SEO Audit Report

**Date:** September 28, 2026
**Scope:** Full-site SEO analysis — technical, on-page, content, and multilingual
**Domain:** astrotherapia.com

---

## Executive Summary

The site has **strong content quality** and **good semantic HTML foundations**, but is missing nearly every technical SEO essential. There are **zero meta descriptions** on static pages, **no canonical tags**, **no hreflang annotations**, **no sitemap.xml**, **no structured data (JSON-LD)**, and the **favicon is 0 bytes**. These are all straightforward to implement and will have an outsized impact on search visibility and social sharing.

> **⚠️ The site is currently invisible to search engines** in several important ways — no sitemap, no meta descriptions on 5 of 6 page types, no structured data, and no multilingual signals. Fixing these is the single highest-ROI work you can do.

---

## 1. Page-by-Page Title Tag Audit

| Page | Current `<title>` | Problem | Recommended `<title>` |
|---|---|---|---|
| **Home** (`/en/`) | `AstroTherapia` (just `config('app.name')`) | ⚠️ Too short, no keywords, no value proposition | `AstroTherapia — Astrology Readings & Birth Chart Analysis` |
| **About** (`/en/about`) | `Astrology — AstroTherapia` | ⚠️ Generic, doesn't describe the page | `About AstroTherapia — Understanding the Why Behind Your Choices` |
| **Services** (`/en/services`) | `Services — AstroTherapia` | ⚠️ Generic, no keywords | `Astrology Services — Natal Chart, Tarot & Relationship Readings · AstroTherapia` |
| **Contact** (`/en/contact`) | `Contact — AstroTherapia` | ⚠️ Generic | `Contact AstroTherapia — Book Your Astrology Session` |
| **Journal** (`/en/journal`) | `Journal` | 🔴 **Missing brand name entirely**, very poor | `Cosmic Journal — Astrology Insights & Reflections · AstroTherapia` |
| **Blog Post** (`/en/journal/{slug}`) | `seo_title ?? title` | ✅ Acceptable pattern | `{Post Title} · AstroTherapia` (append brand) |

> **Warning:** The Journal index page has only `Journal` as its title — no brand, no keywords. This is the worst offender. Blog post titles also lack the brand suffix.

### Title Tag Rules to Enforce
- **Format:** `Primary Keywords — Secondary Context · AstroTherapia`
- **Length:** 50-60 characters ideal
- **Every page MUST include the brand name** as a suffix

---

## 2. Meta Description Audit

| Page | Current `<meta name="description">` | Status |
|---|---|---|
| **Home** | ❌ **None** | Missing |
| **About** | ❌ **None** | Missing |
| **Services** | ❌ **None** | Missing |
| **Contact** | ❌ **None** | Missing |
| **Journal Index** | ❌ **None** | Missing |
| **Blog Post** | ✅ `seo_description ?: subtitle` | Present (when populated) |

> **⚠️ 5 out of 6 page types have zero meta description.** Google will auto-generate snippets from body text, but these are almost always inferior to hand-written descriptions. Each static page should have a unique, compelling, 150-160 character meta description.

### Recommended Meta Descriptions

| Page | Proposed Description |
|---|---|
| **Home** | `AstroTherapia helps you understand the patterns behind your choices through astrology readings, birth chart analysis, and tarot. Book a session today.` |
| **About** | `What is AstroTherapia? A birth-chart-based practice that reveals the patterns behind your decisions — not predictions, but clarity. Discover the philosophy.` |
| **Services** | `Explore astrology services from natal chart analysis to tarot readings, relationship synastry, and yearly forecasts. Find the right reading for your question.` |
| **Contact** | `Get in touch with AstroTherapia. Ask about readings, book a session, or send a message. Email, phone, and social media contact options available.` |
| **Journal** | `Reflections on astrology, self-knowledge, and the patterns that shape your life. Read the latest entries from the AstroTherapia Cosmic Journal.` |

---

## 3. Heading Structure (H1) Audit

| Page | H1 Content | Issue |
|---|---|---|
| **Home** | ❌ **No H1 tag anywhere** | 🔴 Critical — the home page has no `<h1>` at all. The hero heading is likely in a theme partial (`theme::hero`) which wasn't found |
| **About** | ❌ **No H1 tag** | 🔴 The page jumps directly to `<h2>` headings. No main `<h1>` |
| **Services** | ✅ `<h1>Services</h1>` | ⚠️ Present but too generic |
| **Contact** | ✅ `<h1>Contact</h1>` | ⚠️ Present but too generic |
| **Journal Index** | ✅ `<h1>Cosmic Journal</h1>` | ✅ Good |
| **Blog Post** | ✅ `<h1>{post title}</h1>` | ✅ Good |

> **Important:** The **Home** and **About** pages lack `<h1>` tags entirely. Every page must have exactly one `<h1>` that contains the primary keyword for that page.

### Recommended H1s

| Page | Proposed H1 |
|---|---|
| **Home** | `Understanding the Why Behind Your Choices` (the hero headline) |
| **About** | `What Is AstroTherapia?` or `About AstroTherapia` |
| **Services** | `Astrology & Tarot Services` |
| **Contact** | `Contact AstroTherapia` |

---

## 4. Open Graph & Social Sharing Audit

| Page | OG Tags | Twitter Card | Status |
|---|---|---|---|
| **Home** | ❌ None | ❌ None | Missing |
| **About** | ❌ None | ❌ None | Missing |
| **Services** | ❌ None | ❌ None | Missing |
| **Contact** | ❌ None | ❌ None | Missing |
| **Journal Index** | ❌ None | ❌ None | Missing |
| **Blog Post** | ✅ Full set | ✅ Full set | **Good** ✅ |

> **Warning:** Only blog posts have Open Graph and Twitter Card meta tags. When any static page is shared on Facebook, LinkedIn, X, or WhatsApp, the preview card will be empty or auto-generated (usually poorly).

### Required OG Tags for Every Page
```html
<meta property="og:type" content="website">
<meta property="og:title" content="...">
<meta property="og:description" content="...">
<meta property="og:url" content="...">
<meta property="og:image" content="...">
<meta property="og:site_name" content="AstroTherapia">
<meta property="og:locale" content="en_US">   <!-- or ro_RO -->
<meta property="og:locale:alternate" content="ro_RO">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="...">
<meta name="twitter:description" content="...">
<meta name="twitter:image" content="...">
```

---

## 5. Multilingual SEO — `hreflang` & Canonical

### 5a. `hreflang` Tags — ❌ Completely Missing

The site serves two locales (`en`, `ro`) but has **zero `hreflang` annotations**. This means:
- Google cannot pair the EN and RO versions of the same page
- Both versions compete against each other (keyword cannibalization)
- Romanian users may see the English page in search results and vice versa

**Every page must output:**
```html
<link rel="alternate" hreflang="en" href="https://astrotherapia.com/en/about">
<link rel="alternate" hreflang="ro" href="https://astrotherapia.com/ro/about">
<link rel="alternate" hreflang="x-default" href="https://astrotherapia.com/en/about">
```

### 5b. Canonical Tags — ❌ Completely Missing

No page declares a `<link rel="canonical">`. This means:
- Google may index duplicate URLs (with/without trailing slashes, query strings, etc.)
- The `/{locale}/blog/*` → `/{locale}/journal/*` redirects are good (301), but canonical tags provide an additional safety net

**Every page must output:**
```html
<link rel="canonical" href="https://astrotherapia.com/en/about">
```

---

## 6. Technical SEO Audit

### 6a. `robots.txt` — ⚠️ Minimal

Current content:
```
User-agent: *
Disallow:
```

**Missing:**
- `Sitemap: https://astrotherapia.com/sitemap.xml` directive
- Blocking of admin routes: `Disallow: /admin/`
- Blocking of non-content paths

**Proposed `robots.txt`:**
```
User-agent: *
Disallow: /admin/
Disallow: /vendor/

Sitemap: https://astrotherapia.com/sitemap.xml
```

### 6b. `sitemap.xml` — ❌ Does Not Exist

There is no sitemap at all. This is critical for:
- Google discovering all pages (especially blog posts)
- Signaling which pages are important and when they were last updated
- Supporting multilingual page pairing

**Need to create a dynamic `sitemap.xml`** that includes:
- All static pages (both `/en/` and `/ro/` variants)
- All published blog posts (both locale slugs)
- `<lastmod>` dates
- `<xhtml:link rel="alternate" hreflang="...">` within each `<url>` entry

### 6c. Favicon — 🔴 Empty File (0 bytes)

```
public/favicon.ico — 0 bytes
```

The `favicon.ico` exists but is **empty**. No `<link rel="icon">` tag appears in the `<head>` either.

**Need:**
- A proper favicon.ico (16×16, 32×32 ICO format)
- A 180×180 `apple-touch-icon.png`
- A 192×192 + 512×512 set for PWA/Android
- Proper `<link>` tags in the layout `<head>`
- Consider a `site.webmanifest` for progressive web app support

### 6d. `.htaccess` — ✅ Mostly Good

- Trailing-slash removal with 301 redirect ✅
- Front-controller rewrite ✅
- **Missing:** HTTPS enforcement (`RewriteRule ^ https://...`) — but this may be handled at the hosting/cPanel level

### 6e. Image Optimization

| Issue | Details |
|---|---|
| **Logo size** | `logo-nav.png` is **419 KB** — extremely large for a nav logo. Should be < 30 KB. Convert to optimized PNG or WebP. |
| **Missing `width`/`height` on some images** | Blog post featured image in `blog/show.blade.php` lacks `width` and `height` attributes — causes CLS (Cumulative Layout Shift) |
| **No `loading="lazy"`** on blog post images | The main article image and author image in blog post pages lack `loading="lazy"` |
| **No WebP format** | All images appear to be PNG/JPG; serving WebP with `<picture>` fallback would save 25-40% |

---

## 7. Structured Data (JSON-LD) — ❌ Completely Missing

There is **zero structured data** on the entire site. This is a massive missed opportunity for rich snippets in Google search results.

### Required Structured Data

| Page | Schema Type | Benefit |
|---|---|---|
| **Every page** | `Organization` / `WebSite` | Brand knowledge panel, site links search box |
| **Home** | `LocalBusiness` or `ProfessionalService` | Business info in search, Google Maps potential |
| **Services** | `Service` (multiple) | Rich service listings |
| **Blog Post** | `Article` + `Person` (author) | Rich article snippets with author, date, image |
| **Journal Index** | `CollectionPage` | Blog listing context |
| **Contact** | `ContactPage` | Contact intent signals |
| **FAQ sections** (About + Services) | `FAQPage` | ⭐ **FAQ rich snippets** — the FAQ `<details>` elements are perfect candidates |

> **Tip:** The FAQ sections on the About and Services pages are **ideal** for `FAQPage` structured data. Google displays these as expandable Q&A directly in search results, dramatically increasing SERP real estate.

---

## 8. Content & Keyword Issues

### 8a. Duplicate Content Problem

The **About** and **Services** pages share **identical FAQ content** — the same 8 questions and answers are hardcoded in both views. This creates:
- Duplicate content signals for Google
- Wasted crawl budget
- Unclear which page should rank for FAQ queries

**Fix:** Differentiate the FAQs — services-specific questions on Services, philosophy questions on About.

### 8b. Missing Content on Home Page

The home page has **no `<h1>`** and very little unique text. It relies on:
1. A theme-injected hero (which may or may not have an H1)
2. A "What is AstroTherapia?" section (good content, `<h2>`)
3. A Journal carousel (dynamic)

**The home page needs its own unique, keyword-rich introductory content.**

### 8c. Blog Post SEO Fields

The `PostTranslation` model has `seo_title` and `seo_description` columns, which is good. However:
- The blog post title tag shows `seo_title ?? title` **without appending the brand name**
- The meta description is `seo_description ?: subtitle` — good fallback logic

### 8d. Hard-Coded English Content

All page content (About manifesto, services, FAQs, testimonials) is **hardcoded in English** in the Blade templates. The site has `ro` locale support in routing, but:
- Static page content doesn't change based on locale
- Only blog posts have actual multilingual content via `PostTranslation`
- This severely limits Romanian SEO potential

---

## 9. Footer Copyright Year

The footer reads:
```html
AstroTherapia © 2024
```

It's 2026. This should use `{{ date('Y') }}` or `2024–{{ date('Y') }}`.

---

## 10. Social Media Integration

| Platform | Presence | Issue |
|---|---|---|
| **Facebook** | ✅ Link to `facebook.com/astrotherapia.ro` | Present in footer + contact |
| **Instagram** | ⚠️ Link only on Contact page | Missing from footer |
| **X / Twitter** | ❌ No link | No profile linked anywhere |
| **LinkedIn** | ❌ No link | No profile linked anywhere |

The **Instagram link is missing from the footer** — it only appears on the Contact page. Social presence should be consistent site-wide.

---

## 11. Performance SEO Signals

| Item | Status | Impact |
|---|---|---|
| Logo image 419 KB | 🔴 | Slows every page load (it's in the nav) |
| No `<link rel="preconnect">` | ⚠️ | If using external fonts or APIs |
| No `<link rel="dns-prefetch">` | ⚠️ | For Facebook SDK, external resources |
| CKEditor CSS loaded on blog posts | ⚠️ | Large CSS file for content rendering |
| No `content-visibility: auto` | ⚠️ | Below-fold sections could benefit |

---

## 12. URL Structure Assessment

| Pattern | Status | Notes |
|---|---|---|
| `/{locale}/` prefix | ✅ Clean | Good for multilingual SEO |
| `/{locale}/journal/{slug}` | ✅ Clean | Descriptive URLs |
| `/blog` → `/journal` 301 redirect | ✅ Good | Proper redirect chain |
| `/articles` → `/journal` 301 redirect | ✅ Good | Back-compat preserved |
| No pagination URLs | ⚠️ | Blog index loads all posts — may need `?page=N` eventually |

---

## 13. Security Headers (SEO Adjacent)

Missing headers that affect both security and search trust:
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy`
- `Permissions-Policy`
- `Content-Security-Policy`

These can be set in `.htaccess` or Laravel middleware.

---

# Implementation Plan

## Phase 0: APP_NAME = "Laravel" — ✅ FIXED

> **⚠️ Critical:** The `.env` file had `APP_NAME=Laravel` — the framework default was never changed. This caused **"Laravel"** to appear in 11 places across the live site:

| Location | What was displayed |
|---|---|
| Browser tab (Home) | `<title>Laravel</title>` |
| Browser tab (About) | `Astrology — Laravel` |
| Browser tab (Services) | `Services — Laravel` |
| Browser tab (Contact) | `Contact — Laravel` |
| Default title fallback | `Laravel` |
| Nav logo `alt` text | `alt="Laravel"` |
| Nav logo `aria-label` | `"Laravel — Home"` |
| Admin topbar brand | `Laravel admin` |
| Admin login text | `"Administration for Laravel"` |
| Contact email subject | `"New contact message — Laravel"` |
| Email sender name (`MAIL_FROM_NAME`) | `"Laravel"` |

**Fix applied:** Changed `APP_NAME=Laravel` → `APP_NAME=AstroTherapia` in both `.env` and `.env.example`.

> **Note:** This fix must also be applied on the **production server's** `.env` file — it is gitignored.

---

## Phase 1: Critical Foundations (Week 1) — HIGH IMPACT ✅ IMPLEMENTED

| # | Task | Files Affected | Status | Priority |
|---|---|---|---|---|
| 1.1 | **Add `<meta name="description">` to layout** with `@yield('meta_description')` | `resources/views/layouts/app.blade.php` + all page views | ✅ Done | 🔴 Critical |
| 1.2 | **Add `<link rel="canonical">`** to layout using `url()->current()` | `resources/views/layouts/app.blade.php` | ✅ Done | 🔴 Critical |
| 1.3 | **Add `hreflang` tags** to layout (both `en` + `ro` + `x-default`) | `resources/views/layouts/app.blade.php` + `blog/show.blade.php` | ✅ Done | 🔴 Critical |
| 1.4 | **Fix all `<title>` tags** — descriptive, keyword-rich, brand-appended | All 6 public page views | ✅ Done | 🔴 Critical |
| 1.5 | **Add H1 to Home and About pages** | `resources/views/pages/about.blade.php` (Home verified in theme hero) | ✅ Done | 🔴 Critical |
| 1.6 | **Fix favicon** — generate proper ICO + apple-touch-icon + 16/32 PNGs | `public/` + `resources/views/layouts/app.blade.php` | ✅ Done | 🟡 High |

## Phase 2: Social & Structured Data (Week 2) — HIGH IMPACT

| # | Task | Files Affected | Priority |
|---|---|---|---|
| 2.1 | **Add Open Graph tags to layout** (`og:type`, `og:title`, `og:description`, `og:url`, `og:image`, `og:site_name`, `og:locale`) | `resources/views/layouts/app.blade.php` via `@yield` / `@stack` | 🔴 Critical |
| 2.2 | **Add Twitter Card tags to layout** | `resources/views/layouts/app.blade.php` | 🟡 High |
| 2.3 | **Create JSON-LD structured data partial** — `Organization` + `WebSite` on every page | New partial: `resources/views/partials/seo-schema.blade.php` | 🟡 High |
| 2.4 | **Add `Article` JSON-LD** to blog posts | `resources/views/blog/show.blade.php` | 🟡 High |
| 2.5 | **Add `FAQPage` JSON-LD** to About and Services pages | `resources/views/pages/about.blade.php`, `resources/views/pages/services.blade.php` | 🟡 High |
| 2.6 | **Add `Service` JSON-LD** to Services page | `resources/views/pages/services.blade.php` | 🟡 High |

## Phase 3: Technical SEO (Week 2-3)

| # | Task | Files Affected | Priority |
|---|---|---|---|
| 3.1 | **Create dynamic `sitemap.xml`** — route + controller, listing all public pages in both locales + all blog posts with hreflang alternates | New: `app/Http/Controllers/SitemapController.php` + `routes/web.php` | 🔴 Critical |
| 3.2 | **Update `robots.txt`** — add `Sitemap:` directive + block `/admin/` | `public/robots.txt` | 🟡 High |
| 3.3 | **Append brand to blog post titles** | `resources/views/blog/show.blade.php` | 🟡 High |
| 3.4 | **Optimize nav logo** — compress `logo-nav.png` from 419 KB to < 30 KB | `public/img/logo-nav.png` | 🟡 High |
| 3.5 | **Add `width` and `height`** to blog post featured images | `resources/views/blog/show.blade.php` | 🟢 Medium |
| 3.6 | **Fix copyright year** in footer | `resources/views/partials/footer.blade.php` | 🟢 Medium |
| 3.7 | **Add Instagram to footer** | `resources/views/partials/footer.blade.php` | 🟢 Medium |

## Phase 4: Content Improvements (Week 3-4)

| # | Task | Files Affected | Priority |
|---|---|---|---|
| 4.1 | **Differentiate FAQ content** between About and Services pages | `resources/views/pages/about.blade.php`, `resources/views/pages/services.blade.php` | 🟡 High |
| 4.2 | **Add unique descriptive content to Home page** | `resources/views/pages/home.blade.php` | 🟡 High |
| 4.3 | **Improve Services page H1 and header content** | `resources/views/pages/services.blade.php` | 🟢 Medium |
| 4.4 | **Create a default OG image** for social sharing of static pages | `public/img/og-default.jpg` | 🟢 Medium |
| 4.5 | **Consider Romanian translations** for static page content | All page views | 🔵 Future |

## Phase 5: Advanced (Month 2+)

| # | Task | Priority |
|---|---|---|
| 5.1 | **Add security headers** via middleware or .htaccess | 🟢 Medium |
| 5.2 | **Blog pagination** if post count grows beyond ~20 | 🟢 Medium |
| 5.3 | **`BreadcrumbList` JSON-LD** on inner pages | 🟢 Medium |
| 5.4 | **`site.webmanifest`** for PWA support | 🔵 Nice-to-have |
| 5.5 | **Implement blog post categories/tags** for topic clustering | 🔵 Future |
| 5.6 | **Google Search Console** registration + sitemap submission | 🔴 Critical (manual) |
| 5.7 | **Google Business Profile** setup (if applicable) | 🟡 High (manual) |

---

## Architecture Recommendation for Implementation

The cleanest approach is to centralize SEO in the layout via **Blade yields and a new SEO partial**, rather than scattering meta tags across individual views:

```
layouts/app.blade.php
├── @yield('meta_description')          ← each page provides
├── @yield('meta_og_type', 'website')   ← blog posts override to 'article'
├── @include('partials.seo-meta')       ← canonical, hreflang, OG, Twitter
├── @include('partials.seo-schema')     ← JSON-LD Organization + WebSite
└── @stack('schema')                    ← per-page JSON-LD (Article, FAQ, Service)
```

Each page view then only needs to define its specific content:
```blade
@section('meta_description', 'Your compelling 155-char description here')
@push('schema')
    <script type="application/ld+json">{ ... FAQPage ... }</script>
@endpush
```

---

## Severity Summary

| Severity | Count | Examples |
|---|---|---|
| 🔴 **Critical** | 7 | No meta descriptions, no canonical, no hreflang, no sitemap, no H1 on Home/About, empty favicon |
| 🟡 **High** | 10 | No OG tags on static pages, no structured data, generic titles, duplicate FAQ, oversized logo |
| 🟢 **Medium** | 6 | Missing image dimensions, copyright year, Instagram in footer, security headers |
| 🔵 **Future** | 4 | Romanian translations, blog tags, PWA manifest |

> **Note:** All critical and high-priority items can be implemented without changing any backend logic — they're pure template/view layer work plus one new route for the sitemap. This makes them safe, testable, and fully covered by existing test patterns.
