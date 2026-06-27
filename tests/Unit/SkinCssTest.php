<?php

namespace Tests\Unit;

use Tests\TestCase;

class SkinCssTest extends TestCase
{
    public function test_skin_defines_image_alignment_rules(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/skin.css'));

        $this->assertStringContainsString('.image-style-align-left', $css);
        $this->assertStringContainsString('.image-style-align-right', $css);
        $this->assertStringContainsString('.image-style-side', $css);
        $this->assertStringContainsString('figure.image img', $css);
    }

    public function test_block_images_center_by_default_like_editor(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/skin.css'));

        // CKEditor's editing view centers default (break-text) block images via
        // `.ck-content .image { display: table; margin: .9em auto }`. The published
        // page must mirror that, otherwise a resized centered image renders left.
        $this->assertMatchesRegularExpression('/figure\.image\s*\{[^}]*display:\s*table/', $css);
        $this->assertMatchesRegularExpression('/figure\.image\s*\{[^}]*margin:[^;}]*\bauto\b/', $css);
    }

    public function test_skin_defines_hero_and_nav_appearance(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/skin.css'));

        $this->assertStringContainsString('.hero', $css);
        $this->assertStringContainsString('var(--hero-overlay)', $css);
        $this->assertStringContainsString('var(--font-display)', $css);
        $this->assertStringContainsString('var(--color-heading)', $css);
    }

    public function test_skin_defines_home_nav_overlay(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/skin.css'));

        $this->assertStringContainsString('.page-home nav', $css);
    }

    public function test_skin_defines_blog_card_grid(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/skin.css'));

        $this->assertStringContainsString('.blog-grid', $css);
        $this->assertStringContainsString('.card__media', $css);
        $this->assertStringContainsString('.card__body', $css);
        // Square image area via aspect-ratio + cover crop.
        $this->assertMatchesRegularExpression('/\.card__media\s*\{[^}]*aspect-ratio:\s*1/', $css);
        $this->assertMatchesRegularExpression('/\.card__media\s*\{[^}]*object-fit:\s*cover/', $css);
    }

    public function test_skin_retains_test_contract_tokens(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/skin.css'));

        // Contract required by ThemeTokens/SkinCss expectations.
        $this->assertStringContainsString('.hero', $css);
        $this->assertStringContainsString('var(--hero-overlay)', $css);
        $this->assertStringContainsString('var(--font-display)', $css);
        $this->assertStringContainsString('var(--color-heading)', $css);
    }
}
