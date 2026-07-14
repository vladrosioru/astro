<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroSolarsystemCssTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertNotEmpty(array_filter($css, fn ($u) => str_contains($u, '/css/hero.css?v=')));
    }
}
