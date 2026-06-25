# Reusable Site Template (PHP/Laravel) — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a Laravel 11 site skeleton with an isolated design-token style layer, a `SiteSetting` singleton, locale-prefixed routing (`/en`, `/ro`), and public Home/About/Contact pages with admin-controllable section visibility.

**Architecture:** A single Laravel app. All design tokens live in one PHP config (`config/tokens.php`) and are rendered into a single `:root` `<style>` block, overridden per-site by `SiteSetting.branding` — this is the contract a future theming feature plugs into. Public routes are nested under a `{locale}` prefix guarded by a `SetLocale` middleware. Page controllers read the `SiteSetting` singleton to decide which sections render and which nav links appear.

**Tech Stack:** PHP 8.2+, Laravel 11, Blade, plain CSS (no Node toolchain), MySQL/MariaDB in production, SQLite in-memory for tests, PHPUnit.

This is **Plan 1 of 4** for the spec `docs/superpowers/specs/2026-06-25-reusable-site-template-php-design.md`. Later plans cover Admin+Blog, Payments, and cPanel deployment.

## Global Constraints

- PHP **8.2+**; Laravel **11.x**.
- **No Node.js anywhere** — no Vite build, no npm. CSS is plain static files in `public/css/` plus one inline `<style>` token block. Browser JS is added later (Trix) as a pre-compiled asset.
- **One token layer:** every design token (color, font, spacing, radius, shadow) is declared once in `config/tokens.php`. Blade/CSS reference only `var(--<token>)` — **no raw hex/font/px literals** in templates or component CSS.
- **Structure vs. skin split:** `public/css/structure.css` (layout) is separate from `public/css/skin.css` (token-driven appearance).
- All server code is PHP. Production DB is MySQL/MariaDB; tests run on SQLite `:memory:`.
- Supported locales: `en` (default), `ro`. Unsupported locales return 404.
- Conventional-commit messages; commit at the end of every task.

---

### Task 0: Local development environment setup

> One-time per workstation. This is an environment task, not a code task — no commit. The project root has no PHP install yet (verified: `php` is not on PATH). This installs only the **local dev** toolchain; cPanel production extensions are handled in the Deployment plan.

**Files:** none (installs tooling on the machine).

**Interfaces:**
- Produces: working `php` (8.2+), `composer`, and the PHP extensions the foundation needs, all on PATH.

- [ ] **Step 1: Install PHP 8.2+ and Composer**

Option A — Chocolatey (run PowerShell **as Administrator**):
```powershell
choco install php composer -y
```

Option B — Manual: download the **PHP 8.2+ Thread Safe x64** zip from https://windows.php.net/download/, extract to `C:\php`, add `C:\php` to your PATH; then install Composer from https://getcomposer.org/Composer-Setup.exe.

Open a **new** terminal afterward so PATH changes take effect.

- [ ] **Step 2: Verify PHP and Composer are on PATH**

Run: `php -v`
Expected: `PHP 8.2.x` (or higher).

Run: `composer -V`
Expected: `Composer version 2.x`.

- [ ] **Step 3: Enable and verify the required extensions**

In `C:\php\php.ini` (copy `php.ini-development` to `php.ini` if it does not exist), ensure these lines are **uncommented** (no leading `;`):
```
extension=pdo_sqlite
extension=sqlite3
extension=mbstring
extension=openssl
extension=fileinfo
extension=gd
extension=curl
extension=pdo_mysql
```

Then run: `php -m`
Expected: the output includes `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `gd`, `curl`, and `pdo_mysql`.

(`pdo_sqlite` + `sqlite3` power the test harness; `pdo_mysql` is for the real app DB; `gd` is for later image processing.)

- [ ] **Step 4: Confirm Git is available**

Run: `git --version`
Expected: a version string. If missing, install Git for Windows from https://git-scm.com/download/win.

---

### Task 1: Scaffold Laravel app + test harness

**Files:**
- Create: entire Laravel skeleton at the project root (alongside existing `docs/`)
- Modify: `phpunit.xml` (enable SQLite in-memory test DB)
- Modify: `.env` (set `APP_NAME`, leave DB for later tasks)

**Interfaces:**
- Consumes: nothing (first task).
- Produces: a booting Laravel app; `php artisan test` runs green; git repo initialized.

- [ ] **Step 1: Scaffold Laravel into the project root without clobbering `docs/`**

Run (Git Bash):
```bash
cd /c/MINE
composer create-project laravel/laravel _laravel_scaffold "^11.0"
cp -r _laravel_scaffold/. /c/MINE/ClaudeSiteTemplate/
rm -rf _laravel_scaffold
cd /c/MINE/ClaudeSiteTemplate
php artisan key:generate
```
Expected: `vendor/`, `app/`, `routes/`, `artisan`, `.env` now exist in the project; `docs/` is untouched.

- [ ] **Step 2: Confirm the SQLite PHP extensions are present**

Run: `php -m`
Expected: the output list includes both `pdo_sqlite` and `sqlite3`. If either is missing, enable it in `php.ini` (uncomment `extension=pdo_sqlite` and `extension=sqlite3`) before continuing — the in-memory test DB cannot work without them.

- [ ] **Step 3: Point the test suite at an in-memory SQLite database**

Laravel 11 ships `phpunit.xml` with the two SQLite lines **commented out**. Open `phpunit.xml`, find the `<php>` block (it already contains `APP_ENV`, `CACHE_STORE`, `QUEUE_CONNECTION`, etc.), and **uncomment** these two lines so they read exactly:
```xml
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
```

How this harness works (no further wiring needed):
- **`:memory:`** keeps the entire test database in RAM for the test run only — fast, fully isolated, and it never touches the production MySQL/MariaDB data. Laravel holds one SQLite connection open for the run, so the schema persists across a test's queries.
- Test classes use the **`RefreshDatabase`** trait (added per-test in later tasks): it runs your migrations into the in-memory DB and wraps each test in a transaction that rolls back afterward, so every test starts from a clean, migrated schema.
- **Prod vs. test DB difference is safe:** production uses MySQL's native `JSON` column type; SQLite has none, so Laravel's `json()` migration maps to `TEXT`. Models read/write through the Eloquent `array` cast (JSON encode/decode in PHP), so behavior is identical on both engines — the tests remain representative.

- [ ] **Step 4: Set the app name**

Edit `.env`:
```
APP_NAME="Site Template"
```

- [ ] **Step 5: Run the default test suite to verify the harness**

Run: `php artisan test`
Expected: PASS — the stock `Tests\Unit\ExampleTest` and `Tests\Feature\ExampleTest` both pass against the in-memory SQLite DB.

- [ ] **Step 6: Initialize git and commit**

```bash
git init
printf '\n/vendor\n' >> .gitignore   # confirm vendor ignored (Laravel default already ignores it)
git add -A
git commit -m "chore: scaffold Laravel 11 app with SQLite test harness"
```

---

### Task 2: `SiteSetting` singleton model

**Files:**
- Create: `database/migrations/2026_06_25_000001_create_site_settings_table.php`
- Create: `app/Models/SiteSetting.php`
- Test: `tests/Unit/SiteSettingTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `SiteSetting::current(): SiteSetting` — returns the singleton row (id=1), creating it with defaults if missing.
  - `SiteSetting::defaults(): array` — default attribute array.
  - `$setting->sectionVisible(string $key): bool` — true unless explicitly disabled.
  - JSON-cast attributes: `sections`, `nav`, `contact`, `branding`, `locales` (all `array`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SiteSettingTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_creates_and_reuses_a_single_row(): void
    {
        $first = SiteSetting::current();
        $this->assertSame(1, $first->id);
        $this->assertSame(1, SiteSetting::count());

        SiteSetting::current();
        $this->assertSame(1, SiteSetting::count());
    }

    public function test_defaults_enable_sections_and_set_locales(): void
    {
        $setting = SiteSetting::current();
        $this->assertTrue($setting->sectionVisible('blog'));
        $this->assertTrue($setting->sectionVisible('about'));
        $this->assertSame('en', $setting->locales['default']);
        $this->assertSame(['en', 'ro'], $setting->locales['supported']);
    }

    public function test_section_can_be_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['blog' => false] + $setting->sections]);
        $this->assertFalse($setting->fresh()->sectionVisible('blog'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=SiteSettingTest`
Expected: FAIL — `Class "App\Models\SiteSetting" not found`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_25_000001_create_site_settings_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->json('sections')->nullable();
            $table->json('nav')->nullable();
            $table->json('contact')->nullable();
            $table->json('branding')->nullable();
            $table->json('locales')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/SiteSetting.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sections' => 'array',
        'nav'      => 'array',
        'contact'  => 'array',
        'branding' => 'array',
        'locales'  => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], static::defaults());
    }

    public static function defaults(): array
    {
        return [
            'sections' => ['about' => true, 'blog' => true, 'contact' => true],
            'nav'      => [],
            'contact'  => ['email' => '', 'phone' => '', 'address' => ''],
            'branding' => [],
            'locales'  => ['default' => 'en', 'supported' => ['en', 'ro']],
        ];
    }

    public function sectionVisible(string $key): bool
    {
        return (bool) ($this->sections[$key] ?? true);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=SiteSettingTest`
Expected: PASS — all three tests green.

- [ ] **Step 6: Commit**

```bash
git add app/Models/SiteSetting.php database/migrations/2026_06_25_000001_create_site_settings_table.php tests/Unit/SiteSettingTest.php
git commit -m "feat: add SiteSetting singleton with section visibility"
```

---

### Task 3: Style-isolation layer (token config + injection partial + layout)

**Files:**
- Create: `config/tokens.php`
- Create: `resources/views/partials/tokens.blade.php`
- Create: `resources/views/layouts/app.blade.php`
- Create: `public/css/structure.css`
- Create: `public/css/skin.css`
- Test: `tests/Feature/StyleTokensTest.php`

**Interfaces:**
- Consumes: `SiteSetting::current()->branding` (Task 2).
- Produces:
  - `config('tokens.defaults')` — `['<token-name>' => '<css value>', ...]` single source of truth.
  - View `partials.tokens` — renders one `:root { ... }` `<style>` block from defaults merged with branding overrides.
  - View `layouts.app` — base HTML shell yielding `content`, including the tokens partial and the two static stylesheets.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StyleTokensTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StyleTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_partial_renders_default_variables(): void
    {
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-primary: #2563eb', $html);
        $this->assertStringContainsString('--font-base:', $html);
    }

    public function test_branding_overrides_a_default_token(): void
    {
        SiteSetting::current()->update(['branding' => ['color-primary' => '#ff0000']]);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-primary: #ff0000', $html);
        $this->assertStringNotContainsString('--color-primary: #2563eb', $html);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=StyleTokensTest`
Expected: FAIL — `View [partials.tokens] not found`.

- [ ] **Step 3: Create the token source of truth**

Create `config/tokens.php`:
```php
<?php

// Single source of truth for all design tokens. Every Blade/CSS reference
// uses var(--<key>); never a raw literal. SiteSetting.branding overrides these.
return [
    'defaults' => [
        'color-primary'    => '#2563eb',
        'color-accent'     => '#7c3aed',
        'color-bg'         => '#ffffff',
        'color-fg'         => '#111827',
        'color-muted'      => '#6b7280',
        'font-base'        => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'font-heading'     => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'space-unit'       => '0.25rem',
        'radius'           => '0.5rem',
        'shadow'           => '0 1px 3px rgba(0,0,0,0.1)',
        'container-width'  => '64rem',
    ],
];
```

- [ ] **Step 4: Create the token injection partial**

Create `resources/views/partials/tokens.blade.php`:
```blade
@php
    $tokens = array_merge(
        config('tokens.defaults'),
        \App\Models\SiteSetting::current()->branding ?? []
    );
@endphp
<style>
:root {
@foreach ($tokens as $name => $value)
    --{{ $name }}: {{ $value }};
@endforeach
}
</style>
```

- [ ] **Step 5: Create the base layout and static stylesheets**

Create `resources/views/layouts/app.blade.php`:
```blade
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @include('partials.tokens')
    <link rel="stylesheet" href="{{ asset('css/structure.css') }}">
    <link rel="stylesheet" href="{{ asset('css/skin.css') }}">
</head>
<body>
    @yield('content')
</body>
</html>
```

Create `public/css/structure.css`:
```css
/* Structural layer: layout only, no appearance tokens. */
* { box-sizing: border-box; }
body { margin: 0; }
.container { max-width: var(--container-width); margin: 0 auto; padding: calc(var(--space-unit) * 4); }
nav ul { display: flex; gap: calc(var(--space-unit) * 4); list-style: none; padding: 0; }
```

Create `public/css/skin.css`:
```css
/* Skin layer: appearance only, fully token-driven. */
body { background: var(--color-bg); color: var(--color-fg); font-family: var(--font-base); }
h1, h2, h3 { font-family: var(--font-heading); }
a { color: var(--color-primary); }
.muted { color: var(--color-muted); }
.card { border-radius: var(--radius); box-shadow: var(--shadow); }
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=StyleTokensTest`
Expected: PASS — both tests green.

- [ ] **Step 7: Commit**

```bash
git add config/tokens.php resources/views/partials/tokens.blade.php resources/views/layouts/app.blade.php public/css/structure.css public/css/skin.css tests/Feature/StyleTokensTest.php
git commit -m "feat: add isolated design-token style layer with branding overrides"
```

---

### Task 4: Locale-prefixed routing + `SetLocale` middleware

**Files:**
- Create: `app/Http/Middleware/SetLocale.php`
- Modify: `bootstrap/app.php` (register the route-group/locale and middleware alias)
- Modify: `routes/web.php` (root redirect + `{locale}` group with a minimal home route)
- Create: `resources/views/pages/home.blade.php`
- Test: `tests/Feature/LocaleRoutingTest.php`

**Interfaces:**
- Consumes: `layouts.app` (Task 3).
- Produces:
  - `App\Http\Middleware\SetLocale` — 404s unsupported locales, calls `app()->setLocale()`.
  - Middleware alias `setlocale`.
  - Route group `Route::prefix('{locale}')->where(['locale' => 'en|ro'])->middleware('setlocale')` containing a named `home` route.
  - Root `/` redirects to `/{default-locale}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LocaleRoutingTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_default_locale(): void
    {
        $this->get('/')->assertRedirect('/en');
    }

    public function test_supported_locale_sets_application_locale(): void
    {
        $this->get('/ro')->assertOk();
        $this->assertSame('ro', app()->getLocale());
    }

    public function test_unsupported_locale_returns_404(): void
    {
        $this->get('/de')->assertNotFound();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=LocaleRoutingTest`
Expected: FAIL — `/` returns the default welcome page (200), not a redirect to `/en`.

- [ ] **Step 3: Create the `SetLocale` middleware**

Create `app/Http/Middleware/SetLocale.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'ro'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        app()->setLocale($locale);

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware alias**

Edit `bootstrap/app.php` — inside the `->withMiddleware(function (Middleware $middleware) { ... })` closure, add:
```php
        $middleware->alias([
            'setlocale' => \App\Http\Middleware\SetLocale::class,
        ]);
```

- [ ] **Step 5: Define the routes**

Replace the contents of `routes/web.php` with:
```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/' . config('app.locale')));

Route::prefix('{locale}')
    ->where(['locale' => 'en|ro'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', fn () => view('pages.home'))->name('home');
    });
```

- [ ] **Step 6: Create the minimal home view**

Create `resources/views/pages/home.blade.php`:
```blade
@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <div class="container">
        <h1>{{ config('app.name') }}</h1>
    </div>
@endsection
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=LocaleRoutingTest`
Expected: PASS — all three tests green.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/SetLocale.php bootstrap/app.php routes/web.php resources/views/pages/home.blade.php tests/Feature/LocaleRoutingTest.php
git commit -m "feat: add locale-prefixed routing with SetLocale middleware"
```

---

### Task 5: Public pages + section visibility + nav

**Files:**
- Create: `app/Http/Controllers/PageController.php`
- Modify: `routes/web.php` (wire controller; add about/contact routes)
- Create: `resources/views/pages/about.blade.php`
- Create: `resources/views/pages/contact.blade.php`
- Create: `resources/views/partials/nav.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (include nav)
- Test: `tests/Feature/PublicPagesTest.php`

**Interfaces:**
- Consumes: `SiteSetting::current()` + `sectionVisible()` (Task 2); `setlocale` group + named routes (Task 4).
- Produces:
  - `App\Http\Controllers\PageController` with `home()`, `about()`, `contact()` — about/contact `abort(404)` when their section is hidden.
  - Named routes `home`, `about`, `contact` (all inside the locale group).
  - `partials.nav` — renders links only for visible sections, using the current locale.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PublicPagesTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_when_enabled(): void
    {
        $this->get('/en/about')->assertOk();
    }

    public function test_about_page_404s_when_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['about' => false] + $setting->sections]);

        $this->get('/en/about')->assertNotFound();
    }

    public function test_nav_hides_contact_link_when_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['contact' => false] + $setting->sections]);

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('/en/contact');
    }

    public function test_nav_shows_contact_link_when_enabled(): void
    {
        $this->get('/en')->assertSee('/en/contact');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PublicPagesTest`
Expected: FAIL — `/en/about` returns 404 (route not defined yet).

- [ ] **Step 3: Create the page controller**

Create `app/Http/Controllers/PageController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home');
    }

    public function about()
    {
        abort_unless(SiteSetting::current()->sectionVisible('about'), 404);

        return view('pages.about');
    }

    public function contact()
    {
        abort_unless(SiteSetting::current()->sectionVisible('contact'), 404);

        return view('pages.contact', ['contact' => SiteSetting::current()->contact]);
    }
}
```

- [ ] **Step 4: Wire the routes**

Replace the `{locale}` group body in `routes/web.php` so the group reads:
```php
Route::prefix('{locale}')
    ->where(['locale' => 'en|ro'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\PageController::class, 'home'])->name('home');
        Route::get('/about', [\App\Http\Controllers\PageController::class, 'about'])->name('about');
        Route::get('/contact', [\App\Http\Controllers\PageController::class, 'contact'])->name('contact');
    });
```

- [ ] **Step 5: Create the about and contact views**

Create `resources/views/pages/about.blade.php`:
```blade
@extends('layouts.app')

@section('title', 'About — ' . config('app.name'))

@section('content')
    <div class="container">
        <h1>About</h1>
    </div>
@endsection
```

Create `resources/views/pages/contact.blade.php`:
```blade
@extends('layouts.app')

@section('title', 'Contact — ' . config('app.name'))

@section('content')
    <div class="container">
        <h1>Contact</h1>
        <p class="muted">{{ $contact['email'] ?? '' }}</p>
    </div>
@endsection
```

- [ ] **Step 6: Create the nav partial and include it in the layout**

Create `resources/views/partials/nav.blade.php`:
```blade
@php
    $setting = \App\Models\SiteSetting::current();
    $locale = app()->getLocale();
@endphp
<nav>
    <div class="container">
        <ul>
            <li><a href="/{{ $locale }}">Home</a></li>
            @if ($setting->sectionVisible('about'))
                <li><a href="/{{ $locale }}/about">About</a></li>
            @endif
            @if ($setting->sectionVisible('blog'))
                <li><a href="/{{ $locale }}/blog">Blog</a></li>
            @endif
            @if ($setting->sectionVisible('contact'))
                <li><a href="/{{ $locale }}/contact">Contact</a></li>
            @endif
        </ul>
    </div>
</nav>
```

Edit `resources/views/layouts/app.blade.php` — insert the nav include immediately after the opening `<body>` tag:
```blade
<body>
    @include('partials.nav')
    @yield('content')
</body>
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=PublicPagesTest`
Expected: PASS — all four tests green.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS — every test from Tasks 1–5 green.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/PageController.php routes/web.php resources/views/pages/about.blade.php resources/views/pages/contact.blade.php resources/views/partials/nav.blade.php resources/views/layouts/app.blade.php tests/Feature/PublicPagesTest.php
git commit -m "feat: add public pages with section visibility and nav"
```

---

## Self-Review

**Spec coverage (Plan 1 slice):**
- §3 stack (Laravel 11, Blade, MySQL/SQLite-in-test, plain CSS, no Node) — Task 1, Global Constraints. ✔
- §5.1 public site + section visibility (`SiteSetting`, hide → 404, nav omits) — Tasks 2, 5. ✔
- §5.3 style isolation (one token layer, structure/skin split, single injection point, branding override) — Task 3. ✔
- §5.5 i18n (locale-prefixed routing `/en` `/ro`, default + unsupported→404) — Task 4. ✔
- §6 data model `SiteSetting` (sections, nav, contact, branding, locales JSON) — Task 2. ✔
- Deferred to later plans (correctly out of this slice): blog (§5.2), auth/admin (§5.4), payments (§5.6), media (§5.7), deployment (§7). ✔

**Placeholder scan:** No TBD/TODO; every code step shows complete code; every test step shows the command and expected result. ✔

**Type/name consistency:** `SiteSetting::current()`, `sectionVisible()`, `config('tokens.defaults')`, `branding`, middleware alias `setlocale`, and route names `home`/`about`/`contact` are used identically wherever they appear across tasks. ✔

---

## Execution Handoff

Covered by this plan: a working multilingual brochure site with an isolated style layer. The next plan (**Admin + Blog**) builds on `SiteSetting`, the locale group, and the token layer established here.
