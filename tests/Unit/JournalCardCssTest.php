<?php

namespace Tests\Unit;

use Tests\TestCase;

class JournalCardCssTest extends TestCase
{
    public function test_journal_card_media_row_lays_out_half_width_image_beside_text(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/structure.css'));

        // The image sits in a flex row beside the text column, not stacked
        // above it, and is constrained to half the row's width.
        $this->assertMatchesRegularExpression(
            '/\.card--media\s+\.card__row\s*\{[^}]*display:\s*flex/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.card--media\s+\.card__media-link\s*\{[^}]*max-width:\s*50%/s',
            $css
        );

        // Below the phone breakpoint, the row collapses back to a single
        // stacked column (image on top) instead of staying cramped side by side.
        $this->assertMatchesRegularExpression(
            '/@media\s*\([^)]*max-width:\s*720px\)\s*\{.*\.card--media\s+\.card__row\s*\{[^}]*flex-direction:\s*column/s',
            $css
        );
    }

    public function test_journal_card_author_line_is_italic_and_right_aligned(): void
    {
        $css = file_get_contents(public_path('themes/theme_solarsystem/css/skin.css'));

        $this->assertMatchesRegularExpression(
            '/\.card__author\s*\{[^}]*font-style:\s*italic[^}]*\}/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.card__author\s*\{[^}]*text-align:\s*right[^}]*\}/s',
            $css
        );
    }
}
