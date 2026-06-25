<?php

namespace Tests\Unit;

use Tests\TestCase;

class FontsCssTest extends TestCase
{
    public function test_fonts_css_declares_the_self_hosted_faces(): void
    {
        $css = file_get_contents(public_path('css/fonts.css'));

        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString("font-family: 'Cinzel'", $css);
        $this->assertStringContainsString("font-family: 'EB Garamond'", $css);
        $this->assertStringContainsString('fonts/cinzel-400.woff2', $css);
        $this->assertStringContainsString('font-display: swap', $css);
    }
}
