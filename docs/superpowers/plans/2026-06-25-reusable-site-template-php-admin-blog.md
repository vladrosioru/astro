# Reusable Site Template (PHP/Laravel) — Admin + Blog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add session-based admin authentication, a protected `/admin` area, and a multilingual blog (models, Trix editor with sanitized HTML, inline image uploads, and public rendering) on top of the foundation.

**Architecture:** Hand-rolled session auth using Laravel's built-in guard (no Breeze/Fortify, to stay Node-free). An `is_admin` flag plus an `EnsureAdmin` middleware guard the `/admin` route group. Blog content is `Post` + `PostTranslation` (one row per locale), edited with the pre-compiled Trix editor and sanitized via HTMLPurifier on save. Inline images upload to a controller that processes them with Intervention Image and stores them through Laravel's filesystem. Public blog routes live under the existing `{locale}` group.

**Tech Stack:** PHP 8.2+, Laravel 11, Blade, Trix (pre-compiled standalone — no Node), `mews/purifier` (HTMLPurifier), `intervention/image` (GD), SQLite in-memory tests, PHPUnit.

This is **Plan 2 of 4**. It depends on **Plan 1 (Foundation)** being complete: `SiteSetting`, the `{locale}` route group + `setlocale` middleware, the `layouts.app` Blade layout, and the token style layer.

## Global Constraints

- PHP **8.2+**; Laravel **11.x**. **No Node.js anywhere** — Trix is added as pre-compiled `.js`/`.css` files in `public/vendor/trix/`.
- All admin routes sit under `/admin` and require an authenticated user with `is_admin = true`.
- Blog post bodies are stored as **HTML sanitized with HTMLPurifier on save** — never raw user HTML.
- A post has **one translation row per locale** (`en`, `ro`); slugs are unique per locale.
- Style isolation holds: any new CSS lives in the structure/skin split and references only `var(--<token>)`.
- SQLite `:memory:` for tests; conventional commits; commit at the end of every task.

---

### Task 1: Admin flag on users + create-admin command

**Files:**
- Create: `database/migrations/2026_06_26_000001_add_is_admin_to_users_table.php`
- Modify: `app/Models/User.php` (cast `is_admin`)
- Create: `app/Console/Commands/CreateAdmin.php`
- Test: `tests/Feature/CreateAdminCommandTest.php`

**Interfaces:**
- Produces:
  - `users.is_admin` boolean column (default `false`).
  - `User->is_admin` cast to `bool`.
  - Artisan command `app:create-admin {email} {password}` → creates (or updates) a user with `is_admin = true`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CreateAdminCommandTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_admin_user(): void
    {
        $this->artisan('app:create-admin', ['email' => 'a@b.com', 'password' => 'secret123'])
            ->assertExitCode(0);

        $user = User::where('email', 'a@b.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=CreateAdminCommandTest`
Expected: FAIL — command `app:create-admin` does not exist.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_26_000001_add_is_admin_to_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
```

- [ ] **Step 4: Cast the attribute on the User model**

Edit `app/Models/User.php` — add `'is_admin' => 'boolean',` to the array returned by the `casts()` method:
```php
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
```

- [ ] **Step 5: Create the command**

Create `app/Console/Commands/CreateAdmin.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email} {password}';

    protected $description = 'Create or promote an admin user';

    public function handle(): int
    {
        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name' => $this->argument('email'),
                'password' => Hash::make($this->argument('password')),
                'is_admin' => true,
            ],
        );

        $this->info("Admin ready: {$user->email}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=CreateAdminCommandTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_26_000001_add_is_admin_to_users_table.php app/Models/User.php app/Console/Commands/CreateAdmin.php tests/Feature/CreateAdminCommandTest.php
git commit -m "feat: add is_admin flag and create-admin command"
```

---

### Task 2: Admin login / logout

**Files:**
- Create: `app/Http/Controllers/Admin/AuthController.php`
- Create: `resources/views/admin/login.blade.php`
- Modify: `routes/web.php` (add admin auth routes — OUTSIDE the locale group)
- Test: `tests/Feature/AdminAuthTest.php`

**Interfaces:**
- Consumes: `User->is_admin` (Task 1).
- Produces:
  - `GET /admin/login` (name `admin.login`), `POST /admin/login`, `POST /admin/logout` (name `admin.logout`).
  - On successful login the user is authenticated via the session guard and redirected to `/admin`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminAuthTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('secret123'),
            'is_admin' => true,
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'secret123'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('secret123'),
            'is_admin' => true,
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_can_log_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AdminAuthTest`
Expected: FAIL — `POST /admin/login` route is undefined (404/MethodNotAllowed).

- [ ] **Step 3: Create the auth controller**

Create `app/Http/Controllers/Admin/AuthController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
```

- [ ] **Step 4: Create the login view**

Create `resources/views/admin/login.blade.php`:
```blade
@extends('layouts.app')

@section('title', 'Admin Login')

@section('content')
    <div class="container">
        <h1>Admin Login</h1>
        @if ($errors->any())
            <p class="muted">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ route('admin.login.attempt') }}">
            @csrf
            <p><label>Email <input type="email" name="email" value="{{ old('email') }}" required></label></p>
            <p><label>Password <input type="password" name="password" required></label></p>
            <p><button type="submit">Log in</button></p>
        </form>
    </div>
@endsection
```

- [ ] **Step 5: Add the routes (outside the locale group)**

Edit `routes/web.php` — add **above** the `Route::prefix('{locale}')` group so `/admin` is never treated as a locale:
```php
use App\Http\Controllers\Admin\AuthController;

Route::get('/admin/login', [AuthController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.attempt');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=AdminAuthTest`
Expected: PASS — all three tests green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/AuthController.php resources/views/admin/login.blade.php routes/web.php tests/Feature/AdminAuthTest.php
git commit -m "feat: add admin login and logout"
```

---

### Task 3: `EnsureAdmin` middleware + protected admin dashboard

**Files:**
- Create: `app/Http/Middleware/EnsureAdmin.php`
- Modify: `bootstrap/app.php` (alias `admin`)
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Create: `resources/views/admin/dashboard.blade.php`
- Modify: `routes/web.php` (admin group)
- Test: `tests/Feature/AdminAccessTest.php`

**Interfaces:**
- Consumes: `User->is_admin` (Task 1); admin auth routes (Task 2).
- Produces:
  - Middleware alias `admin` → requires authenticated `is_admin` user, else redirect to `admin.login` (guest) or `abort(403)` (non-admin).
  - Route group `Route::prefix('admin')->middleware('admin')` with `GET /admin` (name `admin.dashboard`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminAccessTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_sees_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Dashboard');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AdminAccessTest`
Expected: FAIL — `/admin` route undefined.

- [ ] **Step 3: Create the middleware**

Create `app/Http/Middleware/EnsureAdmin.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        abort_unless($request->user()->is_admin, 403);

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the alias**

Edit `bootstrap/app.php` — extend the existing `$middleware->alias([...])` call (added in Foundation Task 4) to include:
```php
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
```

- [ ] **Step 5: Create the dashboard controller and view**

Create `app/Http/Controllers/Admin/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard');
    }
}
```

Create `resources/views/admin/dashboard.blade.php`:
```blade
@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="container">
        <h1>Dashboard</h1>
        <ul>
            <li><a href="{{ route('admin.posts.index') }}">Blog posts</a></li>
        </ul>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit">Log out</button></form>
    </div>
@endsection
```

- [ ] **Step 6: Add the admin group**

Edit `routes/web.php` — add below the auth routes from Task 2:
```php
use App\Http\Controllers\Admin\DashboardController;

Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
});
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=AdminAccessTest`
Expected: PASS.

> Note: the dashboard view links to `admin.posts.index`, defined in Task 5. Until then the link's `route()` call would error if rendered. The test asserts the page loads and shows "Dashboard"; do not render the posts link target until Task 5. If you run the app manually before Task 5, comment out that `<li>` line.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/EnsureAdmin.php bootstrap/app.php app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard.blade.php routes/web.php tests/Feature/AdminAccessTest.php
git commit -m "feat: add admin middleware and protected dashboard"
```

---

### Task 4: Blog models — `Post` + `PostTranslation`

**Files:**
- Create: `database/migrations/2026_06_26_000002_create_posts_table.php`
- Create: `database/migrations/2026_06_26_000003_create_post_translations_table.php`
- Create: `app/Models/Post.php`
- Create: `app/Models/PostTranslation.php`
- Test: `tests/Unit/PostTest.php`

**Interfaces:**
- Produces:
  - `Post`: `status` (`draft`|`published`), `published_at`, `featured_image`; `translations(): HasMany`; `scopePublished()`; `translation(string $locale): ?PostTranslation`.
  - `PostTranslation`: `post_id`, `locale`, `title`, `slug`, `excerpt`, `body` (HTML), `seo_title`, `seo_description`; unique `(post_id, locale)` and `(locale, slug)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/PostTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_has_translations_and_lookup_by_locale(): void
    {
        $post = Post::create(['status' => 'published', 'published_at' => now()]);
        $post->translations()->create([
            'locale' => 'en', 'title' => 'Hello', 'slug' => 'hello', 'body' => '<p>Hi</p>',
        ]);

        $this->assertSame('Hello', $post->translation('en')->title);
        $this->assertNull($post->translation('ro'));
    }

    public function test_published_scope_excludes_drafts(): void
    {
        Post::create(['status' => 'draft']);
        Post::create(['status' => 'published', 'published_at' => now()]);

        $this->assertSame(1, Post::published()->count());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PostTest`
Expected: FAIL — `App\Models\Post` not found.

- [ ] **Step 3: Create the migrations**

Create `database/migrations/2026_06_26_000002_create_posts_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('featured_image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

Create `database/migrations/2026_06_26_000003_create_post_translations_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->string('slug');
            $table->string('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_translations');
    }
};
```

- [ ] **Step 4: Create the models**

Create `app/Models/Post.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $guarded = [];

    protected $casts = ['published_at' => 'datetime'];

    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    public function translation(string $locale): ?PostTranslation
    {
        return $this->translations->firstWhere('locale', $locale)
            ?? $this->translations()->where('locale', $locale)->first();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }
}
```

Create `app/Models/PostTranslation.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model
{
    protected $guarded = [];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=PostTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_26_000002_create_posts_table.php database/migrations/2026_06_26_000003_create_post_translations_table.php app/Models/Post.php app/Models/PostTranslation.php tests/Unit/PostTest.php
git commit -m "feat: add Post and PostTranslation models"
```

---

### Task 5: Admin blog CRUD with Trix editor + HTML sanitization

**Files:**
- Modify: `composer.json` via `composer require mews/purifier`
- Download: `public/vendor/trix/trix.umd.min.js`, `public/vendor/trix/trix.css`
- Create: `app/Http/Controllers/Admin/PostController.php`
- Create: `resources/views/admin/posts/index.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`
- Modify: `routes/web.php` (admin posts resource routes)
- Test: `tests/Feature/AdminPostCrudTest.php`

**Interfaces:**
- Consumes: `Post`/`PostTranslation` (Task 4); `admin` middleware (Task 3).
- Produces:
  - Resourceful routes under `admin.posts.*` (`index`, `create`, `store`, `edit`, `update`, `destroy`).
  - On store/update, `body` for each locale is sanitized with `clean()` (HTMLPurifier) before saving.

- [ ] **Step 1: Install HTMLPurifier and fetch Trix assets**

Run:
```bash
composer require mews/purifier
mkdir -p public/vendor/trix
curl -L https://unpkg.com/trix@2.1.1/dist/trix.umd.min.js -o public/vendor/trix/trix.umd.min.js
curl -L https://unpkg.com/trix@2.1.1/dist/trix.css -o public/vendor/trix/trix.css
```
Expected: both Trix files exist and are non-empty; `mews/purifier` is in `composer.json`.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/AdminPostCrudTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPostCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_create_a_post_and_body_is_sanitized(): void
    {
        $this->actingAs($this->admin())->post('/admin/posts', [
            'status' => 'published',
            'en_title' => 'Hello', 'en_slug' => 'hello',
            'en_body' => '<p>Hi</p><script>alert(1)</script>',
            'ro_title' => 'Salut', 'ro_slug' => 'salut', 'ro_body' => '<p>Buna</p>',
        ])->assertRedirect('/admin/posts');

        $post = Post::first();
        $this->assertNotNull($post);
        $body = $post->translation('en')->body;
        $this->assertStringContainsString('Hi', $body);
        $this->assertStringNotContainsString('<script>', $body);
    }

    public function test_admin_can_delete_a_post(): void
    {
        $post = Post::create(['status' => 'draft']);

        $this->actingAs($this->admin())->delete("/admin/posts/{$post->id}")
            ->assertRedirect('/admin/posts');

        $this->assertSame(0, Post::count());
    }

    public function test_non_admin_cannot_create_a_post(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/admin/posts', [])->assertForbidden();
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --filter=AdminPostCrudTest`
Expected: FAIL — `admin.posts.store` route undefined.

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/PostController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    private array $locales = ['en', 'ro'];

    public function index()
    {
        return view('admin.posts.index', ['posts' => Post::with('translations')->latest()->get()]);
    }

    public function create()
    {
        return view('admin.posts.create');
    }

    public function store(Request $request)
    {
        $post = Post::create($this->postData($request));
        $this->saveTranslations($post, $request);

        return redirect()->route('admin.posts.index');
    }

    public function edit(Post $post)
    {
        return view('admin.posts.edit', ['post' => $post->load('translations')]);
    }

    public function update(Request $request, Post $post)
    {
        $post->update($this->postData($request));
        $this->saveTranslations($post, $request);

        return redirect()->route('admin.posts.index');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('admin.posts.index');
    }

    private function postData(Request $request): array
    {
        return [
            'status' => $request->input('status', 'draft'),
            'published_at' => $request->input('status') === 'published' ? now() : null,
        ];
    }

    private function saveTranslations(Post $post, Request $request): void
    {
        foreach ($this->locales as $locale) {
            $title = $request->input("{$locale}_title");
            if (! $title) {
                continue;
            }
            $post->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $title,
                    'slug' => $request->input("{$locale}_slug"),
                    'excerpt' => $request->input("{$locale}_excerpt"),
                    'body' => clean($request->input("{$locale}_body", '')),
                    'seo_title' => $request->input("{$locale}_seo_title"),
                    'seo_description' => $request->input("{$locale}_seo_description"),
                ],
            );
        }
    }
}
```

- [ ] **Step 5: Create the views**

Create `resources/views/admin/posts/_form.blade.php`:
```blade
@php($t = isset($post) ? fn ($l) => optional($post->translation($l)) : fn ($l) => null)
<link rel="stylesheet" href="{{ asset('vendor/trix/trix.css') }}">
<script src="{{ asset('vendor/trix/trix.umd.min.js') }}"></script>

<p><label>Status
    <select name="status">
        <option value="draft" @selected(isset($post) && $post->status === 'draft')>Draft</option>
        <option value="published" @selected(isset($post) && $post->status === 'published')>Published</option>
    </select>
</label></p>

@foreach (['en', 'ro'] as $locale)
    <fieldset>
        <legend>{{ strtoupper($locale) }}</legend>
        <p><label>Title <input name="{{ $locale }}_title" value="{{ old("{$locale}_title", $t($locale)?->title) }}"></label></p>
        <p><label>Slug <input name="{{ $locale }}_slug" value="{{ old("{$locale}_slug", $t($locale)?->slug) }}"></label></p>
        <p><label>Excerpt <input name="{{ $locale }}_excerpt" value="{{ old("{$locale}_excerpt", $t($locale)?->excerpt) }}"></label></p>
        <input id="{{ $locale }}_body_input" type="hidden" name="{{ $locale }}_body" value="{{ old("{$locale}_body", $t($locale)?->body) }}">
        <trix-editor input="{{ $locale }}_body_input"></trix-editor>
    </fieldset>
@endforeach
```

Create `resources/views/admin/posts/create.blade.php`:
```blade
@extends('layouts.app')
@section('title', 'New Post')
@section('content')
    <div class="container">
        <h1>New Post</h1>
        <form method="POST" action="{{ route('admin.posts.store') }}">
            @csrf
            @include('admin.posts._form')
            <p><button type="submit">Save</button></p>
        </form>
    </div>
@endsection
```

Create `resources/views/admin/posts/edit.blade.php`:
```blade
@extends('layouts.app')
@section('title', 'Edit Post')
@section('content')
    <div class="container">
        <h1>Edit Post</h1>
        <form method="POST" action="{{ route('admin.posts.update', $post) }}">
            @csrf @method('PUT')
            @include('admin.posts._form')
            <p><button type="submit">Update</button></p>
        </form>
    </div>
@endsection
```

Create `resources/views/admin/posts/index.blade.php`:
```blade
@extends('layouts.app')
@section('title', 'Posts')
@section('content')
    <div class="container">
        <h1>Posts</h1>
        <p><a href="{{ route('admin.posts.create') }}">New post</a></p>
        <ul>
            @foreach ($posts as $post)
                <li>
                    <a href="{{ route('admin.posts.edit', $post) }}">{{ $post->translation('en')?->title ?? '(untitled)' }}</a>
                    — {{ $post->status }}
                    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit">Delete</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
```

- [ ] **Step 6: Add the routes**

Edit `routes/web.php` — inside the existing `Route::prefix('admin')->middleware('admin')->group(...)` body, add:
```php
    Route::resource('posts', \App\Http\Controllers\Admin\PostController::class)
        ->except(['show'])
        ->names('admin.posts');
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=AdminPostCrudTest`
Expected: PASS — all three tests green.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock public/vendor/trix app/Http/Controllers/Admin/PostController.php resources/views/admin/posts routes/web.php tests/Feature/AdminPostCrudTest.php
git commit -m "feat: add admin blog CRUD with Trix editor and HTML sanitization"
```

---

### Task 6: Inline image upload with Intervention Image

**Files:**
- Modify: `composer.json` via `composer require intervention/image`
- Create: `app/Models/Media.php`
- Create: `database/migrations/2026_06_26_000004_create_media_table.php`
- Create: `app/Http/Controllers/Admin/AttachmentController.php`
- Modify: `routes/web.php` (attachment upload route)
- Modify: `resources/views/admin/posts/_form.blade.php` (wire Trix attachment upload)
- Test: `tests/Feature/AttachmentUploadTest.php`

**Interfaces:**
- Consumes: `admin` middleware (Task 3); the storage `public` disk.
- Produces:
  - `POST /admin/attachments` (name `admin.attachments.store`) → accepts `file`, processes with Intervention Image (max width 1600px), stores on the `public` disk, records a `Media` row, returns JSON `{ "url": "<public url>" }`.
  - `Media`: `path`, `url`, `width`, `height`, `alt` (nullable).

- [ ] **Step 1: Install Intervention Image and create the public disk link**

Run:
```bash
composer require intervention/image
php artisan storage:link
```
Expected: `intervention/image` in `composer.json`; `public/storage` symlink created (or a note if symlinks are unavailable — see Deployment plan).

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/AttachmentUploadTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_an_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/attachments', [
            'file' => UploadedFile::fake()->image('photo.jpg', 2000, 1000),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertSame(1, Media::count());
        Storage::disk('public')->assertExists(Media::first()->path);
    }

    public function test_guest_cannot_upload(): void
    {
        $this->post('/admin/attachments', [])->assertRedirect('/admin/login');
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --filter=AttachmentUploadTest`
Expected: FAIL — route `admin.attachments.store` undefined.

- [ ] **Step 4: Create the migration and Media model**

Create `database/migrations/2026_06_26_000004_create_media_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('url');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
```

Create `app/Models/Media.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $guarded = [];
}
```

- [ ] **Step 5: Create the attachment controller**

Create `app/Http/Controllers/Admin/AttachmentController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class AttachmentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['file' => ['required', 'image', 'max:8192']]);

        $manager = ImageManager::gd();
        $image = $manager->read($request->file('file')->getRealPath());
        $image->scaleDown(width: 1600);

        $path = 'media/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, (string) $image->toJpeg(quality: 82));

        $media = Media::create([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'width' => $image->width(),
            'height' => $image->height(),
        ]);

        return response()->json(['url' => $media->url]);
    }
}
```

- [ ] **Step 6: Add the route and wire Trix uploads**

Edit `routes/web.php` — inside the `admin` group, add:
```php
    Route::post('attachments', [\App\Http\Controllers\Admin\AttachmentController::class, 'store'])
        ->name('admin.attachments.store');
```

Edit `resources/views/admin/posts/_form.blade.php` — append this script at the bottom so Trix posts attachments to our endpoint:
```blade
<script>
document.addEventListener('trix-attachment-add', function (event) {
    const attachment = event.attachment;
    if (!attachment.file) return;
    const body = new FormData();
    body.append('file', attachment.file);
    body.append('_token', '{{ csrf_token() }}');
    fetch('{{ route('admin.attachments.store') }}', { method: 'POST', body })
        .then(r => r.json())
        .then(data => attachment.setAttributes({ url: data.url, href: data.url }));
});
</script>
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=AttachmentUploadTest`
Expected: PASS — both tests green.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock app/Models/Media.php database/migrations/2026_06_26_000004_create_media_table.php app/Http/Controllers/Admin/AttachmentController.php routes/web.php resources/views/admin/posts/_form.blade.php tests/Feature/AttachmentUploadTest.php
git commit -m "feat: add inline image upload with Intervention Image"
```

---

### Task 7: Public blog rendering

**Files:**
- Create: `app/Http/Controllers/BlogController.php`
- Create: `resources/views/blog/index.blade.php`, `resources/views/blog/show.blade.php`
- Modify: `routes/web.php` (blog routes inside the `{locale}` group)
- Test: `tests/Feature/PublicBlogTest.php`

**Interfaces:**
- Consumes: `Post::published()` + `Post->translation()` (Task 4); `{locale}` group + `SiteSetting::sectionVisible('blog')` (Foundation).
- Produces:
  - `GET /{locale}/blog` (name `blog.index`) — lists published posts that have a translation in the active locale.
  - `GET /{locale}/blog/{slug}` (name `blog.show`) — shows one published post by its per-locale slug; 404 if missing/draft/untranslated.
  - Both 404 when the blog section is disabled.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PublicBlogTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPost(string $slug = 'hello'): Post
    {
        $post = Post::create(['status' => 'published', 'published_at' => now()]);
        $post->translations()->create([
            'locale' => 'en', 'title' => 'Hello', 'slug' => $slug, 'body' => '<p>Hi there</p>',
        ]);

        return $post;
    }

    public function test_index_lists_published_posts(): void
    {
        $this->publishedPost();
        $this->get('/en/blog')->assertOk()->assertSee('Hello');
    }

    public function test_show_renders_published_post_body(): void
    {
        $this->publishedPost('my-post');
        $this->get('/en/blog/my-post')->assertOk()->assertSee('Hi there', false);
    }

    public function test_draft_post_is_not_visible(): void
    {
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Draft', 'slug' => 'draft', 'body' => 'x']);

        $this->get('/en/blog/draft')->assertNotFound();
    }

    public function test_blog_404s_when_section_disabled(): void
    {
        $this->publishedPost();
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['blog' => false] + $setting->sections]);

        $this->get('/en/blog')->assertNotFound();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PublicBlogTest`
Expected: FAIL — `/en/blog` route undefined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/BlogController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\SiteSetting;

class BlogController extends Controller
{
    public function index()
    {
        abort_unless(SiteSetting::current()->sectionVisible('blog'), 404);

        $locale = app()->getLocale();
        $posts = Post::published()
            ->with('translations')
            ->latest('published_at')
            ->get()
            ->filter(fn (Post $p) => $p->translation($locale) !== null)
            ->values();

        return view('blog.index', ['posts' => $posts, 'locale' => $locale]);
    }

    public function show(string $locale, string $slug)
    {
        abort_unless(SiteSetting::current()->sectionVisible('blog'), 404);

        $translation = PostTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('post', fn ($q) => $q->published())
            ->first();

        abort_if($translation === null, 404);

        return view('blog.show', ['t' => $translation]);
    }
}
```

- [ ] **Step 4: Create the views**

Create `resources/views/blog/index.blade.php`:
```blade
@extends('layouts.app')
@section('title', 'Blog')
@section('content')
    <div class="container">
        <h1>Blog</h1>
        @foreach ($posts as $post)
            @php($t = $post->translation($locale))
            <article class="card">
                <h2><a href="/{{ $locale }}/blog/{{ $t->slug }}">{{ $t->title }}</a></h2>
                <p class="muted">{{ $t->excerpt }}</p>
            </article>
        @endforeach
    </div>
@endsection
```

Create `resources/views/blog/show.blade.php`:
```blade
@extends('layouts.app')
@section('title', $t->seo_title ?? $t->title)
@section('content')
    <div class="container">
        <article>
            <h1>{{ $t->title }}</h1>
            {!! $t->body !!}
        </article>
    </div>
@endsection
```

- [ ] **Step 5: Add the routes**

Edit `routes/web.php` — inside the existing `{locale}` group, add:
```php
        Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=PublicBlogTest`
Expected: PASS — all four tests green.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS — every test across Plans 1 and 2 green.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/BlogController.php resources/views/blog routes/web.php tests/Feature/PublicBlogTest.php
git commit -m "feat: add public blog index and single-post rendering"
```

---

## Self-Review

**Spec coverage (Plan 2 slice):**
- §5.2 blog (Post/PostTranslation per locale; TipTap→ here Trix→sanitized HTML; inline image upload via Intervention Image + storage; public rendering) — Tasks 4, 5, 6, 7. ✔
- §5.4 auth/admin (email+password session auth, DB sessions, `/admin/**` guarded, role check) — Tasks 1, 2, 3. ✔
- §6 data model `Media` — Task 6; `Post`/`PostTranslation` — Task 4. ✔
- Constraint: HTML sanitized on save (HTMLPurifier) — Task 5. ✔
- Constraint: Node-free (Trix pre-compiled, no Vite) — Task 5. ✔

**Placeholder scan:** Every code step shows complete code; commands include expected output. The one forward-reference (dashboard → `admin.posts.index`) is explicitly flagged in Task 3 Step 7. ✔

**Type/name consistency:** `is_admin`, middleware aliases `setlocale`/`admin`, route names `admin.login`/`admin.login.attempt`/`admin.logout`/`admin.dashboard`/`admin.posts.*`/`admin.attachments.store`/`blog.index`/`blog.show`, model methods `Post::published()`/`Post->translation()`/`Post->translations()`, and the `clean()` helper from `mews/purifier` are used identically across tasks. ✔

---

## Execution Handoff

Covered: a working admin area and multilingual blog. The next plan (**Payments**) adds the `PaymentProvider` interface, a Stripe adapter, webhooks, and admin payment settings, consuming the `admin` middleware and `SiteSetting` established here.
