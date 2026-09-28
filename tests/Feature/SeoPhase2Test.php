<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function publishedPost(string $slug = 'sample-post'): Post
    {
        $author = Author::firstOrCreate(
            ['name' => 'Andrei | AstroTherapia'],
            ['description' => 'Astrologer', 'picture' => 'img/logo-nav.png'],
        );

        $post = Post::create([
            'status' => 'published',
            'published_at' => now(),
            'author_id' => $author->id,
            'featured_image' => '/storage/media/test-cover.jpg',
        ]);
        $post->translations()->create([
            'locale' => 'en',
            'title' => 'Understanding Planetary Transits',
            'slug' => $slug,
            'subtitle' => 'How planetary movements guide our daily choices',
            'seo_description' => 'A comprehensive guide to understanding planetary transits.',
            'body' => '<p>Article body content here</p>',
        ]);

        return $post;
    }

    public function test_layout_renders_open_graph_meta_tags_on_static_pages(): void
    {
        $response = $this->get('/en');
        $response->assertOk()
            ->assertSee('<meta property="og:site_name" content="AstroTherapia">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:url" content="http://localhost/en">', false)
            ->assertSee('<meta property="og:locale" content="en_US">', false)
            ->assertSee('<meta property="og:locale:alternate" content="ro_RO">', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('property="og:description"', false)
            ->assertSee('property="og:image"', false);

        $roResponse = $this->get('/ro');
        $roResponse->assertOk()
            ->assertSee('<meta property="og:locale" content="ro_RO">', false)
            ->assertSee('<meta property="og:locale:alternate" content="en_US">', false);
    }

    public function test_layout_renders_twitter_card_meta_tags_on_static_pages(): void
    {
        $response = $this->get('/en/about');
        $response->assertOk()
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('name="twitter:title"', false)
            ->assertSee('name="twitter:description"', false)
            ->assertSee('name="twitter:image"', false);
    }

    public function test_blog_post_renders_article_og_and_twitter_tags(): void
    {
        $this->publishedPost('transits-guide');

        $response = $this->get('/en/journal/transits-guide');
        $response->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="og:title" content="Understanding Planetary Transits">', false)
            ->assertSee('<meta property="og:image" content="http://localhost/storage/media/test-cover.jpg">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="twitter:title" content="Understanding Planetary Transits">', false);
    }

    public function test_all_pages_include_organization_and_website_json_ld(): void
    {
        $response = $this->get('/en');
        $content = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString('"@type": "Organization"', $content);
        $this->assertStringContainsString('"name": "AstroTherapia"', $content);
        $this->assertStringContainsString('"@type": "WebSite"', $content);
    }

    public function test_blog_post_includes_article_json_ld(): void
    {
        $this->publishedPost('json-ld-post');

        $response = $this->get('/en/journal/json-ld-post');
        $content = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString('"@type": "Article"', $content);
        $this->assertStringContainsString('"headline": "Understanding Planetary Transits"', $content);
        $this->assertStringContainsString('"name": "Andrei | AstroTherapia"', $content);
    }

    public function test_about_and_services_pages_include_faq_json_ld(): void
    {
        $aboutResponse = $this->get('/en/about');
        $aboutContent = $aboutResponse->getContent();
        $aboutResponse->assertOk();
        $this->assertStringContainsString('"@type": "FAQPage"', $aboutContent);
        $this->assertStringContainsString('Is this the same as reading my horoscope?', $aboutContent);

        $servicesResponse = $this->get('/en/services');
        $servicesContent = $servicesResponse->getContent();
        $servicesResponse->assertOk();
        $this->assertStringContainsString('"@type": "FAQPage"', $servicesContent);
    }

    public function test_services_page_includes_service_json_ld(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString('"@type": "Service"', $content);
        $this->assertStringContainsString('Natal Chart Analysis', $content);
    }
}
