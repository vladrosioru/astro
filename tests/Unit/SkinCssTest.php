<?php

namespace Tests\Unit;

use Tests\TestCase;

class SkinCssTest extends TestCase
{
    public function test_skin_defines_image_alignment_rules(): void
    {
        $css = file_get_contents(public_path('css/skin.css'));

        $this->assertStringContainsString('.image-style-align-left', $css);
        $this->assertStringContainsString('.image-style-align-right', $css);
        $this->assertStringContainsString('.image-style-side', $css);
        $this->assertStringContainsString('figure.image img', $css);
    }

    public function test_skin_defines_hero_and_nav_appearance(): void
    {
        $css = file_get_contents(public_path('css/skin.css'));

        $this->assertStringContainsString('.hero', $css);
        $this->assertStringContainsString('var(--hero-overlay)', $css);
        $this->assertStringContainsString('var(--font-display)', $css);
        $this->assertStringContainsString('var(--color-heading)', $css);
    }

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
}
