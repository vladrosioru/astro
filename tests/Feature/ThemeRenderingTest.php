<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_emits_active_theme_manifest_assets(): void
    {
        $res = $this->get('/en')->assertOk();

        // The layout must load CSS/JS from the active theme's manifest, not hardcoded paths.
        $res->assertSee('themes/theme_solarsystem/css/hero.css', false);
        $res->assertSee('themes/theme_solarsystem/js/solarsystem.js', false);
    }

    public function test_missing_theme_folder_still_renders_with_default_tokens(): void
    {
        // The "site always renders" guarantee: an invalid pointer falls back to the
        // light default theme rather than 500-ing.
        SiteSetting::current()->update(['theme' => 'does_not_exist']);

        $this->get('/en')
            ->assertOk()
            ->assertSee('--color-primary: #2563eb', false); // light default token
    }

    public function test_switching_theme_clears_stale_branding(): void
    {
        // Branding is a per-theme override layer; it must not survive a theme
        // switch, or a previous theme's palette overrides the new theme's tokens
        // (the bug: a dark Solar System branding painted the light default dark).
        SiteSetting::current()->update([
            'theme' => 'solarsystem',
            'branding' => ['color-bg' => '#05060c', 'color-fg' => '#aab6c8'],
        ]);

        $this->artisan('app:apply-theme', ['name' => 'default'])->assertSuccessful();

        $this->assertSame([], SiteSetting::current()->fresh()->branding);
        $this->get('/en')
            ->assertOk()
            ->assertSee('--color-bg: #ffffff', false)   // default's own light token wins
            ->assertDontSee('--color-bg: #05060c', false); // stale dark branding is gone
    }

    public function test_reapplying_same_theme_keeps_branding(): void
    {
        // Re-applying the active theme is a no-op switch: deliberate per-theme
        // branding customizations must be preserved.
        SiteSetting::current()->update([
            'theme' => 'default',
            'branding' => ['color-primary' => '#abcdef'],
        ]);

        $this->artisan('app:apply-theme', ['name' => 'default'])->assertSuccessful();

        $this->assertSame(['color-primary' => '#abcdef'], SiteSetting::current()->fresh()->branding);
    }

    public function test_default_theme_hero_renders_with_partial_hero_data(): void
    {
        // A persisted hero that predates the CTA keys (only headline + subhead) must
        // not 500 the home page: the hero merges heroDefaults() for missing keys.
        SiteSetting::current()->update([
            'theme' => 'default',
            'hero' => ['headline' => 'Partial Headline', 'subhead' => 'Just two keys'],
        ]);

        $this->get('/en')
            ->assertOk()
            ->assertSee('Partial Headline');
    }
}
