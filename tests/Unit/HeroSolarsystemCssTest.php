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
