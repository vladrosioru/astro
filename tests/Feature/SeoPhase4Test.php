<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPhase4Test extends TestCase
{
    use RefreshDatabase;

    public function test_services_page_h1_is_astrological_guidance(): void
    {
        $response = $this->get('/en/services');
        $response->assertOk()
            ->assertSee('<h1 class="about-hero__title">Astrological Guidance</h1>', false);
    }

    public function test_default_og_image_file_exists_and_is_valid_dimensions(): void
    {
        $path = public_path('img/og-default.jpg');
        $this->assertFileExists($path);
        $this->assertGreaterThan(5000, filesize($path), 'og-default.jpg should be a real image file');

        [$width, $height] = getimagesize($path);
        $this->assertSame(1200, $width);
        $this->assertSame(630, $height);
    }

    public function test_static_pages_render_default_og_image_and_dimensions(): void
    {
        $response = $this->get('/en');
        $response->assertOk()
            ->assertSee('<meta property="og:image" content="http://localhost/img/og-default.jpg">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('<meta property="og:image:height" content="630">', false)
            ->assertSee('<meta name="twitter:image" content="http://localhost/img/og-default.jpg">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }
}
