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
}
