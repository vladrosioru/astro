# Admin Module Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the admin module its own simple functional design, fully independent of the active theme — no theme CSS, tokens, fonts or JS load on any `/admin*` page.

**Architecture:** A dedicated `layouts/admin.blade.php` loads exactly one stylesheet (`public/css/admin.css`) whose palette lives in `--adm-*` properties on `.adm-body`. All admin views move onto it and adopt an `adm-`-prefixed class vocabulary, never reusing the theme-styled names (`.container`, `.card`, `.btn`, `.muted`). The one screen that genuinely needs the theme — the post editor's Preview — renders the theme inside an isolated `<iframe srcdoc>`.

**Tech Stack:** Laravel 12 Blade, PHPUnit 12, plain CSS (no build step), CKEditor 5 (self-hosted UMD).

Spec: [`docs/superpowers/specs/2026-08-01-admin-module-redesign-design.md`](../specs/2026-08-01-admin-module-redesign-design.md)
Mock (visual source of truth): <https://claude.ai/code/artifact/eff709e7-dfa1-484c-a81e-8ced511c7f78>

## Global Constraints

- **TDD, always.** One failing PHPUnit test first (`php artisan test --filter=<name>`), confirm it fails for the right reason, minimal code to green, then the **full** suite (`php artisan test`) before committing.
- **No theme dependency in admin.** `public/css/admin.css` must never reference `var(--color-*)`, `var(--font-*)`, `var(--radius)`, `var(--space-unit)`, `var(--shadow)`, `var(--container-width)` or `var(--nav-height)`. Its own palette uses the `--adm-` prefix.
- **Class vocabulary.** Admin markup uses `adm-` prefixed classes only. Never `.container`, `.card`, `.card__*`, `.btn`, `.btn-primary`, `.muted`, `.blog-grid`, `.journal-hero` on an admin page. Two exceptions, both required by existing passing tests: `.date-locked` on the locked date input, and the `<table>` element on the Database screen.
- **Behaviour is frozen.** Same routes, controller actions, request field names, validation, confirm dialogs, CKEditor plugin list and upload endpoint. Only presentation and the preview mechanism change.
- **Palette (exact values):** ground `#0f1216`, panel `#161b21`, raised `#1d242c`, line `#2a323c`, line-soft `#202730`, text `#e3e8ef`, dim `#93a0b0`, faint `#66717f`, accent `#6f9dff`, accent-dim `#25324b`, ok `#4bbf92`, warn `#cf9a4e`, danger `#e2606b`, radius `6px`.
- **Fonts:** `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif` for UI; `ui-monospace, "Cascadia Mono", Consolas, monospace` for data. No webfont, no `@font-face`.
- **Docs in the same change** (CLAUDE.md rule): `README.md` and `public/themes/AUTHORING.md` in Task 8. No `theme.json` / `theme.schema.json` change — no theme asset, token or view slot moves.
- Local PHP is 8.3; run tests with `php artisan test`.

## File Structure

| File | Responsibility |
| --- | --- |
| `resources/views/layouts/admin.blade.php` | **Create.** Admin master layout: `<body class="adm-body">`, links `css/admin.css` only, `@yield('content')`, `@stack('head')`, `@stack('scripts')`. No tokens, no theme loop, no nav/footer/back-to-top. |
| `resources/views/admin/partials/_topbar.blade.php` | **Create.** The admin top bar: wordmark, section links (`Route::has()`-guarded, active-marked), View site, account email, logout form. |
| `resources/views/admin/partials/_row.blade.php` | **Create.** One list row shared by Posts and Authors: thumb (square or round), title link, sub line, meta slots, action slot. |
| `public/css/admin.css` | **Create.** The whole admin design system: palette, reset, top bar, panel, tiles, rows, pills, buttons, fields, tabs, chips, editor wrapper + CKEditor chrome vars, login card, theme grid, tables. |
| `resources/views/admin/*.blade.php` (8 views) | **Modify.** Move to `layouts.admin`, adopt `adm-` classes, drop inline `style=` attributes. |
| `app/Http/Controllers/Admin/DashboardController.php` | **Modify.** Supply the four dashboard tile figures. |
| `resources/views/layouts/app.blade.php` | **Modify.** Delete the now-dead `@unless(request()->routeIs('admin.*'))` branch. |
| `tests/Feature/AdminLayoutTest.php` | **Create.** Theme-independence + layout assertions. |
| `tests/Feature/AdminTopBarTest.php` | **Create.** Top bar contents, active state, absence on login. |
| `tests/Unit/AdminCssTest.php` | **Create.** `admin.css` carries no theme token reference and does own the CKEditor chrome vars. |
| `tests/Feature/AdminPostFormTest.php` | **Modify.** Three existing tests re-point from `.article-paper`/page-level `article.css` to `.adm-editor`/the preview frame manifest. |

---

### Task 1: Admin layout, stylesheet, login screen

**Files:**
- Create: `resources/views/layouts/admin.blade.php`, `public/css/admin.css`, `tests/Feature/AdminLayoutTest.php`, `tests/Unit/AdminCssTest.php`
- Modify: `resources/views/admin/login.blade.php`
- Test: `tests/Feature/AdminLayoutTest.php`, `tests/Unit/AdminCssTest.php`

**Interfaces:**
- Produces: layout `layouts.admin` with sections `title`, `content` and stacks `head`, `scripts`; body class `adm-body`; stylesheet `public/css/admin.css` exposing the `--adm-*` palette and the component classes listed in Global Constraints.
- Consumes: `versioned_asset()` from `app/Support/helpers.php`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_the_admin_stylesheet_and_no_theme_css(): void
    {
        $content = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('css/admin.css', $content);

        // The admin module is theme-independent: no theme package asset and no
        // :root token block may reach an admin page.
        $this->assertStringNotContainsString('/themes/theme_', $content);
        $this->assertStringNotContainsString('--color-primary', $content);
    }

    public function test_login_page_has_no_site_nav_footer_or_back_to_top(): void
    {
        $content = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('nav-toggle-input', $content);
        $this->assertStringNotContainsString('site-footer', $content);
        $this->assertStringNotContainsString('back-to-top', $content);
    }

    public function test_login_page_body_carries_the_admin_class(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('<body class="adm-body">', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminLayoutTest`
Expected: FAIL — the login page still extends `layouts.app`, so `css/admin.css` is absent and `/themes/theme_` is present.

- [ ] **Step 3: Write the admin layout**

`resources/views/layouts/admin.blade.php`:

```blade
{{-- Admin master layout. Deliberately theme-free: no partials.tokens, no
     ThemeManager asset loops, no theme:: views, no site nav/footer. The admin
     module owns its whole appearance via public/css/admin.css, so switching
     the active theme cannot change an admin screen. The one place the theme
     is still needed — the post editor's Preview — loads it inside an isolated
     iframe (see admin/posts/_form.blade.php). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ versioned_asset('css/admin.css') }}">
    @stack('head')
</head>
<body class="adm-body">
    @yield('content')
    @stack('scripts')
</body>
</html>
```

- [ ] **Step 4: Write `public/css/admin.css`**

Port the mock's stylesheet, renaming every class to the `adm-` vocabulary. Required blocks, in this order:

1. Header comment stating the file is theme-independent and must never reference a theme token.
2. `.adm-body { --adm-bg: #0f1216; --adm-surface: #161b21; --adm-raised: #1d242c; --adm-line: #2a323c; --adm-line-soft: #202730; --adm-fg: #e3e8ef; --adm-fg-dim: #93a0b0; --adm-fg-faint: #66717f; --adm-accent: #6f9dff; --adm-accent-dim: #25324b; --adm-ok: #4bbf92; --adm-warn: #cf9a4e; --adm-danger: #e2606b; --adm-radius: 6px; --adm-sans: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; --adm-mono: ui-monospace, "Cascadia Mono", Consolas, monospace; margin: 0; background: var(--adm-bg); color: var(--adm-fg); font: 15px/1.5 var(--adm-sans); }` plus `.adm-body * { box-sizing: border-box; }`.
3. Top bar: `.adm-bar`, `.adm-bar__brand` (+ `b`/`span` children), `.adm-bar__link`, `.adm-bar__link.is-on` (accent bottom border), `.adm-bar__spacer`, `.adm-bar__site`, `.adm-bar__who` (mono), `.adm-linkbtn` (danger on hover).
4. Page frame: `.adm-main` (max-width 1180px, centered, padding 26px 22px 34px), `.adm-head`, `.adm-head__title`, `.adm-head__count` (mono), `.adm-head__grow`.
5. `.adm-panel`, `.adm-panel__head`, `.adm-panel__body`, `.adm-panel--danger` (red border + red `h3`), `.adm-note`.
6. `.adm-tiles` (auto-fit grid, min 190px), `.adm-tile`, `.adm-tile__k` (uppercase, tracked), `.adm-tile__v` (1.7rem, tabular-nums), `.adm-tile__s`, `.adm-tile__s.is-warn`.
7. `.adm-rows`, `.adm-row` (flex, hairline top border, raised background on hover), `.adm-row__thumb` (38px, `.is-round` → 50%), `.adm-row__main`, `.adm-row__sub`, `.adm-row__meta` (mono, tabular-nums), `.adm-row__acts`.
8. `.adm-pill` + `--ok` / `--draft` / `--neutral` modifiers.
9. `.adm-btn`, `.adm-btn--primary`, `.adm-btn--danger`, `.adm-btn--sm`, and `.adm-btn[disabled]` (0.5 opacity, default cursor).
10. Forms: `.adm-field`, `.adm-field > label` (uppercase caption), `.adm-field__hint`, `.adm-two` (2-col grid), and element rules for `input`/`select`/`textarea` **scoped under `.adm-body`**, plus `:focus-visible { outline: 2px solid var(--adm-accent); }`, `input[readonly]`, and `.date-locked { border-color: var(--adm-danger); color: var(--adm-danger); background: #241a1d; }`.
11. `.adm-tabs`, `.adm-tabs__btn`, `.adm-tabs__btn.is-on`; `.adm-filters`, `.adm-chip`, `.adm-chip.is-on`, `.adm-search`.
12. Editor: `.adm-grid` (`minmax(0,1fr) 268px`), `.adm-rail` (sticky, top 12px), `.adm-editor`, `.adm-editor__frame` (iframe: width 100%, border, radius, background `#fff` is wrong here — use `var(--adm-bg)` only as a fallback; the frame paints its own themed background), `.adm-editor__preview[hidden]`.
13. CKEditor chrome, scoped to `.adm-editor`, restating each derived var CKEditor resolves at `:root` (see `public/css/article.css` for the full list this mirrors): `--ck-color-base-background`, `--ck-color-base-foreground`, `--ck-color-base-text`, `--ck-color-base-border`, `--ck-color-toolbar-background`, `--ck-color-toolbar-border`, `--ck-color-button-default-hover-background`, `--ck-color-button-on-background`, `--ck-color-dropdown-panel-background`, `--ck-color-dropdown-panel-border`, `--ck-color-panel-background`, `--ck-color-panel-border`, `--ck-color-text` — all in `--adm-*` terms.
14. `.adm-table` (full width, collapsed borders, hairline row separators, mono cells, left-aligned uppercase `th`).
15. `.adm-theme-grid`, `.adm-theme-card`, `.adm-theme-shot` (16/10 aspect, `object-fit: cover`).
16. `.adm-login`, `.adm-login__card` (340px), `.adm-err` (danger left rule).
17. `@media (max-width: 760px)`: `.adm-grid` → one column, `.adm-rail` → static, `.adm-two` → one column, `.adm-bar` wraps.
18. `@media (prefers-reduced-motion: reduce) { .adm-body * { transition: none !important; animation: none !important; } }`

- [ ] **Step 5: Rewrite the login view**

`resources/views/admin/login.blade.php`:

```blade
@extends('layouts.admin')

@section('title', 'Sign in')

@section('content')
    <div class="adm-login">
        <div class="adm-panel adm-login__card">
            <div class="adm-panel__body">
                <p class="adm-bar__brand adm-bar__brand--plain"><b>{{ config('app.name') }}</b><span>admin</span></p>
                <h2>Sign in</h2>
                @if ($errors->any())
                    <p class="adm-err">{{ $errors->first() }}</p>
                @endif
                <form method="POST" action="{{ route('admin.login.attempt') }}">
                    @csrf
                    <div class="adm-field">
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="adm-field">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" required>
                    </div>
                    <button class="adm-btn adm-btn--primary" type="submit" style="width:100%;justify-content:center">Log in</button>
                </form>
            </div>
        </div>
    </div>
@endsection
```

- [ ] **Step 6: Run the layout test**

Run: `php artisan test --filter=AdminLayoutTest`
Expected: PASS.

- [ ] **Step 7: Write the CSS unit test**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminCssTest extends TestCase
{
    public function test_admin_css_references_no_theme_token(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        // The admin module owns its palette. A var(--color-…) here would make
        // the admin's appearance a function of the active theme again.
        foreach (['--color-', '--font-base', '--font-heading', '--font-display',
                  '--space-unit', '--container-width', '--nav-height'] as $token) {
            $this->assertStringNotContainsString('var('.$token, $css);
        }
    }

    public function test_admin_css_reskins_ckeditor_chrome_in_admin_terms(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        // The post editor no longer loads article.css, so the CKEditor UI
        // chrome must be re-skinned here instead — in --adm-* terms.
        $this->assertMatchesRegularExpression(
            '/\.adm-editor\s*\{[^}]*--ck-color-base-background:\s*var\(--adm-[a-z-]+\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.adm-editor\s*\{[^}]*--ck-color-toolbar-background:\s*var\(--adm-[a-z-]+\)/s',
            $css
        );
    }
}
```

- [ ] **Step 8: Run it, then the full suite**

Run: `php artisan test --filter=AdminCssTest` → PASS
Run: `php artisan test` → all green (the login page's own auth tests assert redirects and text, not markup).

- [ ] **Step 9: Commit**

```bash
git add resources/views/layouts/admin.blade.php public/css/admin.css resources/views/admin/login.blade.php tests/Feature/AdminLayoutTest.php tests/Unit/AdminCssTest.php
git commit -m "feat: theme-independent admin layout and stylesheet"
```

---

### Task 2: Admin top bar + dashboard

**Files:**
- Create: `resources/views/admin/partials/_topbar.blade.php`, `tests/Feature/AdminTopBarTest.php`
- Modify: `resources/views/admin/dashboard.blade.php`, `app/Http/Controllers/Admin/DashboardController.php`
- Test: `tests/Feature/AdminTopBarTest.php`

**Interfaces:**
- Consumes: `layouts.admin` from Task 1.
- Produces: `@include('admin.partials._topbar')`, which reads `request()->routeIs()` itself — includers pass nothing. Dashboard view variables: `$postCount`, `$draftCount`, `$authorCount`, `$authorsWithoutPosts`, `$backupCount`, `$latestBackupAt` (`?Carbon`), `$activeTheme` (array from `ThemeManager::manifest()`), `$recentPosts` (`Collection<Post>`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTopBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_bar_lists_every_admin_section_and_marks_the_current_one(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        foreach (['Dashboard', 'Posts', 'Authors', 'Themes', 'Database'] as $section) {
            $this->assertStringContainsString('>'.$section.'</a>', $content);
        }

        // Only the current section carries the active marker.
        $this->assertMatchesRegularExpression(
            '/class="adm-bar__link is-on"[^>]*>Dashboard</',
            $content
        );
    }

    public function test_top_bar_offers_the_public_site_and_log_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('View site')
            ->assertSee($admin->email)
            ->assertSee(route('admin.logout'), false);
    }

    public function test_login_page_has_no_top_bar(): void
    {
        // Nothing to navigate to before signing in.
        $this->get('/admin/login')->assertOk()->assertDontSee('adm-bar__link', false);
    }

    public function test_dashboard_shows_content_counts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Posts')
            ->assertSee('Authors')
            ->assertSee('Active theme');
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test --filter=AdminTopBarTest`
Expected: FAIL — `adm-bar__link` does not exist yet.

- [ ] **Step 3: Write the top bar partial**

```blade
{{-- Admin top bar. Section links are Route::has()-guarded so an unbuilt
     section (Payments, Plan 3) only appears once its routes exist. --}}
<header class="adm-bar">
    <a class="adm-bar__brand" href="{{ route('admin.dashboard') }}"><b>{{ config('app.name') }}</b><span>admin</span></a>
    @php($sections = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin.dashboard'],
        ['route' => 'admin.posts.index', 'label' => 'Posts', 'pattern' => 'admin.posts.*'],
        ['route' => 'admin.authors.index', 'label' => 'Authors', 'pattern' => 'admin.authors.*'],
        ['route' => 'admin.payments.edit', 'label' => 'Payments', 'pattern' => 'admin.payments.*'],
        ['route' => 'admin.themes.index', 'label' => 'Themes', 'pattern' => 'admin.themes.*'],
        ['route' => 'admin.database.index', 'label' => 'Database', 'pattern' => 'admin.database.*'],
    ])
    @foreach ($sections as $section)
        @if (Route::has($section['route']))
            <a class="adm-bar__link @if(request()->routeIs($section['pattern'])) is-on @endif"
               href="{{ route($section['route']) }}">{{ $section['label'] }}</a>
        @endif
    @endforeach
    <span class="adm-bar__spacer"></span>
    <a class="adm-bar__site" href="/{{ app()->getLocale() }}">View site &#8599;</a>
    <span class="adm-bar__who">{{ auth()->user()?->email }}</span>
    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="adm-linkbtn" type="submit">Log out</button></form>
</header>
```

Note the `is-on` class must render without a leading space inside the attribute for the test's regex — write it as `class="adm-bar__link{{ request()->routeIs($section['pattern']) ? ' is-on' : '' }}"`.

- [ ] **Step 4: Feed the dashboard**

`DashboardController::index()`:

```php
public function index()
{
    $backups = $this->backups->all();

    return view('admin.dashboard', [
        'postCount' => Post::count(),
        'draftCount' => Post::where('status', 'draft')->count(),
        'authorCount' => Author::count(),
        'authorsWithoutPosts' => Author::doesntHave('posts')->count(),
        'backupCount' => count($backups),
        'latestBackupAt' => $backups->first()['created_at'] ?? null,
        'activeTheme' => app('theme.manager')->manifest(),
        'recentPosts' => Post::with('translations')->latest()->take(5)->get(),
    ]);
}
```

Inject `BackupRepository $backups` via the constructor, mirroring `DatabaseController`. Confirm the shape of a `BackupRepository::all()` entry before using `created_at` — read `app/Services/Database/BackupRepository.php` and use whatever key it actually exposes; if none carries a timestamp, drop the "newest N ago" sub-line rather than inventing a field.

- [ ] **Step 5: Rewrite the dashboard view**

Structure: `@extends('layouts.admin')`, `@include('admin.partials._topbar')`, `<main class="adm-main">`, an `.adm-head` with `<h2>Dashboard</h2>`, then `.adm-tiles` with four `.adm-tile`s (Posts / drafts, Authors / without posts, Backups / newest, Active theme / version), then a `.adm-panel` "Recent posts" whose `.adm-panel__head` holds "All posts" and "New post" buttons and whose body is an `.adm-rows` list of `@include('admin.partials._row')`, then a "Maintenance" panel with Back up database / Change theme / Add author actions. Guard each panel action with `Route::has()` exactly as the current dashboard does.

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=AdminTopBarTest` → PASS
Run: `php artisan test` → all green. `AdminDatabasePageTest::test_dashboard_links_to_the_database_page` asserts the dashboard shows "Database" — the top bar satisfies it.

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/partials/_topbar.blade.php resources/views/admin/dashboard.blade.php app/Http/Controllers/Admin/DashboardController.php tests/Feature/AdminTopBarTest.php
git commit -m "feat: admin top bar and dashboard tiles"
```

---

### Task 3: Shared row partial + Posts index

**Files:**
- Create: `resources/views/admin/partials/_row.blade.php`
- Modify: `resources/views/admin/posts/index.blade.php`
- Test: `tests/Feature/AdminPostCrudTest.php` (existing, must stay green), plus one new test below in `tests/Feature/AdminLayoutTest.php`

**Interfaces:**
- Produces: `_row.blade.php` accepting `$image` (`?string`), `$round` (`bool`, default false), `$title` (`string`), `$url` (`string`), `$sub` (`?string`), `$metas` (`array<string>`), `$pill` (`?array{label:string,kind:string}` where kind ∈ `ok|draft|neutral`), `$actions` (rendered HTML string or a slot include).

- [ ] **Step 1: Write the failing test** (append to `AdminLayoutTest`)

```php
public function test_posts_index_uses_admin_row_markup_and_no_inline_styles(): void
{
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    $post = \App\Models\Post::factory()->create(['status' => 'draft']);
    $post->translations()->create(['locale' => 'en', 'title' => 'Draft one', 'slug' => 'draft-one', 'body' => '<p>x</p>']);

    $content = $this->actingAs($admin)->get('/admin/posts')->assertOk()->getContent();

    $this->assertStringContainsString('adm-row', $content);
    $this->assertStringContainsString('adm-pill--draft', $content);
    // The old index hand-rolled its layout with inline style attributes.
    $this->assertStringNotContainsString('style="display:flex', $content);
    $this->assertStringNotContainsString('/themes/theme_', $content);
}
```

Check `Post::factory()` exists and what it requires before writing this; if there is no factory, create the post via `Post::create([...])` the way `AdminPostCrudTest` does.

- [ ] **Step 2: Run it** — `php artisan test --filter=test_posts_index_uses_admin_row_markup` → FAIL (`adm-row` absent).

- [ ] **Step 3: Write `_row.blade.php`**

```blade
@php
    $round = $round ?? false;
    $sub = $sub ?? null;
    $metas = $metas ?? [];
    $pill = $pill ?? null;
@endphp
<div class="adm-row">
    @if (!empty($image))
        <img class="adm-row__thumb{{ $round ? ' is-round' : '' }}" src="{{ $image }}" alt="">
    @else
        <span class="adm-row__thumb{{ $round ? ' is-round' : '' }}"></span>
    @endif
    <span class="adm-row__main">
        <a href="{{ $url }}">{{ $title }}</a>
        @if ($sub)<span class="adm-row__sub">{{ $sub }}</span>@endif
    </span>
    @if ($pill)<span class="adm-pill adm-pill--{{ $pill['kind'] }}">{{ $pill['label'] }}</span>@endif
    @foreach ($metas as $meta)
        <span class="adm-row__meta">{{ $meta }}</span>
    @endforeach
    <span class="adm-row__acts">{!! $actions !!}</span>
</div>
```

`$actions` is trusted, view-authored markup (never user input) — hence `{!! !!}`.

- [ ] **Step 4: Rewrite `admin/posts/index.blade.php`**

`@extends('layouts.admin')`, top bar, `.adm-main`, `.adm-head` (title + `.adm-head__count` "N total · M drafts" + New post button), one `.adm-panel` whose head holds the filter chips (All / Drafts / Published) and a title filter input, whose body is `.adm-rows` of `@include('admin.partials._row', [...])` per post. Status maps to `['label' => $post->status, 'kind' => $post->status === 'published' ? 'ok' : 'draft']`. Metas: author name (or `—`) and published date (`$post->published_at?->toDateString() ?? '—'`). Actions: Edit link + the existing DELETE form, both as `.adm-btn.adm-btn--sm`.

Filter chips and the title filter are client-side only — a small `@push('scripts')` block that toggles `hidden` on `.adm-row` elements by `data-status` and a case-insensitive title substring. No route or controller change.

- [ ] **Step 5: Run tests** — filtered test PASS, then `php artisan test` green (`AdminPostCrudTest` asserts titles and routes, not markup).

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/partials/_row.blade.php resources/views/admin/posts/index.blade.php tests/Feature/AdminLayoutTest.php
git commit -m "feat: admin posts index on the new row component"
```

---

### Task 4: Authors index and author forms

**Files:**
- Modify: `resources/views/admin/authors/index.blade.php`, `resources/views/admin/authors/create.blade.php`, `resources/views/admin/authors/edit.blade.php`, `resources/views/admin/authors/_form.blade.php`
- Test: `tests/Feature/AdminAuthorCrudTest.php` (existing, must stay green), one new test below

**Interfaces:**
- Consumes: `_row.blade.php` (Task 3) with `round: true`; `layouts.admin` (Task 1).

- [ ] **Step 1: Write the failing test** (append to `AdminLayoutTest`)

```php
public function test_authors_index_uses_round_row_avatars_and_no_theme_css(): void
{
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    \App\Models\Author::create(['name' => 'Ioana P.']);

    $content = $this->actingAs($admin)->get('/admin/authors')->assertOk()->getContent();

    $this->assertStringContainsString('adm-row__thumb is-round', $content);
    $this->assertStringNotContainsString('/themes/theme_', $content);
    $this->assertStringNotContainsString('style="display:flex', $content);
}
```

- [ ] **Step 2: Run it** → FAIL.

- [ ] **Step 3: Rewrite the three author views**

`index`: same shape as Posts — head with count and "New author", one panel of `_row` includes with `round => true`, metas `["{$author->posts_count} posts"]`, actions Edit + the existing DELETE form (keep its `onclick` confirm text verbatim).

`create` / `edit`: `@extends('layouts.admin')`, top bar, `.adm-main`, `.adm-head` with the page title, a `.adm-panel` wrapping the existing form (unchanged `method`, `action`, `@csrf`, `@method('PUT')`, `enctype`), submit as `.adm-btn.adm-btn--primary`.

`_form`: same field names (`name`, `description`, `picture`, `remove_picture`), rewritten as `.adm-field` blocks; error list becomes `.adm-err`; the picture preview `<img>` drops its inline style for a class.

- [ ] **Step 4: Run tests** — filtered PASS, `php artisan test` green.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/authors tests/Feature/AdminLayoutTest.php
git commit -m "feat: admin authors screens on the new design"
```

---

### Task 5: Post editor — layout, locale tabs, isolated themed preview

**Files:**
- Modify: `resources/views/admin/posts/_form.blade.php`, `resources/views/admin/posts/create.blade.php`, `resources/views/admin/posts/edit.blade.php`, `tests/Feature/AdminPostFormTest.php`
- Test: `tests/Feature/AdminPostFormTest.php`

**Interfaces:**
- Consumes: `layouts.admin`, `.adm-editor` CKEditor chrome vars from `admin.css` (Task 1).
- Produces: a `<script type="application/json" id="adm-preview-assets">` block holding `{"css": [...urls], "tokens": {name: value}}`, and per-locale `<iframe class="adm-editor__frame" id="preview_<locale>" hidden>`.

- [ ] **Step 1: Update the three existing tests that pin the old markup**

In `tests/Feature/AdminPostFormTest.php`:

- `test_create_form_loads_article_css_for_wysiwyg_match` → rename to `test_create_form_ships_article_css_to_the_preview_frame_only` and assert (a) the page has no `<link ... css/article.css`, and (b) `css/article.css` appears inside the `adm-preview-assets` JSON block:

```php
public function test_create_form_ships_article_css_to_the_preview_frame_only(): void
{
    $admin = User::factory()->create(['is_admin' => true]);

    $content = $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->getContent();

    // The admin page itself stays theme-free; article.css is token-driven and
    // belongs only inside the isolated preview frame.
    $this->assertDoesNotMatchRegularExpression('/<link[^>]+css\/article\.css/', $content);
    $this->assertMatchesRegularExpression(
        '/id="adm-preview-assets"[^>]*>[^<]*css\\\\?\/article\.css/',
        $content
    );
}
```

- `test_create_form_wraps_each_editor_in_article_paper` → rename to `test_create_form_wraps_each_editor_in_the_admin_editor_surface`, regex `/<div class="adm-editor"[^>]*>\s*<textarea[^>]*id="editor_en"/` (and `_ro`).
- `test_create_form_has_a_live_preview_toggle_per_locale` → keep the `preview-toggle` button assertions; replace the `journal-hero__title` assertion with `assertStringContainsString('<iframe class="adm-editor__frame" id="preview_'.$locale.'"', $content)`.

Add one new test:

```php
public function test_the_preview_frame_is_handed_the_active_theme_css(): void
{
    $admin = User::factory()->create(['is_admin' => true]);

    $content = $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->getContent();

    // Preview must show the real published article, so the frame — and only
    // the frame — receives the active theme's stylesheets and tokens.
    foreach (app('theme.manager')->cssUrls() as $href) {
        $this->assertStringContainsString(str_replace('/', '\/', $href), $content);
    }
    $this->assertDoesNotMatchRegularExpression('/<link[^>]+themes\/theme_/', $content);
}
```

- [ ] **Step 2: Run them** — `php artisan test --filter=AdminPostFormTest` → the four above FAIL, the rest PASS.

- [ ] **Step 3: Rewrite `_form.blade.php`**

Keep verbatim: every field `name`, the `min`/`max` on the date input, the `.date-locked` class, the `unlock_date` checkbox, the slug regenerate checkbox logic, the `authors` dropdown, `reading_time`, the card-image fieldset fields, the CKEditor config block (plugins, toolbar, image, table, `simpleUpload`), the slug preview JS and the date-lock JS.

Change:
- Wrap in `.adm-grid`: left column = locale tab strip + title/slug/subtitle + editor; right column `<aside class="adm-rail">` = "Publishing" panel (status, date, author, reading time), "Card image" panel, and on `edit` only, a `.adm-panel--danger` delete panel.
- Locale tabs: render both locales' field groups, `hidden` on the inactive one, toggled by `.adm-tabs__btn`. Both remain in the DOM so both submit.
- Editor wrapper: `<div class="adm-editor" id="editorPaper_{{ $locale }}">` (id kept — the preview JS reads it).
- Preview target: `<iframe class="adm-editor__frame" id="preview_{{ $locale }}" hidden></iframe>`.
- Drop the `<link ... article.css>` and the inline `<style>.date-locked{…}</style>` (now in `admin.css`). Keep the `ckeditor5.css` link — the editor UI needs it.
- Emit the preview manifest once:

```blade
<script type="application/json" id="adm-preview-assets">@json([
    'css' => array_merge(
        app('theme.manager')->cssUrls(),
        [versioned_asset('css/article.css'), asset('vendor/ckeditor/ckeditor5.css')]
    ),
    'tokens' => app('theme.manager')->tokens(),
])</script>
```

- Rewrite the preview toggle JS to build the frame document:

```js
// Preview renders the article exactly as the public site will: the active
// theme's CSS and tokens are loaded inside an isolated iframe, so the themed
// styles can never touch the admin page around it.
var PREVIEW = JSON.parse(document.getElementById('adm-preview-assets').textContent);

function previewDoc(title, html, image) {
    var vars = Object.keys(PREVIEW.tokens).map(function (k) {
        return '--' + k + ':' + PREVIEW.tokens[k] + ';';
    }).join('');
    var links = PREVIEW.css.map(function (href) {
        return '<link rel="stylesheet" href="' + href + '">';
    }).join('');
    return '<!DOCTYPE html><html><head><meta charset="utf-8">' + links +
        '<style>:root{' + vars + '}body{margin:0}</style></head><body>' +
        '<header class="journal-hero"><h1 class="journal-hero__title"></h1></header>' +
        (image ? '<div class="article-image"><img src="' + image + '" alt=""></div>' : '') +
        '<article><div class="article-paper"><div class="ck-content"></div></div></article>' +
        '</body></html>';
}
```

Then, on toggle: write the document via `frame.srcdoc = previewDoc(...)`, and in the frame's `load` handler set `.journal-hero__title` `textContent` and `.ck-content` `innerHTML` from the live editor (`editor.getData()`), then size the frame to `frame.contentDocument.body.scrollHeight`. Setting text/HTML after load — rather than interpolating it into the srcdoc string — avoids any quoting problem with the article body.

- [ ] **Step 4: Rewrite `create.blade.php` / `edit.blade.php`**

`@extends('layouts.admin')`, top bar, `.adm-main`, `.adm-head` (post title or "New post", plus a "← All posts" link), the form (`method`, `action`, `@csrf`, `@method('PUT')` on edit, `enctype` unchanged) wrapping `@include('admin.posts._form')`, submit as `.adm-btn.adm-btn--primary`.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=AdminPostFormTest` → PASS
Run: `php artisan test` → green. `test_create_form_shows_date_field_between_status_and_card_image` still holds: the rail orders status → date → author → reading time → card image.

- [ ] **Step 6: Manually verify the editor once**

Run the app (`php artisan serve`), open `/admin/posts/create`, confirm: the CKEditor toolbar and typing surface are dark and legible, Preview renders a themed article inside the frame at the right height, and toggling back restores the editor. This is the one step tests cannot cover.

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/posts tests/Feature/AdminPostFormTest.php
git commit -m "feat: admin post editor with rail metadata and isolated themed preview"
```

---

### Task 6: Themes and Database screens

**Files:**
- Modify: `resources/views/admin/themes/index.blade.php`, `resources/views/admin/database/index.blade.php`
- Test: `tests/Feature/AdminThemesTest.php`, `tests/Feature/AdminDatabasePageTest.php` (both existing, must stay green), one new test below

**Interfaces:**
- Consumes: `layouts.admin`, `.adm-theme-grid`, `.adm-table`, `.adm-panel--danger`.

- [ ] **Step 1: Write the failing test** (append to `AdminLayoutTest`)

```php
public function test_themes_page_is_itself_unthemed(): void
{
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);

    $content = $this->actingAs($admin)->get('/admin/themes')->assertOk()->getContent();

    // Screenshots are plain images from each package; no theme stylesheet is
    // loaded to render this page.
    $this->assertDoesNotMatchRegularExpression('/<link[^>]+themes\/theme_[^>]+\.css/', $content);
    $this->assertStringContainsString('adm-theme-grid', $content);
}
```

- [ ] **Step 2: Run it** → FAIL.

- [ ] **Step 3: Rewrite the Themes view**

Same controller data (`$themes`), same POST form per card with `@method('PATCH')` and the hidden `theme` input. `.adm-theme-grid` of `.adm-panel.adm-theme-card`: screenshot `<img class="adm-theme-shot">` when present, title + `.adm-pill--ok` "active" marker, description, and an `.adm-btn--primary` Apply button (`@disabled($t['active'])`). Keep `session('status')` and `@error('theme')` output, rendered as `.adm-note` / `.adm-err`. Titles "Solar System" and "Default (Light)" must still render — `AdminThemesTest` asserts them.

- [ ] **Step 4: Rewrite the Database view**

Keep the backups `<table>` with the `Backup`, `Origin`, `Size` headers in that order — `AdminDatabasePageTest::test_backups_are_listed_in_a_table` asserts both the `<table` tag and the header order. Style it as `.adm-table` inside an `.adm-panel`. Keep the "Back up now" form and every download/delete form and its `onsubmit` confirm text verbatim. Move the `$restoreEnabled && $sourceConfigured` prod → dev block into `.adm-panel.adm-panel--danger`, keeping its confirm dialog and `@error('pull')` output.

- [ ] **Step 5: Run tests** — filtered PASS, `php artisan test` green.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/themes resources/views/admin/database tests/Feature/AdminLayoutTest.php
git commit -m "feat: admin themes and database screens on the new design"
```

---

### Task 7: Theme-independence guarantee + public layout cleanup

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/AdminLayoutTest.php`

**Interfaces:**
- Consumes: every admin view converted in Tasks 1–6.

- [ ] **Step 1: Write the failing test**

```php
public function test_the_active_theme_has_no_effect_on_admin_pages(): void
{
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);
    $setting = \App\Models\SiteSetting::current();

    $render = function (string $theme) use ($admin) {
        \App\Models\SiteSetting::current()->switchTheme($theme);
        app()->forgetInstance('theme.manager');

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        // CSRF tokens are per-request; everything else must be identical.
        return preg_replace('/name="_token" value="[^"]+"/', 'name="_token"', $html);
    };

    $this->assertSame($render('default'), $render('solarsystem'));
}
```

Confirm the installed theme names with `app('theme.manager')->available()` before hard-coding them, and confirm `forgetInstance` actually clears `ThemeManager`'s internal `activeCache` — if it does not (the singleton is rebound, but the test's earlier response may be cached), reset by calling `app()->instance('theme.manager', new \App\Services\ThemeManager)` instead.

- [ ] **Step 2: Run it**

Run: `php artisan test --filter=test_the_active_theme_has_no_effect_on_admin_pages`
Expected: PASS if Tasks 1–6 are complete. **If it fails, it has found a real leak** — locate the differing markup and remove the theme dependency; do not weaken the assertion.

- [ ] **Step 3: Remove the dead branch in the public layout**

In `resources/views/layouts/app.blade.php`, replace

```blade
    @unless(request()->routeIs('admin.*'))
        @include('partials.footer')
        @include('partials.back-to-top')
    @endunless
```

with

```blade
    @include('partials.footer')
    @include('partials.back-to-top')
```

No admin view extends this layout any more, so the condition can never be false.

- [ ] **Step 4: Run the full suite**

Run: `php artisan test`
Expected: green — `FooterTest` and `BackToTopTest` cover the public half; the admin half is covered by `test_login_page_has_no_site_nav_footer_or_back_to_top`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/app.blade.php tests/Feature/AdminLayoutTest.php
git commit -m "test: pin admin rendering as theme-independent; drop dead admin branch from the public layout"
```

---

### Task 8: Documentation

**Files:**
- Modify: `README.md`, `public/themes/AUTHORING.md`

- [ ] **Step 1: Update `README.md`**

- **CSS / asset layering**: state that `layouts/app.blade.php` is the *public* layout, and add a paragraph for `layouts/admin.blade.php` — loads only `public/css/admin.css`, no tokens, no theme assets, no nav/footer; the admin palette is `--adm-*` on `.adm-body`; the post editor's Preview is the sole place the theme appears in admin, inside an isolated iframe.
- **Blog & rich text**, "Admin editor WYSIWYG parity" bullet: rewrite. The editor surface is now admin-styled (`.adm-editor`, CKEditor chrome re-skinned from `admin.css` in `--adm-*` terms); Preview renders the real themed article inside an `<iframe srcdoc>` fed by the `adm-preview-assets` manifest (theme CSS URLs + tokens + `article.css` + `ckeditor5.css`).
- **Project layout**: add `public/css/admin.css` ("admin design system, theme-independent"), `resources/views/layouts/admin.blade.php`, and `resources/views/admin/partials/` (`_topbar`, `_row`); note `admin/` now covers dashboard, login, posts/\*, authors/\*, themes/index, database/index.
- **Theming** section: add one sentence — themes style public pages only; the admin module is out of their scope.

- [ ] **Step 2: Update `public/themes/AUTHORING.md`**

State in the class-vocabulary section that the contract covers public views only: admin screens use a separate `adm-` vocabulary from `public/css/admin.css` and no theme rule reaches them. The single exception is the post editor's Preview frame, which loads the theme's CSS and therefore must render `.journal-hero`, `.article-paper` and `.ck-content` correctly — those stay part of the contract.

- [ ] **Step 3: Verify nothing else went stale**

Run: `git grep -n "admin" README.md public/themes/AUTHORING.md` and read each hit; fix any statement the redesign made false.

- [ ] **Step 4: Full suite + commit**

```bash
php artisan test
git add README.md public/themes/AUTHORING.md
git commit -m "docs: record the theme-independent admin module"
```

---

## Self-Review

- **Spec coverage.** Isolation mechanism → Task 1. Palette/typography → Task 1. Top bar → Task 2. Component set → Tasks 1–4. Screens table: login T1, dashboard T2, posts index T3, authors T4, post form T5, themes + database T6. Editor decision (admin-styled surface, iframe preview) → T5. Spec tests 1–2 → T1, 3 → T7, 4 → T1+T7, 5–6 → T2, 7 → T5, 8–9 → T1. Docs → T8. Public-layout cleanup → T7.
- **Spec correction carried here.** The spec's test 10 said the CKEditor chrome overrides would be *deleted* from `article.css`. They are not: `article.css` is still loaded inside the preview frame and on the public article page, and its `.article-paper` block is already documented as inert where no CKEditor UI exists. Admin gets its own `.adm-editor` block instead, so `tests/Unit/ArticleCssTest.php` stays untouched. The spec is amended to match.
- **Naming consistency.** `.adm-editor` (wrapper, id `editorPaper_<locale>`), `.adm-editor__frame` (iframe, id `preview_<locale>`), `adm-preview-assets` (JSON manifest id), `.adm-bar__link.is-on` (active section) — used identically in every task and test above.
- **Known-fragile assertions** flagged inline where a fact must be re-checked before the code is written: `BackupRepository::all()` entry shape (T2), `Post::factory()` existence (T3), installed theme names and singleton reset (T7).
