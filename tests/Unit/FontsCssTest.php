<?php

namespace Tests\Unit;

use Tests\TestCase;

class FontsCssTest extends TestCase
{
    public function test_fonts_css_references_self_hosted_theme_fonts(): void
    {
        $css = file_get_contents(public_path('css/fonts.css'));

        $this->assertStringContainsString("font-family: 'Jost'", $css);
        $this->assertStringContainsString('jost-400.woff2', $css);
        $this->assertStringContainsString("font-family: 'Cormorant Garamond'", $css);
        $this->assertStringContainsString('cormorant-garamond-400.woff2', $css);
        $this->assertStringContainsString('cormorant-garamond-400-italic.woff2', $css);
        $this->assertStringContainsString("font-family: 'EB Garamond'", $css);
        $this->assertStringContainsString('cinzel-400.woff2', $css);
    }

    public function test_theme_font_files_exist(): void
    {
        foreach ([
            'jost-300.woff2', 'jost-400.woff2', 'jost-500.woff2', 'jost-600.woff2',
            'cormorant-garamond-400.woff2', 'cormorant-garamond-500.woff2',
            'cormorant-garamond-400-italic.woff2',
        ] as $file) {
            $this->assertFileExists(public_path("fonts/$file"));
        }
    }
}
