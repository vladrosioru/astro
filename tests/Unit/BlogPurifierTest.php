<?php

namespace Tests\Unit;

use Tests\TestCase;

class BlogPurifierTest extends TestCase
{
    public function test_keeps_ckeditor_image_markup(): void
    {
        $html = '<figure class="image image-style-side"><img src="/storage/media/a.jpg" style="width:40%;" alt="x"><figcaption>Cap</figcaption></figure>';
        $clean = clean($html, 'blog');

        $this->assertStringContainsString('<figure', $clean);
        $this->assertStringContainsString('image-style-side', $clean);
        $this->assertStringContainsString('<figcaption', $clean);
        $this->assertStringContainsString('width:40%', $clean);
    }

    public function test_strips_scripts_and_unknown_classes(): void
    {
        $html = '<p>ok</p><script>alert(1)</script><figure class="evil"><img src="x" onerror="alert(1)"></figure>';
        $clean = clean($html, 'blog');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('evil', $clean);
        $this->assertStringContainsString('<p>ok</p>', $clean);
    }
}
