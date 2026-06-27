# Theme Packages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn each theme into a self-contained `public/themes/theme_<name>/` package (tokens, CSS, JS, fonts, Blade) described by one `theme.json` that is both the app's loader manifest and a portable authoring map, selectable from an admin picker.

**Architecture:** A `ThemeManager` singleton reads `SiteSetting.theme`, loads that folder's `theme.json`, merges tokens (`config defaults ← theme.json ← SiteSetting.branding`), exposes ordered CSS/JS asset URLs, and registers the theme's `views/` under a `theme::` namespace. The master layout and `partials/tokens.blade.php` stop hardcoding assets and read everything from the manager. The current Solar System look is migrated verbatim into `theme_solarsystem`; a light `theme_default` is added.

**Tech Stack:** Laravel 11, PHP 8.2+, plain static CSS/JS under `public/` (no Node/Vite), PHPUnit on in-memory SQLite, Blade.

## Global Constraints

- **Node-free:** no `package.json`/Vite/Tailwind/npm; all CSS/JS/fonts are static files under `public/`. (verbatim from spec)
- **No raw literals in markup/CSS:** every color/font reference is `var(--token)`; token names live in `config/tokens.php`. (verbatim)
- **cPanel/Apache target:** server-side files inside a public theme folder must be blocked via `.htaccess`. (verbatim)
- **No new front-end runtime deps or JS libraries.** (verbatim)
- **Default active theme is `solarsystem`** so the shipped/test render matches today's site; `theme_default` is the light base.
- **Token merge order is always:** `config('tokens.defaults')` ← theme.json token values ← `SiteSetting.branding`. (verbatim from spec §2)
- Commit message trailer on every commit: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

---

## File Structure

**Created:**
- `public/themes/theme.schema.json` — JSON Schema (descriptive contract for `theme.json`).
- `public/themes/theme_solarsystem/` — `theme.json`, `.htaccess`, `css/`, `js/`, `fonts/`, `views/`, `screenshot.png` (optional).
- `public/themes/theme_default/` — `theme.json`, `.htaccess`, `css/{structure,skin}.css`, `views/hero.blade.php`.
- `app/Services/ThemeManager.php` — the single brain.
- `app/Providers/ThemeServiceProvider.php` — binds the manager, registers `theme::`, shares manifest.
- `app/Http/Controllers/Admin/ThemeController.php` — admin picker.
- `resources/views/admin/themes/index.blade.php` — picker UI.
- DB migration `..._add_theme_to_site_settings.php`.
- Tests: `tests/Unit/ThemeManagerTest.php`, `tests/Unit/ThemeJsonContractTest.php`, `tests/Feature/AdminThemesTest.php`, `tests/Unit/ThemeDefaultTest.php`.

**Modified:**
- `app/Models/SiteSetting.php` — add `theme` to defaults.
- `resources/views/partials/tokens.blade.php` — source tokens from `ThemeManager`.
- `resources/views/layouts/app.blade.php` — loop manifest CSS/JS, `@includeIf('theme::cosmos')`.
- `resources/views/pages/home.blade.php` — `@includeIf('theme::hero')`.
- `app/Console/Commands/ApplyTheme.php` — set the `theme` pointer.
- `bootstrap/providers.php` — register `ThemeServiceProvider`.
- `routes/web.php` — admin themes routes.
- `resources/views/admin/dashboard.blade.php` — link to Themes.
- Tests updated to new asset locations / active-theme values: `StyleTokensTest`, `ThemeTokensTest`, `HeroSolarsystemCssTest`, `FontsCssTest`, `SolarsystemJsTest`, `SkinCssTest`, `ApplyThemeCommandTest`.

**Deleted (Task 6 / Task 10):**
- `public/css/{structure,skin,cosmos,hero-solarsystem,fonts}.css`, `public/js/solarsystem.js`, the moved `public/fonts/*.woff2`, `resources/views/partials/{hero,cosmos}.blade.php`, `config/themes.php`, `docs/theme-style-map.json`.

---

### Task 1: `theme` pointer on SiteSetting

**Files:**
- Create: `database/migrations/2026_06_27_000000_add_theme_to_site_settings.php`
- Modify: `app/Models/SiteSetting.php:25-35`
- Test: `tests/Unit/SiteSettingTest.php`

**Interfaces:**
- Produces: `SiteSetting::current()->theme` (string, default `'solarsystem'`).

- [ ] **Step 1: Write the failing test** — append to `tests/Unit/SiteSettingTest.php`:

```php
public function test_current_has_default_theme_pointer(): void
{
    $this->assertSame('solarsystem', \App\Models\SiteSetting::current()->theme);
}
```

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test --filter=test_current_has_default_theme_pointer`
Expected: FAIL (unknown column / null `theme`).

- [ ] **Step 3: Create the migration** `database/migrations/2026_06_27_000000_add_theme_to_site_settings.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('theme')->default('solarsystem')->after('branding');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
```

- [ ] **Step 4: Add `theme` to the model defaults** — in `app/Models/SiteSetting.php`, inside `defaults()` add the key (after `'branding' => []`,):

```php
            'theme'    => 'solarsystem',
```

- [ ] **Step 5: Run the test, verify it passes**

Run: `php artisan test --filter=SiteSettingTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/SiteSetting.php tests/Unit/SiteSettingTest.php
git commit -m "feat: add theme pointer column to site settings"
```

---

### Task 2: `theme_solarsystem` package + schema + structural contract test

Copy the current assets into the package (originals stay until Task 6) and author the manifest. **No app code reads it yet** — this task is purely additive.

**Files:**
- Create: `public/themes/theme.schema.json`
- Create: `public/themes/theme_solarsystem/theme.json`
- Create: `public/themes/theme_solarsystem/.htaccess`
- Create (copy): `public/themes/theme_solarsystem/css/{structure,skin,cosmos,fonts}.css`, `css/hero.css` (from `public/css/hero-solarsystem.css`)
- Create (copy): `public/themes/theme_solarsystem/js/solarsystem.js`
- Create (copy): `public/themes/theme_solarsystem/fonts/*.woff2` (the Jost / Cormorant Garamond / Cinzel files)
- Create (copy): `public/themes/theme_solarsystem/views/hero.blade.php`, `views/cosmos.blade.php` (from `resources/views/partials/`)
- Test: `tests/Unit/ThemeJsonContractTest.php`

**Interfaces:**
- Produces: a valid theme package at `public/themes/theme_solarsystem/` whose `theme.json` shape is the contract consumed by `ThemeManager` (Task 3).

- [ ] **Step 1: Copy the asset files** (Git Bash):

```bash
mkdir -p public/themes/theme_solarsystem/{css,js,fonts,views}
cp public/css/structure.css public/themes/theme_solarsystem/css/structure.css
cp public/css/skin.css      public/themes/theme_solarsystem/css/skin.css
cp public/css/cosmos.css    public/themes/theme_solarsystem/css/cosmos.css
cp public/css/fonts.css     public/themes/theme_solarsystem/css/fonts.css
cp public/css/hero-solarsystem.css public/themes/theme_solarsystem/css/hero.css
cp public/js/solarsystem.js public/themes/theme_solarsystem/js/solarsystem.js
cp resources/views/partials/hero.blade.php   public/themes/theme_solarsystem/views/hero.blade.php
cp resources/views/partials/cosmos.blade.php public/themes/theme_solarsystem/views/cosmos.blade.php
cp public/fonts/jost-300.woff2 public/fonts/jost-400.woff2 public/fonts/jost-500.woff2 public/fonts/jost-600.woff2 public/themes/theme_solarsystem/fonts/
cp public/fonts/cormorant-garamond-400.woff2 public/fonts/cormorant-garamond-500.woff2 public/fonts/cormorant-garamond-400-italic.woff2 public/themes/theme_solarsystem/fonts/
cp public/fonts/cinzel-400.woff2 public/fonts/cinzel-600.woff2 public/fonts/cinzel-700.woff2 public/themes/theme_solarsystem/fonts/
```

> Note: `fonts.css` references font files by root-relative URL today (`/fonts/...`). The package copy must reference its own folder. After copying, edit `public/themes/theme_solarsystem/css/fonts.css` so every `url(/fonts/<file>)` becomes `url(/themes/theme_solarsystem/fonts/<file>)`. (EB Garamond is mystik-only; it may be dropped from this theme's fonts.css — keep only Jost/Cormorant/Cinzel.)

- [ ] **Step 2: Write `.htaccess`** at `public/themes/theme_solarsystem/.htaccess`:

```apache
# Block direct web access to server-side theme files; serve assets normally.
<FilesMatch "\.(blade\.php|json)$">
    Require all denied
</FilesMatch>
```

- [ ] **Step 3: Write the schema** `public/themes/theme.schema.json`:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "Site Template theme.json",
  "type": "object",
  "required": ["name", "title", "tokens", "assets"],
  "properties": {
    "name": { "type": "string", "pattern": "^[a-z0-9_-]+$" },
    "title": { "type": "string" },
    "description": { "type": "string" },
    "version": { "type": "string" },
    "screenshot": { "type": "string" },
    "page_home_class": { "type": "string" },
    "tokens": {
      "type": "object",
      "additionalProperties": {
        "type": "object",
        "required": ["type", "role", "value"],
        "properties": {
          "type": { "enum": ["color", "font-stack", "length", "shadow"] },
          "role": { "type": "string" },
          "value": { "type": "string" }
        }
      }
    },
    "fonts": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["family", "files"],
        "properties": {
          "family": { "type": "string" },
          "weights": { "type": "array", "items": { "type": "number" } },
          "style": { "type": "string" },
          "files": { "type": "array", "items": { "type": "string" } },
          "used_for": { "type": "string" }
        }
      }
    },
    "assets": {
      "type": "object",
      "required": ["css"],
      "properties": {
        "css": { "type": "array", "items": { "type": "string" } },
        "js": {
          "type": "array",
          "items": {
            "type": "object",
            "required": ["src"],
            "properties": {
              "src": { "type": "string" },
              "defer": { "type": "boolean" },
              "async": { "type": "boolean" }
            }
          }
        }
      }
    },
    "views": {
      "type": "object",
      "properties": {
        "namespace": { "type": "string" },
        "hero": { "type": "string" },
        "cosmos": { "type": "string" },
        "nav": { "type": "string" }
      }
    }
  }
}
```

- [ ] **Step 4: Author `public/themes/theme_solarsystem/theme.json`** (all 16 tokens; values from the current `config/themes.php` solarsystem set, inherited ones from `config/tokens.php`):

```json
{
  "$schema": "../theme.schema.json",
  "name": "solarsystem",
  "title": "Solar System",
  "description": "Dark celestial theme with an animated orbiting-planets hero and shared cosmos backdrop.",
  "version": "1.0.0",
  "screenshot": "screenshot.png",
  "page_home_class": "page-home",
  "tokens": {
    "color-primary": { "type": "color", "role": "Links, primary button, eyebrow, scroll cue", "value": "#9dc1e6" },
    "color-accent":  { "type": "color", "role": "Hover/active highlight", "value": "#dcebfb" },
    "color-bg":      { "type": "color", "role": "Page background; stage base; nav source", "value": "#05060c" },
    "color-bg-alt":  { "type": "color", "role": "Cards/panels; nav border", "value": "#0b1426" },
    "color-fg":      { "type": "color", "role": "Body text; card border tint; stage lede", "value": "#aab6c8" },
    "color-heading": { "type": "color", "role": "Headings; stage title", "value": "#f2f7fd" },
    "color-muted":   { "type": "color", "role": "Muted text; idle nav links", "value": "#9aa6b8" },
    "font-base":     { "type": "font-stack", "role": "Body/UI text", "value": "'Jost', system-ui, sans-serif" },
    "font-heading":  { "type": "font-stack", "role": "Headings", "value": "'Cormorant Garamond', serif" },
    "font-display":  { "type": "font-stack", "role": "Button/CTA chrome", "value": "'Cinzel', serif" },
    "space-unit":    { "type": "length", "role": "Spacing scale base", "value": "0.25rem" },
    "radius":        { "type": "length", "role": "Corner radius", "value": "0.5rem" },
    "shadow":        { "type": "shadow", "role": "Card elevation", "value": "0 1px 3px rgba(0,0,0,0.1)" },
    "container-width": { "type": "length", "role": "Max content width", "value": "64rem" },
    "nav-height":    { "type": "length", "role": "Nav min height", "value": "4.5rem" },
    "hero-overlay":  { "type": "color", "role": "Legacy hero scrim", "value": "rgba(0,0,0,0.45)" }
  },
  "fonts": [
    { "family": "Jost", "weights": [300,400,500,600], "style": "normal",
      "files": ["fonts/jost-300.woff2","fonts/jost-400.woff2","fonts/jost-500.woff2","fonts/jost-600.woff2"], "used_for": "font-base" },
    { "family": "Cormorant Garamond", "weights": [400,500], "style": "normal+italic(400)",
      "files": ["fonts/cormorant-garamond-400.woff2","fonts/cormorant-garamond-500.woff2","fonts/cormorant-garamond-400-italic.woff2"], "used_for": "font-heading" },
    { "family": "Cinzel", "weights": [400,600,700], "style": "normal",
      "files": ["fonts/cinzel-400.woff2","fonts/cinzel-600.woff2","fonts/cinzel-700.woff2"], "used_for": "font-display" }
  ],
  "assets": {
    "css": ["css/fonts.css", "css/structure.css", "css/cosmos.css", "css/skin.css", "css/hero.css"],
    "js": [{ "src": "js/solarsystem.js", "defer": true }]
  },
  "views": { "namespace": "theme", "hero": "hero", "cosmos": "cosmos" }
}
```

- [ ] **Step 5: Write the contract test** `tests/Unit/ThemeJsonContractTest.php`:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class ThemeJsonContractTest extends TestCase
{
    public function test_every_shipped_theme_json_is_structurally_valid(): void
    {
        $configTokenNames = array_keys(config('tokens.defaults'));

        foreach (glob(public_path('themes/theme_*'), GLOB_ONLYDIR) as $dir) {
            $manifestPath = $dir . '/theme.json';
            $this->assertFileExists($manifestPath, "Missing theme.json in $dir");

            $m = json_decode(file_get_contents($manifestPath), true);
            $this->assertIsArray($m, "Invalid JSON in $manifestPath");

            foreach (['name', 'title', 'tokens', 'assets'] as $key) {
                $this->assertArrayHasKey($key, $m, "$key missing in $manifestPath");
            }

            // Every declared token has type/role/value and is a known token name.
            foreach ($m['tokens'] as $name => $def) {
                $this->assertContains($name, $configTokenNames, "Unknown token '$name' in $manifestPath");
                foreach (['type', 'role', 'value'] as $field) {
                    $this->assertArrayHasKey($field, $def, "Token '$name' missing '$field' in $manifestPath");
                }
            }

            // Every referenced CSS asset exists on disk.
            foreach ($m['assets']['css'] as $rel) {
                $this->assertFileExists("$dir/$rel", "Missing CSS asset $rel in $dir");
            }
            foreach ($m['assets']['js'] ?? [] as $js) {
                $this->assertFileExists("$dir/{$js['src']}", "Missing JS asset {$js['src']} in $dir");
            }

            // Declared view partials exist.
            foreach (($m['views'] ?? []) as $slot => $view) {
                if (in_array($slot, ['hero', 'cosmos', 'nav'], true)) {
                    $this->assertFileExists("$dir/views/$view.blade.php", "Missing view $view in $dir");
                }
            }
        }
    }
}
```

- [ ] **Step 6: Run it, verify it passes**

Run: `php artisan test --filter=ThemeJsonContractTest`
Expected: PASS (only `theme_solarsystem` exists so far).

- [ ] **Step 7: Commit**

```bash
git add public/themes tests/Unit/ThemeJsonContractTest.php
git commit -m "feat: add theme_solarsystem package, theme.json schema and contract test"
```

---

### Task 3: `ThemeManager` service

Pure logic over the package from Task 2 and the pointer from Task 1. Nothing in the app uses it yet.

**Files:**
- Create: `app/Services/ThemeManager.php`
- Test: `tests/Unit/ThemeManagerTest.php`

**Interfaces:**
- Consumes: `SiteSetting::current()->theme`, `config('tokens.defaults')`, the `theme_solarsystem` package.
- Produces:
  - `active(): string`
  - `manifest(): array`
  - `tokens(): array` (name => value, fully merged)
  - `cssUrls(): array<string>` (absolute `/themes/...` URLs in order)
  - `jsAssets(): array<array{url:string,defer:bool,async:bool}>`
  - `viewsPath(): ?string` (filesystem path to the theme `views/` dir, or null)
  - `available(): array<array{name,title,description,screenshot,active}>`

- [ ] **Step 1: Write the failing test** `tests/Unit/ThemeManagerTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_defaults_to_solarsystem(): void
    {
        $this->assertSame('solarsystem', (new ThemeManager)->active());
    }

    public function test_tokens_merge_defaults_then_theme_then_branding(): void
    {
        $tokens = (new ThemeManager)->tokens();
        // theme overrides default
        $this->assertSame('#9dc1e6', $tokens['color-primary']);
        $this->assertSame('4.5rem', $tokens['nav-height']);
        // default survives where theme is silent on an inherited value
        $this->assertSame('64rem', $tokens['container-width']);

        // branding overrides theme
        SiteSetting::current()->update(['branding' => ['color-primary' => '#ff0000']]);
        $this->assertSame('#ff0000', (new ThemeManager)->tokens()['color-primary']);
    }

    public function test_css_urls_are_ordered_theme_urls(): void
    {
        $urls = (new ThemeManager)->cssUrls();
        $this->assertSame('http://localhost/themes/theme_solarsystem/css/fonts.css', $urls[0]);
        $this->assertStringEndsWith('/themes/theme_solarsystem/css/hero.css', end($urls));
    }

    public function test_missing_theme_folder_falls_back_to_default_pointer(): void
    {
        SiteSetting::current()->update(['theme' => 'does_not_exist']);
        // falls back to config('theme.fallback') = 'default' when the folder is absent
        $this->assertSame('default', (new ThemeManager)->active());
    }

    public function test_available_lists_solarsystem_and_flags_active(): void
    {
        $names = array_column((new ThemeManager)->available(), 'active', 'name');
        $this->assertTrue($names['solarsystem']);
    }
}
```

> The fallback test expects a `theme_default` folder to exist by the time the whole suite runs (Task 7). To keep this task green in isolation, the implementation returns the configured fallback name string even if its folder is not yet present (it only *renders* a fallback when assets are loaded). The assertion checks the returned string, not a render.

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test --filter=ThemeManagerTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Add config** `config/theme.php`:

```php
<?php

return [
    // Used when the active theme's folder is missing/invalid.
    'fallback' => 'default',
    'path'     => 'themes', // relative to public/
];
```

- [ ] **Step 4: Implement** `app/Services/ThemeManager.php`:

```php
<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;

class ThemeManager
{
    private ?array $manifestCache = null;
    private ?string $activeCache = null;

    public function active(): string
    {
        if ($this->activeCache !== null) {
            return $this->activeCache;
        }

        $name = SiteSetting::current()->theme ?: config('theme.fallback');

        if (! is_dir($this->dir($name))) {
            $fallback = config('theme.fallback');
            if ($name !== $fallback) {
                Log::warning("Theme folder missing for '{$name}', falling back to '{$fallback}'.");
            }
            $name = $fallback;
        }

        return $this->activeCache = $name;
    }

    public function manifest(): array
    {
        if ($this->manifestCache !== null) {
            return $this->manifestCache;
        }

        $path = $this->dir($this->active()) . '/theme.json';
        $data = is_file($path) ? json_decode(file_get_contents($path), true) : null;

        if (! is_array($data)) {
            Log::warning("Invalid or missing theme.json for '{$this->active()}'.");
            $data = ['tokens' => [], 'assets' => ['css' => [], 'js' => []], 'views' => []];
        }

        return $this->manifestCache = $data;
    }

    /** name => value, merged defaults <- theme.json <- branding. */
    public function tokens(): array
    {
        $defaults = config('tokens.defaults');

        $themeValues = [];
        foreach ($this->manifest()['tokens'] ?? [] as $name => $def) {
            $themeValues[$name] = $def['value'] ?? null;
        }

        $branding = SiteSetting::current()->branding ?? [];

        return array_merge($defaults, array_filter($themeValues, fn ($v) => $v !== null), $branding);
    }

    /** @return array<string> ordered absolute CSS URLs */
    public function cssUrls(): array
    {
        return array_map(
            fn ($rel) => asset($this->rel($rel)),
            $this->manifest()['assets']['css'] ?? []
        );
    }

    /** @return array<array{url:string,defer:bool,async:bool}> */
    public function jsAssets(): array
    {
        return array_map(fn ($js) => [
            'url'   => asset($this->rel($js['src'])),
            'defer' => (bool) ($js['defer'] ?? false),
            'async' => (bool) ($js['async'] ?? false),
        ], $this->manifest()['assets']['js'] ?? []);
    }

    public function viewsPath(): ?string
    {
        $path = $this->dir($this->active()) . '/views';

        return is_dir($path) ? $path : null;
    }

    /** @return array<int,array{name:string,title:string,description:string,screenshot:?string,active:bool}> */
    public function available(): array
    {
        $active = $this->active();
        $out = [];

        foreach (glob(public_path(config('theme.path') . '/theme_*'), GLOB_ONLYDIR) as $dir) {
            $m = json_decode(@file_get_contents($dir . '/theme.json'), true) ?: [];
            $name = $m['name'] ?? basename($dir);
            $name = preg_replace('/^theme_/', '', basename($dir));
            $out[] = [
                'name'        => $name,
                'title'       => $m['title'] ?? ucfirst($name),
                'description' => $m['description'] ?? '',
                'screenshot'  => isset($m['screenshot'])
                    ? asset(config('theme.path') . "/theme_{$name}/" . $m['screenshot'])
                    : null,
                'active'      => $name === $active,
            ];
        }

        return $out;
    }

    private function dir(string $name): string
    {
        return public_path(config('theme.path') . '/theme_' . $name);
    }

    private function rel(string $assetRelativeToTheme): string
    {
        return config('theme.path') . '/theme_' . $this->active() . '/' . ltrim($assetRelativeToTheme, '/');
    }
}
```

- [ ] **Step 5: Run the test, verify it passes**

Run: `php artisan test --filter=ThemeManagerTest`
Expected: PASS (the missing-folder test returns `'default'` string without needing the folder).

- [ ] **Step 6: Commit**

```bash
git add app/Services/ThemeManager.php config/theme.php tests/Unit/ThemeManagerTest.php
git commit -m "feat: ThemeManager resolves active theme, merges tokens, lists packages"
```

---

### Task 4: `ThemeServiceProvider` registers the `theme::` namespace

Additive: registers the namespace and shares the manager so `theme::hero` resolves. Home/layout still use the old `partials.*` paths, so the live render is unchanged.

**Files:**
- Create: `app/Providers/ThemeServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Unit/ThemeManagerTest.php` (add a render test)

**Interfaces:**
- Consumes: `ThemeManager` (Task 3), `theme_solarsystem/views/*` (Task 2).
- Produces: `theme::hero` / `theme::cosmos` resolvable views; `app('theme.manager')` singleton; `$theme` (manifest) and `$themeManager` shared to all views.

- [ ] **Step 1: Write the failing test** — add to `tests/Unit/ThemeManagerTest.php`:

```php
public function test_theme_namespace_resolves_hero_partial(): void
{
    $this->assertTrue(view()->exists('theme::hero'));
    $this->assertTrue(view()->exists('theme::cosmos'));
}
```

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test --filter=test_theme_namespace_resolves_hero_partial`
Expected: FAIL (`theme::hero` not found).

- [ ] **Step 3: Implement the provider** `app/Providers/ThemeServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Services\ThemeManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('theme.manager', fn () => new ThemeManager);
    }

    public function boot(): void
    {
        $manager = $this->app->make('theme.manager');

        if ($path = $manager->viewsPath()) {
            View::addNamespace('theme', $path);
        }

        View::share('themeManager', $manager);
        View::share('theme', $manager->manifest());
    }
}
```

- [ ] **Step 4: Register it** — in `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ThemeServiceProvider::class,
];
```

- [ ] **Step 5: Run the test, verify it passes**

Run: `php artisan test --filter=ThemeManagerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Providers/ThemeServiceProvider.php bootstrap/providers.php tests/Unit/ThemeManagerTest.php
git commit -m "feat: register theme:: view namespace via ThemeServiceProvider"
```

---

### Task 5: Flip the runtime to the manifest (the atomic switch)

Now the layout, tokens partial, and home page read everything from `ThemeManager`. Coupled tests are updated in the same task so the full suite stays green. Originals in `public/css` etc. are still on disk but become unused.

**Files:**
- Modify: `resources/views/partials/tokens.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/pages/home.blade.php`
- Test (rewrite): `tests/Feature/StyleTokensTest.php`, `tests/Feature/ThemeTokensTest.php`, `tests/Unit/HeroSolarsystemCssTest.php`, `tests/Unit/SolarsystemJsTest.php`, `tests/Unit/FontsCssTest.php`, `tests/Unit/SkinCssTest.php`

**Interfaces:**
- Consumes: `$themeManager` shared view var (Task 4), `theme::hero`, `theme::cosmos`.

- [ ] **Step 1: Update the token emitter** `resources/views/partials/tokens.blade.php`:

```blade
@php
    $tokens = app('theme.manager')->tokens();
@endphp
<style>
:root {
@foreach ($tokens as $name => $value)
    --{{ $name }}: {{ $value }};
@endforeach
}
</style>
```

- [ ] **Step 2: Update the layout** `resources/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @include('partials.tokens')
    @foreach (app('theme.manager')->cssUrls() as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
    @stack('head')
</head>
<body class="@yield('body_class')">
    @includeIf('theme::cosmos')
    @include('partials.nav')
    @yield('content')
    @foreach (app('theme.manager')->jsAssets() as $js)
        <script src="{{ $js['url'] }}" @if($js['defer'])defer @endif @if($js['async'])async @endif></script>
    @endforeach
</body>
</html>
```

- [ ] **Step 3: Update home** `resources/views/pages/home.blade.php` — replace `@include('partials.hero')` with:

```blade
    @includeIf('theme::hero')
```

- [ ] **Step 4: Verify the live render manually**

Run: `php artisan view:clear && php artisan test --filter=HeroTest`
Expected: PASS — the stage, eyebrow, and secondary CTA still render (now from `theme::hero`).

- [ ] **Step 5: Rewrite the token tests** to assert the active (solarsystem) theme. Replace the body of `tests/Feature/StyleTokensTest.php`:

```php
    public function test_tokens_partial_renders_active_theme_variables(): void
    {
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-primary: #9dc1e6', $html); // solarsystem
        $this->assertStringContainsString('--font-base:', $html);
    }

    public function test_branding_overrides_a_theme_token(): void
    {
        \App\Models\SiteSetting::current()->update(['branding' => ['color-primary' => '#ff0000']]);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-primary: #ff0000', $html);
        $this->assertStringNotContainsString('--color-primary: #9dc1e6', $html);
    }
```

And replace `tests/Feature/ThemeTokensTest.php` body:

```php
    public function test_active_theme_emits_all_token_names(): void
    {
        $html = view('partials.tokens')->render();

        $this->assertStringContainsString('--color-bg-alt:', $html);
        $this->assertStringContainsString('--color-heading:', $html);
        $this->assertStringContainsString('--font-display:', $html);
        $this->assertStringContainsString('--nav-height: 4.5rem', $html); // solarsystem
        $this->assertStringContainsString('--container-width: 64rem', $html); // inherited default
    }
```

- [ ] **Step 6: Repoint the CSS/JS file-location tests** to the theme package.

`tests/Unit/HeroSolarsystemCssTest.php` — replace both methods:

```php
    public function test_stage_css_defines_animated_solar_system(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/hero.css'));
        $this->assertStringContainsString('.stage', $css);
        $this->assertStringContainsString('.orbit', $css);
        $this->assertStringContainsString('@keyframes spin', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_active_theme_manifest_loads_stage_stylesheet(): void
    {
        $css = app('theme.manager')->cssUrls();
        $this->assertNotEmpty(array_filter($css, fn ($u) => str_ends_with($u, '/css/hero.css')));
    }
```

`tests/Unit/SolarsystemJsTest.php` — replace both methods:

```php
    public function test_js_exists_and_self_guards(): void
    {
        $js = file_get_contents(public_path('themes/theme_solarsystem/js/solarsystem.js'));
        $this->assertStringContainsString('.twinkle', $js);
        $this->assertStringContainsString('data-parallax', $js);
        $this->assertStringContainsString('.stage', $js);
    }

    public function test_active_theme_loads_js_deferred(): void
    {
        $js = app('theme.manager')->jsAssets();
        $solar = array_values(array_filter($js, fn ($a) => str_ends_with($a['url'], 'solarsystem.js')));
        $this->assertNotEmpty($solar);
        $this->assertTrue($solar[0]['defer']);
    }
```

`tests/Unit/FontsCssTest.php` — change both `public_path('css/fonts.css')` → `public_path('themes/theme_solarsystem/css/fonts.css')`, change the file-existence loop base `public_path("fonts/$file")` → `public_path("themes/theme_solarsystem/fonts/$file")`, and drop EB Garamond expectations (mystik-only, not in this theme's fonts.css). The Cinzel assertion stays.

`tests/Unit/SkinCssTest.php` — change every `public_path('css/skin.css')` to `public_path('themes/theme_solarsystem/css/skin.css')` (6 occurrences).

- [ ] **Step 7: Run the FULL suite, verify green**

Run: `php artisan view:clear && php artisan test`
Expected: PASS (all). If `PublicPagesTest` asserts old `css/...` asset hrefs, update those assertions to the manifest URLs the same way.

- [ ] **Step 8: Commit**

```bash
git add resources/views tests
git commit -m "feat: load CSS/JS/tokens/partials from the active theme manifest"
```

---

### Task 6: Delete the now-unused original assets

The migrated copies are authoritative; remove the originals so there's one source of truth.

**Files:**
- Delete: `public/css/{structure,skin,cosmos,hero-solarsystem,fonts}.css`, `public/js/solarsystem.js`, `resources/views/partials/{hero,cosmos}.blade.php`, and the Solar System WOFF2 now living under the theme (`public/fonts/{jost-*,cormorant-garamond-*,cinzel-*}.woff2`).

> Keep `public/css/article.css` and `public/vendor/ckeditor/ckeditor5.css` — the blog "paper" is app-level and still `@push`ed on the post page. Keep `public/fonts/eb-garamond-*.woff2` for now (referenced only by the retired mystik theme; removed with mystik cleanup if desired).

- [ ] **Step 1: Delete the files** (Git Bash):

```bash
rm public/css/structure.css public/css/skin.css public/css/cosmos.css public/css/hero-solarsystem.css public/css/fonts.css
rm public/js/solarsystem.js
rm resources/views/partials/hero.blade.php resources/views/partials/cosmos.blade.php
rm public/fonts/jost-300.woff2 public/fonts/jost-400.woff2 public/fonts/jost-500.woff2 public/fonts/jost-600.woff2
rm public/fonts/cormorant-garamond-400.woff2 public/fonts/cormorant-garamond-500.woff2 public/fonts/cormorant-garamond-400-italic.woff2
rm public/fonts/cinzel-400.woff2 public/fonts/cinzel-600.woff2 public/fonts/cinzel-700.woff2
```

- [ ] **Step 2: Run the FULL suite, verify green**

Run: `php artisan view:clear && php artisan test`
Expected: PASS (Task 5 already repointed every test to the theme package).

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "refactor: remove original assets superseded by theme_solarsystem package"
```

---

### Task 7: `theme_default` light base theme

A real, selectable light theme so "default" lists in the picker and gives the light-token assertions a home.

**Files:**
- Create: `public/themes/theme_default/theme.json`, `.htaccess`, `css/structure.css`, `css/skin.css`, `views/hero.blade.php`
- Test: `tests/Unit/ThemeDefaultTest.php`

**Interfaces:**
- Consumes: `ThemeManager` (switching `SiteSetting.theme` to `default`).

- [ ] **Step 1: Write the failing test** `tests/Unit/ThemeDefaultTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_theme_emits_light_tokens(): void
    {
        SiteSetting::current()->update(['theme' => 'default']);
        $tokens = (new ThemeManager)->tokens();

        $this->assertSame('#2563eb', $tokens['color-primary']);
        $this->assertSame('4rem', $tokens['nav-height']);
    }

    public function test_default_theme_renders_a_hero(): void
    {
        SiteSetting::current()->update(['theme' => 'default']);
        $this->get('/en')->assertOk()->assertSee('Understanding the Why Behind Your Choices');
    }
}
```

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test --filter=ThemeDefaultTest`
Expected: FAIL (folder/manifest missing → falls back to solarsystem, wrong tokens; and route caching of theme namespace).

- [ ] **Step 3: Create the package.** `.htaccess` identical to Task 2 Step 2. `public/themes/theme_default/css/structure.css` — a minimal layout (container + nav + body box model) using tokens; `css/skin.css` — light appearance, must keep the contract strings required by `SkinCssTest` (`.hero`, `var(--hero-overlay)`, `var(--font-display)`, `var(--color-heading)`, `.page-home nav`, `.blog-grid`, `.card__media`, `.card__body`, and the `.image-style-*` / centered `figure.image` rules). Reuse the solarsystem `skin.css`/`structure.css` as the starting point (they're already token-driven; the light look comes purely from token values).

```bash
cp public/themes/theme_solarsystem/css/structure.css public/themes/theme_default/css/structure.css
cp public/themes/theme_solarsystem/css/skin.css      public/themes/theme_default/css/skin.css
```

`public/themes/theme_default/views/hero.blade.php` — a simple static hero bound to `SiteSetting.hero` (no cosmic art):

```blade
@php($hero = \App\Models\SiteSetting::current()->hero)
<section class="stage page-home-hero">
    <div class="container">
        @if(!empty($hero['eyebrow']))<p class="eyebrow">{{ $hero['eyebrow'] }}</p>@endif
        <h1 class="title">{{ $hero['headline'] }}</h1>
        <p class="lede">{{ $hero['subhead'] }}</p>
        <p class="hero-actions">
            <a class="btn btn-primary" href="{{ $hero['cta_url'] }}">{{ $hero['cta_label'] }}</a>
            @if(!empty($hero['cta2_label']))<a class="btn btn-ghost" href="{{ $hero['cta2_url'] }}">{{ $hero['cta2_label'] }}</a>@endif
        </p>
    </div>
</section>
```

> The `class="stage"` keeps `HeroTest::test_home_renders_stage...` valid for any active theme. `theme_default` has no `cosmos`/`js`, so those manifest keys are omitted.

- [ ] **Step 4: Author `public/themes/theme_default/theme.json`** — same 16 tokens with the **light** values from `config/tokens.php` (`color-primary #2563eb`, `color-bg #ffffff`, `nav-height 4rem`, the system-ui font stacks, etc.). `assets.css` = `["css/structure.css","css/skin.css"]`; no `js`; `views` = `{ "namespace": "theme", "hero": "hero" }`; `title` "Default (Light)".

- [ ] **Step 5: Run the test, verify it passes**

Run: `php artisan view:clear && php artisan test --filter=ThemeDefaultTest`
Expected: PASS.

- [ ] **Step 6: Run the FULL suite (ThemeJsonContractTest now validates two themes)**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add public/themes/theme_default tests/Unit/ThemeDefaultTest.php
git commit -m "feat: add theme_default light base theme package"
```

---

### Task 8: Admin Themes picker

**Files:**
- Create: `app/Http/Controllers/Admin/ThemeController.php`
- Create: `resources/views/admin/themes/index.blade.php`
- Modify: `routes/web.php:10-19`
- Modify: `resources/views/admin/dashboard.blade.php`
- Test: `tests/Feature/AdminThemesTest.php`

**Interfaces:**
- Consumes: `ThemeManager::available()`, `SiteSetting`.
- Produces: routes `admin.themes.index` (GET `/admin/themes`), `admin.themes.update` (PATCH `/admin/themes`).

- [ ] **Step 1: Write the failing test** `tests/Feature/AdminThemesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminThemesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_index_lists_available_themes_and_marks_active(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/themes')
            ->assertOk()
            ->assertSee('Solar System')
            ->assertSee('Default (Light)');
    }

    public function test_admin_can_switch_theme(): void
    {
        $this->actingAs($this->admin())
            ->patch('/admin/themes', ['theme' => 'default'])
            ->assertRedirect('/admin/themes');

        $this->assertSame('default', SiteSetting::current()->fresh()->theme);
    }

    public function test_unknown_theme_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->patch('/admin/themes', ['theme' => '../../etc'])
            ->assertSessionHasErrors('theme');

        $this->assertSame('solarsystem', SiteSetting::current()->fresh()->theme);
    }

    public function test_guests_cannot_access(): void
    {
        $this->get('/admin/themes')->assertRedirect('/admin/login');
    }
}
```

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test --filter=AdminThemesTest`
Expected: FAIL (route not defined).

- [ ] **Step 3: Implement the controller** `app/Http/Controllers/Admin/ThemeController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function index()
    {
        $themes = app('theme.manager')->available();

        return view('admin.themes.index', compact('themes'));
    }

    public function update(Request $request)
    {
        $names = array_column(app('theme.manager')->available(), 'name');

        $data = $request->validate([
            'theme' => ['required', 'string', Rule::in($names)],
        ]);

        SiteSetting::current()->update(['theme' => $data['theme']]);
        Artisan::call('view:clear');

        return redirect('/admin/themes')->with('status', "Theme switched to {$data['theme']}.");
    }
}
```

- [ ] **Step 4: Add the routes** — inside the `Route::prefix('admin')->middleware('admin')->group(...)` block in `routes/web.php`:

```php
    Route::get('themes', [\App\Http\Controllers\Admin\ThemeController::class, 'index'])->name('admin.themes.index');
    Route::patch('themes', [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('admin.themes.update');
```

- [ ] **Step 5: Create the view** `resources/views/admin/themes/index.blade.php` (follow the existing admin layout/markup conventions — check `resources/views/admin/dashboard.blade.php` for the wrapping layout it `@extends`):

```blade
@extends('admin.dashboard' === '' ? '' : 'layouts.app')
@section('title', 'Themes')
@section('content')
<div class="container">
    <h1>Themes</h1>
    @if(session('status'))<p class="muted">{{ session('status') }}</p>@endif
    @error('theme')<p class="muted">{{ $message }}</p>@enderror

    <div class="blog-grid">
        @foreach($themes as $t)
            <form method="POST" action="{{ route('admin.themes.update') }}" class="card">
                @csrf @method('PATCH')
                @if($t['screenshot'])<img class="card__media" src="{{ $t['screenshot'] }}" alt="{{ $t['title'] }}">@endif
                <div class="card__body">
                    <h3>{{ $t['title'] }} @if($t['active'])<span class="muted">(active)</span>@endif</h3>
                    <p class="muted">{{ $t['description'] }}</p>
                    <input type="hidden" name="theme" value="{{ $t['name'] }}">
                    <button class="btn btn-primary" @disabled($t['active'])>Apply</button>
                </div>
            </form>
        @endforeach
    </div>
</div>
@endsection
```

> Adjust the `@extends`/section names to match what `admin/dashboard.blade.php` actually uses (read it first). The placeholder ternary above is only to flag that — replace with the real admin layout name.

- [ ] **Step 6: Link from the dashboard** — add to `resources/views/admin/dashboard.blade.php` a link: `<a href="{{ route('admin.themes.index') }}">Themes</a>` in the existing nav/links area.

- [ ] **Step 7: Run the test, verify it passes**

Run: `php artisan test --filter=AdminThemesTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/ThemeController.php resources/views/admin routes/web.php tests/Feature/AdminThemesTest.php
git commit -m "feat: admin theme picker (list, apply, validate)"
```

---

### Task 9: Repurpose `app:apply-theme`

CLI equivalent of the picker: flips the `theme` pointer, validates the folder, clears views.

**Files:**
- Modify: `app/Console/Commands/ApplyTheme.php`
- Test (rewrite): `tests/Feature/ApplyThemeCommandTest.php`

**Interfaces:**
- Consumes: `ThemeManager::available()`, `SiteSetting`.

- [ ] **Step 1: Rewrite the test** `tests/Feature/ApplyThemeCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyThemeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_solarsystem_sets_pointer_and_emits_cosmos_tokens(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'solarsystem'])->assertExitCode(0);

        $this->assertSame('solarsystem', SiteSetting::current()->fresh()->theme);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-bg: #05060c', $html);
    }

    public function test_applying_default_sets_pointer_and_emits_light(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'default'])->assertExitCode(0);

        $this->assertSame('default', SiteSetting::current()->fresh()->theme);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-primary: #2563eb', $html);
    }

    public function test_unknown_theme_fails(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'nope'])->assertExitCode(1);
        $this->assertSame('solarsystem', SiteSetting::current()->fresh()->theme);
    }
}
```

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test --filter=ApplyThemeCommandTest`
Expected: FAIL (old command writes `branding`, no `theme` pointer).

- [ ] **Step 3: Rewrite the command** `app/Console/Commands/ApplyTheme.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ApplyTheme extends Command
{
    protected $signature = 'app:apply-theme {name}';

    protected $description = 'Set the active theme (folder under public/themes/theme_<name>)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $names = array_column(app('theme.manager')->available(), 'name');

        if (! in_array($name, $names, true)) {
            $this->error("Unknown theme: {$name}. Available: " . implode(', ', $names));

            return self::FAILURE;
        }

        SiteSetting::current()->update(['theme' => $name]);
        Artisan::call('view:clear');
        $this->info("Active theme set to '{$name}'.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test, verify it passes**

Run: `php artisan test --filter=ApplyThemeCommandTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ApplyTheme.php tests/Feature/ApplyThemeCommandTest.php
git commit -m "feat: app:apply-theme now sets the active-theme pointer"
```

---

### Task 10: Docs & retire the old theming files

**Files:**
- Modify: `README.md`
- Modify: `CLAUDE.md`
- Delete: `config/themes.php`, `docs/theme-style-map.json`
- Test: full suite (no new test; ensure nothing references the deleted files).

- [ ] **Step 1: Confirm nothing references the deletions**

Run: `grep -rn "config('themes" app/ resources/ tests/ ; grep -rn "theme-style-map" app/ resources/ tests/ docs/superpowers`
Expected: no functional references (only historical mentions in old spec/plan docs are fine).

- [ ] **Step 2: Delete the retired files**

```bash
rm config/themes.php docs/theme-style-map.json
```

- [ ] **Step 3: Update `CLAUDE.md`** — rewrite the two upkeep rules:
  - Rule 1 (style map): point at "the active theme's `public/themes/theme_<name>/theme.json` (validated by `public/themes/theme.schema.json`)" instead of `docs/theme-style-map.json`. Keep the "update in the same change" requirement.
  - Rule 2 (infra): add the theme-folder mechanism, `ThemeManager`, the `theme` column, and `/admin/themes` to the list of things that require a README update.

- [ ] **Step 4: Update `README.md`** — rewrite these sections for the folder model:
  - **Theming:** replace the `config/tokens.php → branding → tokens.blade.php → public/css/*` diagram with `SiteSetting.theme → public/themes/theme_<name>/theme.json → ThemeManager (defaults ← theme.json ← branding) → tokens.blade.php → manifest CSS/JS + theme:: partials`. Document the package layout, `theme.json`/`theme.schema.json`, and the `theme::` namespace.
  - **Available themes:** `solarsystem` (active), `default` (light base); note `mystik` retired (follow-up to repackage).
  - **Building a new theme:** "drop a `public/themes/theme_<name>/` folder with a `theme.json` validating against `theme.schema.json`; select it in Admin → Themes or `php artisan app:apply-theme <name>`."
  - **CSS / asset layering:** the order is now the manifest's `assets.css`; article paper still `@push`ed app-side.
  - **Artisan table:** `app:apply-theme {name}` now "sets the active-theme pointer" (no longer writes token arrays).
  - **Admin section / routes:** add `/admin/themes` (index + update).
  - **Project layout:** add `public/themes/`, `app/Services/ThemeManager.php`, `app/Providers/ThemeServiceProvider.php`; remove `config/themes.php`.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "docs: document theme-package model; retire config/themes.php and theme-style-map.json"
```

---

## Self-Review notes (addressed)

- **Spec coverage:** §2 architecture → Tasks 4–6; §3 theme.json → Task 2; §4 ThemeManager → Tasks 3–5; §5 admin/storage/CLI → Tasks 1, 8, 9; §6 migration → Tasks 2, 6, 7; §7 docs/tests → Task 10 + test updates across Tasks 5/7/8/9. Out-of-scope (mystik repackage, theme upload, live preview) intentionally omitted (§8).
- **Active-theme/test-contract conflict resolved:** default pointer = `solarsystem` keeps page-render tests green; token tests rewritten to solarsystem values in Task 5; light-token assertions rehomed in `theme_default` (Task 7).
- **Type consistency:** `ThemeManager` method names (`active`, `manifest`, `tokens`, `cssUrls`, `jsAssets`, `viewsPath`, `available`) are used identically in Tasks 4, 5, 8, 9. The singleton is resolved as `app('theme.manager')` everywhere.
- **Green-at-each-commit:** copy (T2) → add manager (T3) → register namespace (T4) → flip + retest (T5) → delete originals (T6). No commit leaves the suite red.
- **Known follow-up:** the admin view's `@extends` must be matched to the real admin layout (flagged in Task 8 Step 5); `theme_mystik` repackaging deferred.
