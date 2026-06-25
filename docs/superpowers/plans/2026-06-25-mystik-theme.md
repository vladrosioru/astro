# "Mystik" Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the site a dark, mystical "Mystik" look (gold serif headings, starfield Home hero, sticky translucent nav) authored entirely through the existing design-token contract, so it later becomes a selectable `Theme` record.

**Architecture:** New tokens are added to `config/tokens.php` with light defaults; the Mystik values live in `config/themes.php` and are applied to `SiteSetting.branding` (the `:root` override path) by an `app:apply-theme` command. Self-hosted OFL fonts, a CSS-only starfield hero partial, and token-driven skin/structure CSS complete the look. No Node, no binary starfield asset.

**Tech Stack:** PHP 8.2+, Laravel 11, Blade, plain CSS (CSS custom properties), self-hosted WOFF2 fonts, SQLite tests, PHPUnit.

Implements `docs/superpowers/specs/2026-06-25-mystik-theme-design.md`. Depends on Plan 1 (token style layer, `SiteSetting`, `layouts.app`, `partials/nav`, Home view).

## Global Constraints

- **Node-free / cPanel-safe:** self-hosted WOFF2 fonts in `public/fonts/`; CSS-only starfield (no JS, no image asset).
- **Theming-ready:** Mystik is a token value set applied via `SiteSetting.branding`; default light theme stays in `config/tokens.php`. No admin theme manager.
- **Style isolation:** all appearance from token-driven CSS; no raw color/font literals in Blade templates. Structure (`public/css/structure.css`) vs. skin (`public/css/skin.css`) split preserved.
- Mystik token values (verbatim): `color-bg #0a0a0f`, `color-bg-alt #14141f`, `color-fg #e8e6f0`, `color-heading #f0a23c`, `color-primary #f0a23c`, `color-accent #c9962f`, `color-muted #9a96ad`, `font-display 'Cinzel', serif`, `font-base 'EB Garamond', serif`, `nav-height 4.5rem`, `hero-overlay rgba(0,0,0,0.55)`.
- SQLite `:memory:` tests; conventional commits; commit each task.

---

### Task 1: Extend the token vocabulary

**Files:**
- Modify: `config/tokens.php` (add new tokens with light defaults)
- Test: `tests/Feature/ThemeTokensTest.php`

**Interfaces:**
- Consumes: `partials.tokens` view (Plan 1) which renders `config('tokens.defaults')` merged with `SiteSetting.branding`.
- Produces: new default tokens `color-bg-alt`, `color-heading`, `font-display`, `nav-height`, `hero-overlay` available as CSS variables.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ThemeTokensTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tokens_have_light_defaults(): void
    {
        $html = view('partials.tokens')->render();

        $this->assertStringContainsString('--color-bg-alt:', $html);
        $this->assertStringContainsString('--color-heading:', $html);
        $this->assertStringContainsString('--font-display:', $html);
        $this->assertStringContainsString('--nav-height: 4rem', $html);
        $this->assertStringContainsString('--hero-overlay:', $html);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ThemeTokensTest`
Expected: FAIL — the new tokens are not in `config/tokens.php` yet.

- [ ] **Step 3: Add the new tokens**

Edit `config/tokens.php` — add these entries inside the `'defaults' => [ ... ]` array (alongside the existing tokens):
```php
        'color-bg-alt'     => '#f5f5f7',
        'color-heading'    => '#111827',
        'font-display'     => "system-ui, -apple-system, 'Segoe UI', Roboto, serif",
        'nav-height'       => '4rem',
        'hero-overlay'     => 'rgba(0,0,0,0)',
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=ThemeTokensTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/tokens.php tests/Feature/ThemeTokensTest.php
git commit -m "feat: extend design-token vocabulary for theming"
```

---

### Task 2: Theme config + `app:apply-theme` command

**Files:**
- Create: `config/themes.php`
- Create: `app/Console/Commands/ApplyTheme.php`
- Test: `tests/Feature/ApplyThemeCommandTest.php`

**Interfaces:**
- Consumes: `SiteSetting::current()` + `branding` attribute (Plan 1).
- Produces:
  - `config('themes.mystik')` — the Mystik token value array.
  - Artisan `app:apply-theme {name}` — `name='mystik'` writes the token set into `SiteSetting.branding`; `name='default'` clears it (`[]`); unknown name exits non-zero.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ApplyThemeCommandTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyThemeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_mystik_writes_gold_dark_tokens(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'mystik'])->assertExitCode(0);

        $branding = SiteSetting::current()->fresh()->branding;
        $this->assertSame('#f0a23c', $branding['color-heading']);
        $this->assertSame('#0a0a0f', $branding['color-bg']);
    }

    public function test_applying_mystik_makes_token_partial_emit_gold(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'mystik']);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-heading: #f0a23c', $html);
    }

    public function test_applying_default_clears_branding(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'mystik']);
        $this->artisan('app:apply-theme', ['name' => 'default'])->assertExitCode(0);

        $this->assertSame([], SiteSetting::current()->fresh()->branding);
    }

    public function test_unknown_theme_fails(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'nope'])->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ApplyThemeCommandTest`
Expected: FAIL — command `app:apply-theme` does not exist.

- [ ] **Step 3: Create the themes config**

Create `config/themes.php`:
```php
<?php

// Named token sets. Applied to SiteSetting.branding by `php artisan app:apply-theme`.
// This is the same :root override path the future admin Theme record will use.
return [
    'mystik' => [
        'color-bg'      => '#0a0a0f',
        'color-bg-alt'  => '#14141f',
        'color-fg'      => '#e8e6f0',
        'color-heading' => '#f0a23c',
        'color-primary' => '#f0a23c',
        'color-accent'  => '#c9962f',
        'color-muted'   => '#9a96ad',
        'font-display'  => "'Cinzel', serif",
        'font-base'     => "'EB Garamond', serif",
        'nav-height'    => '4.5rem',
        'hero-overlay'  => 'rgba(0,0,0,0.55)',
    ],
];
```

- [ ] **Step 4: Create the command**

Create `app/Console/Commands/ApplyTheme.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;

class ApplyTheme extends Command
{
    protected $signature = 'app:apply-theme {name}';

    protected $description = 'Apply a named theme token set to SiteSetting.branding';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === 'default') {
            SiteSetting::current()->update(['branding' => []]);
            $this->info('Theme reset to default.');

            return self::SUCCESS;
        }

        $tokens = config("themes.{$name}");

        if (! is_array($tokens)) {
            $this->error("Unknown theme: {$name}");

            return self::FAILURE;
        }

        SiteSetting::current()->update(['branding' => $tokens]);
        $this->info("Theme '{$name}' applied.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=ApplyThemeCommandTest`
Expected: PASS — all four tests green.

- [ ] **Step 6: Commit**

```bash
git add config/themes.php app/Console/Commands/ApplyTheme.php tests/Feature/ApplyThemeCommandTest.php
git commit -m "feat: add theme config and apply-theme command"
```

---

### Task 3: Self-hosted fonts

**Files:**
- Download: `public/fonts/cinzel-400.woff2`, `cinzel-700.woff2`, `eb-garamond-400.woff2`, `eb-garamond-700.woff2`, `eb-garamond-400-italic.woff2`
- Create: `public/css/fonts.css`
- Modify: `resources/views/layouts/app.blade.php` (link fonts.css before the token partial)
- Test: `tests/Unit/FontsCssTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `@font-face` declarations for `Cinzel` and `EB Garamond`, referenced by the `font-display`/`font-base` tokens once Mystik is active.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/FontsCssTest.php`:
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class FontsCssTest extends TestCase
{
    public function test_fonts_css_declares_the_self_hosted_faces(): void
    {
        $css = file_get_contents(public_path('css/fonts.css'));

        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString("font-family: 'Cinzel'", $css);
        $this->assertStringContainsString("font-family: 'EB Garamond'", $css);
        $this->assertStringContainsString('fonts/cinzel-400.woff2', $css);
        $this->assertStringContainsString('font-display: swap', $css);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=FontsCssTest`
Expected: FAIL — `public/css/fonts.css` does not exist.

- [ ] **Step 3: Download the WOFF2 files (OFL, via the Fontsource CDN)**

Run:
```bash
mkdir -p public/fonts
base=https://cdn.jsdelivr.net/fontsource/fonts
curl -sL "$base/cinzel@latest/latin-400-normal.woff2"        -o public/fonts/cinzel-400.woff2
curl -sL "$base/cinzel@latest/latin-700-normal.woff2"        -o public/fonts/cinzel-700.woff2
curl -sL "$base/eb-garamond@latest/latin-400-normal.woff2"   -o public/fonts/eb-garamond-400.woff2
curl -sL "$base/eb-garamond@latest/latin-700-normal.woff2"   -o public/fonts/eb-garamond-700.woff2
curl -sL "$base/eb-garamond@latest/latin-400-italic.woff2"   -o public/fonts/eb-garamond-400-italic.woff2
for f in public/fonts/*.woff2; do echo "$f: $(wc -c < "$f") bytes"; done
```
Expected: each file is non-trivially sized (> 5 KB). If any is tiny/HTML, the version path changed — confirm the file at `https://www.jsdelivr.com/package/npm/@fontsource/cinzel`.

- [ ] **Step 4: Create fonts.css**

Create `public/css/fonts.css`:
```css
@font-face {
    font-family: 'Cinzel';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('/fonts/cinzel-400.woff2') format('woff2');
}
@font-face {
    font-family: 'Cinzel';
    font-style: normal;
    font-weight: 700;
    font-display: swap;
    src: url('/fonts/cinzel-700.woff2') format('woff2');
}
@font-face {
    font-family: 'EB Garamond';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('/fonts/eb-garamond-400.woff2') format('woff2');
}
@font-face {
    font-family: 'EB Garamond';
    font-style: normal;
    font-weight: 700;
    font-display: swap;
    src: url('/fonts/eb-garamond-700.woff2') format('woff2');
}
@font-face {
    font-family: 'EB Garamond';
    font-style: italic;
    font-weight: 400;
    font-display: swap;
    src: url('/fonts/eb-garamond-400-italic.woff2') format('woff2');
}
```

- [ ] **Step 5: Link fonts.css in the layout**

Edit `resources/views/layouts/app.blade.php` — add the fonts stylesheet immediately **before** the `@include('partials.tokens')` line:
```blade
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    @include('partials.tokens')
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=FontsCssTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add public/fonts public/css/fonts.css resources/views/layouts/app.blade.php tests/Unit/FontsCssTest.php
git commit -m "feat: self-host Cinzel and EB Garamond fonts"
```

---

### Task 4: Home hero block

**Files:**
- Create: `database/migrations/2026_06_29_000001_add_hero_to_site_settings_table.php`
- Modify: `app/Models/SiteSetting.php` (cast `hero`, `heroDefaults()`, include in `defaults()`)
- Create: `resources/views/partials/hero.blade.php`
- Modify: `resources/views/pages/home.blade.php` (include the hero)
- Test: `tests/Feature/HeroTest.php`

**Interfaces:**
- Consumes: `SiteSetting::current()` (Plan 1); `layouts.app`.
- Produces:
  - `SiteSetting::heroDefaults(): array` — `headline`, `subhead`, `cta_label`, `cta_url`.
  - `hero` JSON column cast to `array`, seeded in `defaults()`.
  - `partials.hero` — full-bleed hero reading hero content; included at the top of the Home page.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/HeroTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_hero_headline(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('Personal Horoscope &amp; Magic Services', false)
            ->assertSee('Begin Here');
    }

    public function test_hero_uses_custom_content_when_set(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['hero' => ['headline' => 'Custom Headline'] + $setting->hero]);

        $this->get('/en')->assertSee('Custom Headline');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=HeroTest`
Expected: FAIL — the hero headline is not on the Home page.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_29_000001_add_hero_to_site_settings_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('hero')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('hero');
        });
    }
};
```

- [ ] **Step 4: Update the SiteSetting model**

Edit `app/Models/SiteSetting.php`:
- Add `'hero' => 'array',` to the `$casts` array.
- Add a `heroDefaults()` method and include it in `defaults()`:
```php
    public static function heroDefaults(): array
    {
        return [
            'headline'  => 'Personal Horoscope & Magic Services',
            'subhead'   => 'Enjoy a vivid discussion about your horoscope or birth chart with a certified professional astrologer.',
            'cta_label' => 'Begin Here',
            'cta_url'   => '/en/contact',
        ];
    }
```
In `defaults()`, add `'hero' => self::heroDefaults(),` to the returned array.

- [ ] **Step 5: Create the hero partial**

Create `resources/views/partials/hero.blade.php`:
```blade
@php
    $hero = array_merge(\App\Models\SiteSetting::heroDefaults(), \App\Models\SiteSetting::current()->hero ?? []);
@endphp
<header class="hero">
    <div class="hero-inner">
        <h1 class="hero-title">{{ $hero['headline'] }}</h1>
        <p class="hero-subhead">{{ $hero['subhead'] }}</p>
        @if (!empty($hero['cta_label']))
            <a class="hero-cta" href="{{ $hero['cta_url'] ?? '#' }}">{{ $hero['cta_label'] }}</a>
        @endif
    </div>
</header>
```

- [ ] **Step 6: Include the hero on the Home page**

Replace the contents of `resources/views/pages/home.blade.php`:
```blade
@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    @include('partials.hero')
    <div class="container">
        <h2>{{ config('app.name') }}</h2>
    </div>
@endsection
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=HeroTest`
Expected: PASS — both tests green.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_06_29_000001_add_hero_to_site_settings_table.php app/Models/SiteSetting.php resources/views/partials/hero.blade.php resources/views/pages/home.blade.php tests/Feature/HeroTest.php
git commit -m "feat: add Home hero block backed by SiteSetting"
```

---

### Task 5: Sticky nav + Mystik skin, and activate the theme

**Files:**
- Modify: `resources/views/partials/nav.blade.php` (gold wordmark)
- Modify: `public/css/structure.css` (sticky nav + hero layout)
- Modify: `public/css/skin.css` (token-driven Mystik appearance)
- Test: `tests/Feature/NavBrandingTest.php`, `tests/Unit/SkinCssTest.php` (extend)

**Interfaces:**
- Consumes: tokens (`color-bg`, `color-heading`, `font-display`, `nav-height`, `hero-overlay`, etc.); `partials.hero`.
- Produces: the visible Mystik look — sticky translucent nav with a centered gold `✦ wordmark ✦`, starfield hero, gold headings/links/buttons, dark panel cards.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/NavBrandingTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_shows_the_gold_wordmark(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('✦')
            ->assertSee(config('app.name'));
    }
}
```

Append to `tests/Unit/SkinCssTest.php` (new method in the existing class):
```php
    public function test_skin_defines_hero_and_nav_appearance(): void
    {
        $css = file_get_contents(public_path('css/skin.css'));

        $this->assertStringContainsString('.hero', $css);
        $this->assertStringContainsString('var(--hero-overlay)', $css);
        $this->assertStringContainsString('var(--font-display)', $css);
        $this->assertStringContainsString('var(--color-heading)', $css);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="NavBrandingTest|SkinCssTest"`
Expected: FAIL — the wordmark and the new skin rules are not present.

- [ ] **Step 3: Add the gold wordmark to the nav**

Edit `resources/views/partials/nav.blade.php` — insert the brand wordmark as the first child of the `<div class="container">`, before the `<ul>`:
```blade
        <a class="brand" href="/{{ $locale }}">&#10022; {{ config('app.name') }} &#10022;</a>
```

- [ ] **Step 4: Add sticky-nav + hero structure**

Append to `public/css/structure.css`:
```css

/* Sticky nav + hero structure (layout only). */
nav { position: sticky; top: 0; z-index: 10; min-height: var(--nav-height); display: flex; align-items: center; }
nav .container { display: flex; align-items: center; gap: calc(var(--space-unit) * 6); width: 100%; }
nav .brand { margin-right: auto; white-space: nowrap; }
.hero { position: relative; padding: calc(var(--space-unit) * 28) calc(var(--space-unit) * 4); text-align: center; overflow: hidden; }
.hero-inner { position: relative; z-index: 1; max-width: var(--container-width); margin: 0 auto; }
.hero-cta { display: inline-block; margin-top: calc(var(--space-unit) * 6); padding: calc(var(--space-unit) * 3) calc(var(--space-unit) * 8); }
```

- [ ] **Step 5: Add the Mystik skin (token-driven, with CSS starfield)**

Append to `public/css/skin.css`:
```css

/* Theme skin: appearance only, fully token-driven. */
h1, h2, h3, .brand { font-family: var(--font-display); color: var(--color-heading); letter-spacing: 0.04em; }
.brand { text-decoration: none; font-size: 1.4rem; }
nav { background: color-mix(in srgb, var(--color-bg) 82%, transparent); backdrop-filter: blur(6px); border-bottom: 1px solid var(--color-bg-alt); }
nav a { text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.8rem; text-decoration: none; }

/* CSS starfield (no image asset): layered radial-gradient dots over a dark base. */
.hero {
    background-color: var(--color-bg);
    background-image:
        radial-gradient(1px 1px at 20% 30%, #ffffff 50%, transparent 51%),
        radial-gradient(1px 1px at 70% 60%, #ffffff 50%, transparent 51%),
        radial-gradient(2px 2px at 40% 80%, #ffffff 50%, transparent 51%),
        radial-gradient(1px 1px at 85% 25%, #cbd5e1 50%, transparent 51%),
        radial-gradient(1px 1px at 55% 15%, #ffffff 50%, transparent 51%),
        radial-gradient(1.5px 1.5px at 10% 70%, #e2e8f0 50%, transparent 51%);
    background-repeat: repeat;
    background-size: 320px 320px, 280px 280px, 400px 400px, 360px 360px, 240px 240px, 300px 300px;
}
.hero::before { content: ""; position: absolute; inset: 0; background: var(--hero-overlay); z-index: 0; }
.hero-title { font-size: clamp(2.5rem, 6vw, 4.5rem); margin: 0; }
.hero-subhead { color: var(--color-muted); font-size: 1.2rem; max-width: 32rem; margin: calc(var(--space-unit) * 4) auto 0; }
.hero-cta { background: var(--color-primary); color: var(--color-bg); border-radius: var(--radius); text-decoration: none; font-family: var(--font-display); letter-spacing: 0.08em; text-transform: uppercase; }
.card { background: var(--color-bg-alt); border: 1px solid color-mix(in srgb, var(--color-fg) 12%, transparent); padding: calc(var(--space-unit) * 4); }
button, .hero-cta { cursor: pointer; }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter="NavBrandingTest|SkinCssTest"`
Expected: PASS.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS — every test across all plans green.

- [ ] **Step 8: Activate Mystik on the dev database and verify live**

Run:
```bash
php artisan migrate --force
php artisan app:apply-theme mystik
```
Then start the server (`php artisan serve`) and load `/en`: the nav is dark with a gold `✦ Site Template ✦` wordmark, the hero shows a starfield with a gold Cinzel headline and "Begin Here" button. To revert: `php artisan app:apply-theme default`.

- [ ] **Step 9: Commit**

```bash
git add resources/views/partials/nav.blade.php public/css/structure.css public/css/skin.css tests/Feature/NavBrandingTest.php tests/Unit/SkinCssTest.php
git commit -m "feat: add sticky nav, starfield hero, and Mystik skin"
```

---

## Self-Review

**Spec coverage:**
- §3 token vocabulary (color-bg-alt, color-heading, font-display, nav-height, hero-overlay) — Task 1; Mystik values — Task 2. ✔
- §4.1 self-hosted fonts — Task 3. ✔
- §4.2 activation (`app:apply-theme`, `config/themes.php`) — Task 2. ✔
- §4.3 sticky nav with gold wordmark — Task 5. ✔
- §4.4 Home hero (CSS starfield, hero-overlay, configurable content) — Tasks 4–5. ✔
- §4.5 skin (display font headings, gold links/buttons, dark cards) — Task 5. ✔
- §5 data (`hero` JSON column, defaults) — Task 4. ✔
- §6 testing (apply-theme tokens, partial emits gold, home hero, nav wordmark, fonts.css) — Tasks 2, 3, 4, 5. ✔

**Placeholder scan:** Every step has concrete code/commands. The only runtime-verified item (Fontsource WOFF2 URLs) is flagged with a size check and fallback in Task 3 Step 3. ✔

**Type/name consistency:** token keys (`color-heading`/`font-display`/`nav-height`/`hero-overlay`), `config('themes.mystik')`, `app:apply-theme {name}`, `SiteSetting::heroDefaults()`, `hero` cast, partial names (`partials.hero`/`partials.nav`/`partials.tokens`), and CSS classes (`.hero`/`.hero-inner`/`.hero-title`/`.hero-cta`/`.brand`) are consistent across tasks. ✔

---

## Execution Handoff

Inline execution in this session (consistent with prior plans), via the executing-plans skill.
