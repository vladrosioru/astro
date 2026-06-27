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

    public function test_theme_namespace_resolves_hero_partial(): void
    {
        $this->assertTrue(view()->exists('theme::hero'));
        $this->assertTrue(view()->exists('theme::cosmos'));
    }
}
