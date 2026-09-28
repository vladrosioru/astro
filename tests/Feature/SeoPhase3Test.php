<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function createPostWithTranslations(string $status = 'published', string $suffix = ''): Post
    {
        $author = Author::firstOrCreate(
            ['name' => 'Andrei | AstroTherapia'],
            ['description' => 'Astrologer', 'picture' => 'img/logo-nav.png'],
        );

        $post = Post::create([
            'status' => $status,
            'published_at' => now(),
            'author_id' => $author->id,
            'featured_image' => '/storage/media/transit-guide.jpg',
        ]);

        $post->translations()->create([
            'locale' => 'en',
            'title' => 'Transits Guide',
            'slug' => 'transits-guide'.($suffix ? '-'.$suffix : ''),
            'body' => '<p>Article body</p>',
        ]);

        $post->translations()->create([
            'locale' => 'ro',
            'title' => 'Ghid Tranzite',
            'slug' => 'ghid-tranzite'.($suffix ? '-'.$suffix : ''),
            'body' => '<p>Continut articol</p>',
        ]);

        return $post;
    }

    public function test_sitemap_xml_returns_valid_xml_with_static_and_blog_urls(): void
    {
        $this->createPostWithTranslations('published');

        // Also create a draft post to verify it is NOT included
        $this->createPostWithTranslations('draft', 'draft');

        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('<urlset', $content);
        $this->assertStringContainsString('http://www.sitemaps.org/schemas/sitemap/0.9', $content);
        $this->assertStringContainsString('http://www.w3.org/1999/xhtml', $content);

        // Check static pages in both locales
        $this->assertStringContainsString('/en</loc>', $content);
        $this->assertStringContainsString('/ro</loc>', $content);
        $this->assertStringContainsString('/en/about</loc>', $content);
        $this->assertStringContainsString('/ro/about</loc>', $content);
        $this->assertStringContainsString('/en/services</loc>', $content);
        $this->assertStringContainsString('/ro/services</loc>', $content);
        $this->assertStringContainsString('/en/contact</loc>', $content);
        $this->assertStringContainsString('/ro/contact</loc>', $content);
        $this->assertStringContainsString('/en/journal</loc>', $content);
        $this->assertStringContainsString('/ro/journal</loc>', $content);

        // Check hreflang alternates inside url entries
        $this->assertStringContainsString('hreflang="en"', $content);
        $this->assertStringContainsString('hreflang="ro"', $content);
        $this->assertStringContainsString('hreflang="x-default"', $content);

        // Check published post URLs
        $this->assertStringContainsString('/en/journal/transits-guide</loc>', $content);
        $this->assertStringContainsString('/ro/journal/ghid-tranzite</loc>', $content);

        // Draft post should NOT be in sitemap
        $this->assertStringNotContainsString('draft', $content);
    }

    public function test_robots_txt_contains_sitemap_and_disallows_admin(): void
    {
        $robotsContent = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Disallow: /admin/', $robotsContent);
        $this->assertStringContainsString('Sitemap:', $robotsContent);
        $this->assertStringContainsString('sitemap.xml', $robotsContent);
    }

    public function test_footer_shows_updated_copyright_year_and_instagram_link(): void
    {
        $response = $this->get('/en');
        $response->assertOk()
            ->assertSee('AstroTherapia © 2025 - 2026', false)
            ->assertSee('https://www.instagram.com/astrotherapia/', false)
            ->assertSee('aria-label="Instagram"', false);
    }

    public function test_blog_post_featured_image_has_dimensions_and_lazy_loading(): void
    {
        $this->createPostWithTranslations('published');

        $response = $this->get('/en/journal/transits-guide');
        $response->assertOk()
            ->assertSee('loading="lazy"', false)
            ->assertSee('width="', false)
            ->assertSee('height="', false);
    }

    public function test_nav_logo_image_is_optimized_file(): void
    {
        $logoPath = public_path('img/logo-nav.png');
        $this->assertFileExists($logoPath);
        $this->assertLessThan(100 * 1024, filesize($logoPath), 'Logo should be compressed under 100 KB');
    }
}
