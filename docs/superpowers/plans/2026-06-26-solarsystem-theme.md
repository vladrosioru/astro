# Solar System Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the active site theme with an animated "solar system" celestial look — a full-viewport orbiting Home hero plus a dark cosmos skin on inner pages — authored through the project's existing token + data-driven contracts.

**Architecture:** A new named token set `solarsystem` in `config/themes.php`, activated via the existing `php artisan app:apply-theme solarsystem` (writes to `SiteSetting.branding`). Self-hosted fonts. The animated Home hero is a `.stage`-scoped CSS module (`public/css/hero-solarsystem.css`) plus a self-guarding vanilla-JS file (`public/js/solarsystem.js`). The hero Blade partial is rebuilt to the stage markup with all content still read from `SiteSetting.hero`. Nav stays a single global include whose appearance switches on a `<body>` page class. Inner pages inherit the palette/fonts via tokens and a static CSS starfield.

**Tech Stack:** Laravel 11, Blade, PHPUnit, plain CSS + vanilla JS (no Node/build step), self-hosted WOFF2 fonts, SQLite (dev/test).

## Global Constraints

- **Node-free / cPanel-safe:** no build step, no CDN, no JS libraries. All assets are plain files under `public/`. Fonts self-hosted as WOFF2 in `public/fonts/`.
- **Token-driven:** brand-level palette/fonts come only from `:root` tokens (`config/tokens.php` defaults → overridable by `SiteSetting.branding`). Theme values live in `config/themes.solarsystem`. **Do not change `config/tokens.php` light defaults.** Only decorative literals (planet gradients, nebula, sun glow, star colors, saturn ring) may be hard-coded in the theme CSS.
- **Data-driven:** hero copy from `SiteSetting.hero`; nav from `SiteSetting` (`sectionVisible()`, locale-prefixed URLs); brand from `config('app.name')`. No theme demo copy ("AstroTherapia") hardcoded.
- **Keep Mystik:** the existing `themes.mystik` set and its tests stay untouched.
- **Test contract:** `public/css/skin.css` must continue to contain the literal strings `.hero`, `var(--hero-overlay)`, `var(--font-display)`, `var(--color-heading)`, and the CKEditor image-alignment rules. `config/tokens.php` defaults must keep emitting `--nav-height: 4rem` and light values.
- **PHP/test commands:** every shell command that runs PHP must first prepend PHP to PATH:
  `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"`
  Run tests with `php artisan test`.
- **Source artifact:** `resources/theme_solarsystem/{index.html,css/styles.css,js/main.js}` is the reference; port from it.

---

### Task 1: `solarsystem` token set

**Files:**
- Modify: `config/themes.php` (add `solarsystem` array alongside `mystik`)
- Test: `tests/Feature/ApplyThemeCommandTest.php` (add one method)

**Interfaces:**
- Produces: `config('themes.solarsystem')` → associative array of token name ⇒ value, applied to `SiteSetting.branding` by the existing `app:apply-theme` command (no command code change).

- [ ] **Step 1: Write the failing test**

Add this method to `tests/Feature/ApplyThemeCommandTest.php`:

```php
    public function test_applying_solarsystem_writes_cosmos_tokens(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'solarsystem'])->assertExitCode(0);

        $branding = SiteSetting::current()->fresh()->branding;
        $this->assertSame('#05060c', $branding['color-bg']);
        $this->assertSame('#9dc1e6', $branding['color-primary']);
        $this->assertSame("'Jost', system-ui, sans-serif", $branding['font-base']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=test_applying_solarsystem_writes_cosmos_tokens`
Expected: FAIL — `app:apply-theme solarsystem` exits 1 (unknown theme), assertion never reached.

- [ ] **Step 3: Add the token set**

In `config/themes.php`, add this entry inside the returned array, after the `'mystik' => [...]` block (keep `mystik` unchanged):

```php
    'solarsystem' => [
        'color-bg'      => '#05060c',
        'color-bg-alt'  => '#0b1426',
        'color-fg'      => '#aab6c8',
        'color-heading' => '#f2f7fd',
        'color-muted'   => '#9aa6b8',
        'color-primary' => '#9dc1e6',
        'color-accent'  => '#dcebfb',
        'font-display'  => "'Cinzel', serif",
        'font-heading'  => "'Cormorant Garamond', serif",
        'font-base'     => "'Jost', system-ui, sans-serif",
        'nav-height'    => '4.5rem',
        'hero-overlay'  => 'rgba(0,0,0,0.45)',
    ],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=ApplyThemeCommandTest`
Expected: PASS — all 5 methods (4 original + new) green.

- [ ] **Step 5: Commit**

```bash
git add config/themes.php tests/Feature/ApplyThemeCommandTest.php
git commit -m "feat: add solarsystem theme token set"
```

---

### Task 2: Self-host Jost + Cormorant Garamond fonts

**Files:**
- Create: `public/fonts/jost-300.woff2`, `jost-400.woff2`, `jost-500.woff2`, `jost-600.woff2`
- Create: `public/fonts/cormorant-garamond-400.woff2`, `cormorant-garamond-500.woff2`, `cormorant-garamond-400-italic.woff2`
- Create: `public/fonts/cinzel-600.woff2`
- Modify: `public/css/fonts.css` (append `@font-face` rules)
- Test: `tests/Unit/FontsCssTest.php` (new)

**Interfaces:**
- Produces: font families `Jost`, `Cormorant Garamond` available via `@font-face`; referenced by the `font-base`/`font-heading` tokens from Task 1.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/FontsCssTest.php`:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class FontsCssTest extends TestCase
{
    public function test_fonts_css_references_self_hosted_theme_fonts(): void
    {
        $css = file_get_contents(public_path('css/fonts.css'));

        $this->assertStringContainsString("font-family: 'Jost'", $css);
        $this->assertStringContainsString('jost-400.woff2', $css);
        $this->assertStringContainsString("font-family: 'Cormorant Garamond'", $css);
        $this->assertStringContainsString('cormorant-garamond-400.woff2', $css);
        $this->assertStringContainsString('cormorant-garamond-400-italic.woff2', $css);
    }

    public function test_theme_font_files_exist(): void
    {
        foreach ([
            'jost-300.woff2', 'jost-400.woff2', 'jost-500.woff2', 'jost-600.woff2',
            'cormorant-garamond-400.woff2', 'cormorant-garamond-500.woff2',
            'cormorant-garamond-400-italic.woff2',
        ] as $file) {
            $this->assertFileExists(public_path("fonts/$file"));
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=FontsCssTest`
Expected: FAIL — files do not exist; strings not in fonts.css.

- [ ] **Step 3: Download the WOFF2 files**

Run this script (downloads the `latin` subset for each weight/style from the Google Fonts CSS2 API into `public/fonts/`):

```bash
cd c:/MINE/ClaudeSiteTemplate/public/fonts
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
getfont() { # $1=css-query  $2=output-file
  url=$(curl -s -A "$UA" "https://fonts.googleapis.com/css2?family=$1&display=swap" \
        | awk '/\/\* latin \*\//{f=1} f&&/src:/{print;exit}' \
        | grep -oE "https://[^)]+\.woff2")
  curl -s -o "$2" "$url"
  echo "$2 <- $url"
}
getfont "Jost:wght@300" jost-300.woff2
getfont "Jost:wght@400" jost-400.woff2
getfont "Jost:wght@500" jost-500.woff2
getfont "Jost:wght@600" jost-600.woff2
getfont "Cormorant+Garamond:wght@400" cormorant-garamond-400.woff2
getfont "Cormorant+Garamond:wght@500" cormorant-garamond-500.woff2
getfont "Cormorant+Garamond:ital,wght@1,400" cormorant-garamond-400-italic.woff2
getfont "Cinzel:wght@600" cinzel-600.woff2
file *.woff2 | grep -i woff2   # verify all are real WOFF2 files
```

Expected: each line prints a `fonts.gstatic.com/...woff2` URL and `file` reports "Web Open Font Format (Version 2)" for every file. If any file is 0 bytes or not WOFF2, re-run that `getfont` line.

- [ ] **Step 4: Append `@font-face` rules to `public/css/fonts.css`**

Append to the end of `public/css/fonts.css`:

```css
/* Cinzel 600 (brand weight for the Solar System theme). */
@font-face {
    font-family: 'Cinzel';
    font-style: normal;
    font-weight: 600;
    font-display: swap;
    src: url('/fonts/cinzel-600.woff2') format('woff2');
}

/* Jost — body / UI text. */
@font-face { font-family: 'Jost'; font-style: normal; font-weight: 300; font-display: swap; src: url('/fonts/jost-300.woff2') format('woff2'); }
@font-face { font-family: 'Jost'; font-style: normal; font-weight: 400; font-display: swap; src: url('/fonts/jost-400.woff2') format('woff2'); }
@font-face { font-family: 'Jost'; font-style: normal; font-weight: 500; font-display: swap; src: url('/fonts/jost-500.woff2') format('woff2'); }
@font-face { font-family: 'Jost'; font-style: normal; font-weight: 600; font-display: swap; src: url('/fonts/jost-600.woff2') format('woff2'); }

/* Cormorant Garamond — display/heading serif. */
@font-face { font-family: 'Cormorant Garamond'; font-style: normal; font-weight: 400; font-display: swap; src: url('/fonts/cormorant-garamond-400.woff2') format('woff2'); }
@font-face { font-family: 'Cormorant Garamond'; font-style: normal; font-weight: 500; font-display: swap; src: url('/fonts/cormorant-garamond-500.woff2') format('woff2'); }
@font-face { font-family: 'Cormorant Garamond'; font-style: italic; font-weight: 400; font-display: swap; src: url('/fonts/cormorant-garamond-400-italic.woff2') format('woff2'); }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=FontsCssTest`
Expected: PASS — both methods green.

- [ ] **Step 6: Commit**

```bash
git add public/fonts/*.woff2 public/css/fonts.css tests/Unit/FontsCssTest.php
git commit -m "feat: self-host Jost, Cormorant Garamond, Cinzel-600 fonts"
```

---

### Task 3: Hero data model — optional eyebrow + secondary CTA

**Files:**
- Modify: `app/Models/SiteSetting.php` (`heroDefaults()`)
- Test: `tests/Feature/HeroTest.php` (add one method)

**Interfaces:**
- Produces: `SiteSetting::heroDefaults()` now returns the additional keys `eyebrow`, `cta2_label`, `cta2_url`. Consumers merge via `array_merge(heroDefaults(), current()->hero ?? [])`, so the keys are always present.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/HeroTest.php`:

```php
    public function test_hero_defaults_include_eyebrow_and_secondary_cta(): void
    {
        $defaults = SiteSetting::heroDefaults();

        $this->assertSame('Celestial Guidance', $defaults['eyebrow']);
        $this->assertArrayHasKey('cta2_label', $defaults);
        $this->assertArrayHasKey('cta2_url', $defaults);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=test_hero_defaults_include_eyebrow_and_secondary_cta`
Expected: FAIL — `Undefined array key "eyebrow"`.

- [ ] **Step 3: Extend `heroDefaults()`**

In `app/Models/SiteSetting.php`, replace the `heroDefaults()` return array with:

```php
        return [
            'headline'   => 'Personal Horoscope & Magic Services',
            'subhead'    => 'Enjoy a vivid discussion about your horoscope or birth chart with a certified professional astrologer.',
            'cta_label'  => 'Begin Here',
            'cta_url'    => '/en/contact',
            'eyebrow'    => 'Celestial Guidance',
            'cta2_label' => 'Read the Journal',
            'cta2_url'   => '/en/blog',
        ];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=HeroTest`
Expected: PASS — all HeroTest methods green (existing headline/CTA tests still pass).

- [ ] **Step 5: Commit**

```bash
git add app/Models/SiteSetting.php tests/Feature/HeroTest.php
git commit -m "feat: add optional eyebrow + secondary CTA to hero defaults"
```

---

### Task 4: Animated Home stage CSS

**Files:**
- Create: `public/css/hero-solarsystem.css`
- Modify: `resources/views/layouts/app.blade.php` (add stylesheet `<link>`)
- Test: `tests/Unit/HeroSolarsystemCssTest.php` (new)

**Interfaces:**
- Produces: a stylesheet defining `.stage` and all cosmos/orbit selectors **scoped under `.stage`**, loaded site-wide (inert where no `.stage` element exists). Consumed by the hero partial markup in Task 6.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/HeroSolarsystemCssTest.php`:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class HeroSolarsystemCssTest extends TestCase
{
    public function test_stage_css_defines_animated_solar_system(): void
    {
        $css = file_get_contents(public_path('css/hero-solarsystem.css'));

        $this->assertStringContainsString('.stage', $css);
        $this->assertStringContainsString('.orbit', $css);
        $this->assertStringContainsString('@keyframes spin', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_layout_links_stage_stylesheet(): void
    {
        $blade = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('hero-solarsystem.css', $blade);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=HeroSolarsystemCssTest`
Expected: FAIL — file missing; layout has no link.

- [ ] **Step 3: Create `public/css/hero-solarsystem.css`**

Port `resources/theme_solarsystem/css/styles.css` with these exact transformations:

1. **Delete** the `:root { ... }` block (lines defining `--bg/--ink/--text/--muted/--icy/--icy-bright/--icy-line`). Keep `--solar-scale` by moving it onto `.stage` (see below). The palette comes from tokens.
2. **Delete** the global `* { box-sizing }`, `html, body { margin: 0 }`, `body { ... }`, and `a { ... }` rules (the project's `structure.css`/`skin.css` own those).
3. **Prefix every remaining selector** that targets cosmos/hero/nav/scroll elements with `.stage ` so the module is fully scoped — i.e. `.bg-base` → `.stage .bg-base`, `.solar-wrap` → `.stage .solar-wrap`, `.orbit-1` → `.stage .orbit-1`, `.nav` → `.stage .nav`, `.hero` → `.stage .hero`, `.eyebrow` → `.stage .eyebrow`, `.title` → `.stage .title`, `.lede` → `.stage .lede`, `.actions`/`.btn*` → `.stage .actions` / `.stage .btn*`, `.scroll-cue` → `.stage .scroll-cue`, etc. The `.stage` rule itself stays `.stage`. Keyframes (`@keyframes …`) are **not** prefixed.
4. On the `.stage` rule, **add** `--solar-scale: 1.18;` (and keep the responsive overrides below, but rewrite the media-query `:root { --solar-scale: … }` rules to target `.stage` instead, e.g. `@media (max-width:1024px){ .stage{ --solar-scale:1.0 } .stage .nav{padding:28px 36px} … }`).
5. **Add** these reset rules so the inner-page `.hero` skin (from `skin.css`) never bleeds into the stage's `<main class="hero">`:

```css
.stage .hero { background: none; }
.stage .hero::before { content: none; }
```

6. The `.stage` block keeps `position: relative; width:100%; height:100vh; min-height:680px; overflow:hidden; isolation:isolate;` and **add** a base background using the token: `background: var(--color-bg);`. Keep `.stage .nav { position: relative; z-index: 6; ... }` exactly as the theme has it (the nav is overlaid by the page-class rule from Task 7; inside the stage it sits above the cosmos).

   Note: the theme markup places `<header class="nav">` inside the stage. In this project the nav is a **separate global include** (Task 6/7) positioned `absolute` over the stage via `.page-home nav`, so the `.stage .nav` rules here apply to nothing — that is fine and harmless. Keep them for fidelity but they are not required.

7. For colors that were `var(--icy)` / `var(--ink)` / `var(--text)` etc. in the theme, map to the project tokens: `--icy` → `var(--color-primary)`, `--icy-bright` → `var(--color-accent)`, `--ink` → `var(--color-heading)`, `--text` → `var(--color-fg)`, `--muted` → `var(--color-muted)`. For `--icy-line` use `color-mix(in srgb, var(--color-primary) 70%, transparent)`. Decorative literals (planet gradients, sun glow, nebula, twinkle, saturn ring, scroll-cue grey) stay as-is.

The result is a self-contained `.stage`-scoped stylesheet. Verify it contains `.stage`, `.orbit`, `@keyframes spin`, and `prefers-reduced-motion` (the test checks these).

- [ ] **Step 4: Link the stylesheet in the layout**

In `resources/views/layouts/app.blade.php`, add this line in `<head>` immediately after the existing `skin.css` link:

```blade
    <link rel="stylesheet" href="{{ asset('css/hero-solarsystem.css') }}">
```

- [ ] **Step 5: Run test to verify it passes**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=HeroSolarsystemCssTest`
Expected: PASS — both methods green.

- [ ] **Step 6: Commit**

```bash
git add public/css/hero-solarsystem.css resources/views/layouts/app.blade.php tests/Unit/HeroSolarsystemCssTest.php
git commit -m "feat: add scoped animated solar-system stage stylesheet"
```

---

### Task 5: Stage JavaScript (starfield + parallax)

**Files:**
- Create: `public/js/solarsystem.js`
- Modify: `resources/views/layouts/app.blade.php` (add `<script defer>` before `</body>`)
- Test: `tests/Unit/SolarsystemJsTest.php` (new)

**Interfaces:**
- Produces: a self-guarding IIFE that builds twinkle stars into `.twinkle` and binds mouse parallax to `.stage` / `[data-depth]` / `[data-parallax="solar"]`. No-ops when no `.stage` is present, so it is safe to load on every page.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SolarsystemJsTest.php`:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class SolarsystemJsTest extends TestCase
{
    public function test_js_exists_and_self_guards(): void
    {
        $js = file_get_contents(public_path('js/solarsystem.js'));

        $this->assertStringContainsString('.twinkle', $js);
        $this->assertStringContainsString("data-parallax", $js);
        // Must no-op when there is no stage on the page.
        $this->assertStringContainsString(".stage", $js);
    }

    public function test_layout_loads_js_deferred(): void
    {
        $blade = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('solarsystem.js', $blade);
        $this->assertStringContainsString('defer', $blade);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=SolarsystemJsTest`
Expected: FAIL — file missing; layout has no script tag.

- [ ] **Step 3: Create `public/js/solarsystem.js`**

Copy `resources/theme_solarsystem/js/main.js` verbatim to `public/js/solarsystem.js`. It already self-guards (`buildStars` returns if `.twinkle` is absent; `bindParallax` returns if `.stage` is absent), so it is inert on inner pages. No edits needed.

- [ ] **Step 4: Add the deferred script to the layout**

In `resources/views/layouts/app.blade.php`, add immediately before the closing `</body>` tag:

```blade
    <script src="{{ asset('js/solarsystem.js') }}" defer></script>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=SolarsystemJsTest`
Expected: PASS — both methods green.

- [ ] **Step 6: Commit**

```bash
git add public/js/solarsystem.js resources/views/layouts/app.blade.php tests/Unit/SolarsystemJsTest.php
git commit -m "feat: add self-guarding starfield + parallax script"
```

---

### Task 6: Rebuild hero partial as the data-driven stage + body class

**Files:**
- Modify: `resources/views/partials/hero.blade.php` (full rewrite to stage markup)
- Modify: `resources/views/layouts/app.blade.php` (`<body class="@yield('body_class')">`)
- Modify: `resources/views/pages/home.blade.php` (set `@section('body_class', 'page-home')`)
- Test: `tests/Feature/HeroTest.php` (add markup assertions)

**Interfaces:**
- Consumes: `SiteSetting::heroDefaults()` keys from Task 3; `hero-solarsystem.css` (Task 4); `solarsystem.js` (Task 5).
- Produces: a `.stage` block on the Home page containing `.eyebrow`, `.title`, `.lede`, and one or two `.btn` actions, all bound to `SiteSetting.hero`. The `<body>` carries class `page-home` on Home.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/HeroTest.php`:

```php
    public function test_home_renders_stage_with_eyebrow_and_secondary_cta(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('class="stage"', false)
            ->assertSee('Celestial Guidance')      // eyebrow default
            ->assertSee('Read the Journal');       // secondary CTA default
    }

    public function test_home_sets_page_home_body_class(): void
    {
        $this->get('/en')->assertSee('class="page-home"', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=HeroTest`
Expected: FAIL — old hero markup has no `.stage`/eyebrow.

- [ ] **Step 3: Rewrite `resources/views/partials/hero.blade.php`**

Replace the entire file with the stage markup (cosmos layers + hero copy + scroll cue; **no nav** — nav is the global include). All copy is data-driven:

```blade
@php
    $hero = array_merge(\App\Models\SiteSetting::heroDefaults(), \App\Models\SiteSetting::current()->hero ?? []);
@endphp
<section class="stage">
    {{-- cosmos background --}}
    <div class="bg-base"></div>
    <div class="bg-layer" data-depth="0.35"><div class="stars stars1"></div></div>
    <div class="bg-layer" data-depth="0.7"><div class="stars stars2"></div></div>
    <div class="nebula"></div>
    <div class="twinkle" data-depth="1.1"></div>

    {{-- solar system --}}
    <div class="solar-wrap" data-parallax="solar">
        <div class="plane">
            <div class="orbit orbit-1"><div class="anchor"><div class="planet planet-mercury"></div></div></div>
            <div class="orbit orbit-2"><div class="anchor"><div class="planet planet-venus"></div></div></div>
            <div class="orbit orbit-3"><div class="anchor"><div class="planet planet-earth"></div></div></div>
            <div class="orbit orbit-4"><div class="anchor"><div class="planet planet-mars"></div></div></div>
            <div class="orbit orbit-5"><div class="anchor"><div class="planet planet-saturn">
                <span class="saturn-ring"></span><span class="saturn-body"></span>
            </div></div></div>
        </div>
        <div class="sun"></div>
    </div>

    <div class="vignette"></div>

    {{-- hero copy --}}
    <main class="hero">
        @if (!empty($hero['eyebrow']))
            <p class="eyebrow"><span class="rule"></span>{{ $hero['eyebrow'] }}<span class="rule"></span></p>
        @endif
        <h1 class="title">{{ $hero['headline'] }}</h1>
        <p class="lede">{{ $hero['subhead'] }}</p>
        <div class="actions">
            @if (!empty($hero['cta_label']))
                <a href="{{ $hero['cta_url'] ?? '#' }}" class="btn btn-primary">{{ $hero['cta_label'] }}</a>
            @endif
            @if (!empty($hero['cta2_label']))
                <a href="{{ $hero['cta2_url'] ?? '#' }}" class="btn btn-ghost">{{ $hero['cta2_label'] }} &rarr;</a>
            @endif
        </div>
    </main>

    <div class="scroll-cue">Scroll<span class="arrow">&darr;</span></div>
</section>
```

- [ ] **Step 4: Add the body class hook to the layout**

In `resources/views/layouts/app.blade.php`, change `<body>` to:

```blade
<body class="@yield('body_class')">
```

- [ ] **Step 5: Mark the Home page**

In `resources/views/pages/home.blade.php`, add after the `@section('title', ...)` line:

```blade
@section('body_class', 'page-home')
```

- [ ] **Step 6: Run test to verify it passes**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=HeroTest`
Expected: PASS — all HeroTest methods green (including original headline/CTA assertions).

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/hero.blade.php resources/views/layouts/app.blade.php resources/views/pages/home.blade.php tests/Feature/HeroTest.php
git commit -m "feat: rebuild hero partial as data-driven solar-system stage"
```

---

### Task 7: Nav overlay + inner-page cosmos skin

**Files:**
- Modify: `public/css/structure.css` (nav overlay positioning, hero-copy layout helpers)
- Modify: `public/css/skin.css` (dark restyle: nav, links, buttons, cards, static starfield body; retain test-contract strings)
- Test: `tests/Unit/SkinCssTest.php` (add assertions; keep existing)

**Interfaces:**
- Consumes: the `page-home` body class (Task 6); palette/font tokens (Task 1).
- Produces: nav overlays the stage on Home (`.page-home nav`) and is a sticky translucent bar elsewhere; inner pages get a static CSS starfield background and dark-readable `.container`/`.card`/`.btn` styling.

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/SkinCssTest.php` (keep both existing methods unchanged):

```php
    public function test_skin_defines_home_nav_overlay(): void
    {
        $css = file_get_contents(public_path('css/skin.css'));

        $this->assertStringContainsString('.page-home nav', $css);
    }

    public function test_skin_retains_test_contract_tokens(): void
    {
        $css = file_get_contents(public_path('css/skin.css'));

        // Contract required by ThemeTokens/SkinCss expectations.
        $this->assertStringContainsString('.hero', $css);
        $this->assertStringContainsString('var(--hero-overlay)', $css);
        $this->assertStringContainsString('var(--font-display)', $css);
        $this->assertStringContainsString('var(--color-heading)', $css);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=SkinCssTest`
Expected: FAIL — `.page-home nav` not present yet.

- [ ] **Step 3: Update `public/css/structure.css`**

Append these layout rules (structure only — no colors) to `public/css/structure.css`:

```css
/* Solar System: Home nav overlays the stage; inner-page nav is the sticky bar above. */
.page-home nav { position: absolute; top: 0; left: 0; right: 0; }
.page-home { position: relative; }
```

- [ ] **Step 4: Update `public/css/skin.css`**

Keep the existing file contents (image-alignment rules, the `.hero` starfield block referencing `var(--hero-overlay)`, the `h1,h2,h3,.brand` rule referencing `var(--font-display)`/`var(--color-heading)`, the `nav` translucent rule — all required by tests). **Append** this dark-cosmos skin block at the end:

```css
/* ============================================================
   Solar System skin (token-driven). Appearance only.
   ============================================================ */

/* Inner pages: static CSS starfield over the dark base (no JS, no orbits). */
body {
    background-color: var(--color-bg);
    background-image:
        radial-gradient(1px 1px at 20% 30%, #ffffff 50%, transparent 51%),
        radial-gradient(1px 1px at 70% 60%, rgba(255,255,255,.75) 50%, transparent 51%),
        radial-gradient(1.4px 1.4px at 40% 80%, rgba(220,235,255,.9) 50%, transparent 51%),
        radial-gradient(1px 1px at 85% 25%, #cbd5e1 50%, transparent 51%),
        radial-gradient(1px 1px at 55% 15%, rgba(255,255,255,.6) 50%, transparent 51%),
        radial-gradient(1.5px 1.5px at 10% 70%, #e2e8f0 50%, transparent 51%);
    background-size: 320px 320px, 280px 280px, 400px 400px, 360px 360px, 240px 240px, 300px 300px;
    background-attachment: fixed;
}

/* Headings use the heading serif; brand uses the display serif. */
h1, h2, h3 { font-family: var(--font-heading); color: var(--color-heading); letter-spacing: 0.01em; }

/* Nav: transparent overlay on Home, translucent sticky bar elsewhere. */
.page-home nav { background: transparent; backdrop-filter: none; border-bottom: 0; }
nav a { color: var(--color-muted); }
nav a:hover { color: var(--color-accent); }

/* Links + muted text. */
a { color: var(--color-primary); }
a:hover { color: var(--color-accent); }
.muted { color: var(--color-muted); }

/* Buttons / CTAs (icy pill + ghost). */
.btn, .hero-cta {
    text-decoration: none; font-family: var(--font-display);
    font-size: 0.78rem; font-weight: 500; letter-spacing: 0.16em; text-transform: uppercase;
    transition: background .25s ease, color .25s ease;
}
.btn-primary, .hero-cta {
    display: inline-block; border: 1px solid color-mix(in srgb, var(--color-primary) 55%, transparent);
    border-radius: 999px; padding: 0.9rem 2rem;
    color: var(--color-accent); background: color-mix(in srgb, var(--color-primary) 6%, transparent);
}
.btn-primary:hover, .hero-cta:hover { background: color-mix(in srgb, var(--color-primary) 16%, transparent); }
.btn-ghost { display: inline-flex; align-items: center; gap: 8px; color: var(--color-primary); padding: 0.9rem 0.25rem; }
.btn-ghost:hover { color: var(--color-accent); }

/* Cards / content panels. */
.card {
    background: var(--color-bg-alt);
    border: 1px solid color-mix(in srgb, var(--color-fg) 14%, transparent);
    padding: calc(var(--space-unit) * 4);
}
.card a { text-decoration: none; }
.container { color: var(--color-fg); }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test --filter=SkinCssTest`
Expected: PASS — all four methods (2 original + 2 new) green.

- [ ] **Step 6: Commit**

```bash
git add public/css/structure.css public/css/skin.css tests/Unit/SkinCssTest.php
git commit -m "feat: dark cosmos skin + Home nav overlay for solarsystem theme"
```

---

### Task 8: Activate theme + full-suite regression + manual verification

**Files:**
- No source changes (runtime activation + verification only).

**Interfaces:**
- Consumes: everything above.

- [ ] **Step 1: Run the FULL test suite**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan test`
Expected: PASS — previous 37 + new tests, zero failures. If any of `PublicPagesTest`, `StyleTokensTest`, `ThemeTokensTest` fail, fix the regression before continuing (do not weaken those tests).

- [ ] **Step 2: Activate the theme on the dev database**

Run: `export PATH="/c/Users/rosio/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe:$PATH"; php artisan app:apply-theme solarsystem`
Expected: `Theme 'solarsystem' applied.`

- [ ] **Step 3: Manual visual check**

Run `php artisan serve` (with the PATH prefix) and open `http://127.0.0.1:8000/en`. Confirm:
- Home shows the dark stage, drifting stars, orbiting planets + sun, vignette, the real hero headline/subhead/CTAs, eyebrow kicker, and nav overlaid transparently at the top; mouse movement parallaxes the cosmos.
- `/en/about`, `/en/contact`, `/en/blog` render the dark starfield skin with a sticky translucent nav and readable content/cards (no orbit animation).
- `prefers-reduced-motion` (OS setting) stops the animations.

- [ ] **Step 4: Commit (if `database/database.sqlite` is tracked)**

`database/database.sqlite` is gitignored, so there is nothing to commit for activation. If the manual check surfaced a CSS tweak, commit it:

```bash
git add -A
git commit -m "fix: solarsystem theme visual adjustments from manual review"
```

(Skip if no changes.)

---

## Notes for the implementer

- The source theme uses Google-Fonts class/markup names; this plan deliberately **scopes the stage CSS under `.stage`** and **keeps content data-driven** — do not reintroduce the demo copy or the Google Fonts `<link>`.
- The active theme in tests is whatever `RefreshDatabase` seeds (empty branding ⇒ light defaults). Structure/markup tests therefore assert on markup and text, never on cosmos colors. Color values are verified only through `app:apply-theme` in Task 1.
- If a font download step yields a 0-byte file, re-run just that `getfont` line; the URL is generated fresh each call.
