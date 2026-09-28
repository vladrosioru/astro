<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPhase1Test extends TestCase
{
    use RefreshDatabase;

    private function publishedPostWithBothLocales(string $enSlug = 'star-signs', string $roSlug = 'semne-zodiacale'): Post
    {
        $author = Author::firstOrCreate(
            ['name' => 'Andrei | AstroTherapia'],
            ['description' => 'Astrologer', 'picture' => 'img/logo-nav.png'],
        );

        $post = Post::create(['status' => 'published', 'published_at' => now(), 'author_id' => $author->id]);
        $post->translations()->create([
            'locale' => 'en',
            'title' => 'Star Signs and Patterns',
            'slug' => $enSlug,
            'subtitle' => 'Understanding archetypes',
            'seo_description' => 'A deep dive into star signs and patterns.',
            'body' => '<p>English body</p>',
        ]);
        $post->translations()->create([
            'locale' => 'ro',
            'title' => 'Semne Zodiacale și Tipare',
            'slug' => $roSlug,
            'subtitle' => 'Înțelegerea arhetipurilor',
            'seo_description' => 'O analiză aprofundată a semnelor zodiacale.',
            'body' => '<p>Romanian body</p>',
        ]);

        return $post;
    }

    public function test_all_public_pages_have_meta_description(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('<meta name="description" content="AstroTherapia helps you understand the patterns behind your choices', false);

        $this->get('/ro')
            ->assertOk()
            ->assertSee('<meta name="description" content="AstroTherapia te ajută să înțelegi tiparele din spatele alegerilor tale', false);

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('<meta name="description" content="What is AstroTherapia?', false);

        $this->get('/ro/about')
            ->assertOk()
            ->assertSee('<meta name="description" content="Ce este AstroTherapia?', false);

        $this->get('/en/services')
            ->assertOk()
            ->assertSee('<meta name="description" content="Explore astrology services from natal chart analysis', false);

        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('<meta name="description" content="Get in touch with AstroTherapia.', false);

        $this->get('/en/journal')
            ->assertOk()
            ->assertSee('<meta name="description" content="Reflections on astrology, self-knowledge', false);
    }

    public function test_blog_post_has_meta_description_without_duplicates(): void
    {
        $this->publishedPostWithBothLocales('sample-post');

        $response = $this->get('/en/journal/sample-post');
        $response->assertOk()
            ->assertSee('<meta name="description" content="A deep dive into star signs and patterns."', false);

        $this->assertSame(
            1,
            substr_count($response->getContent(), '<meta name="description"'),
            'Expected exactly one meta description tag on blog post.'
        );
    }

    public function test_all_public_pages_have_canonical_url(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/en">', false);

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/en/about">', false);

        $this->get('/en/services')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/en/services">', false);

        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/en/contact">', false);

        $this->get('/en/journal')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/en/journal">', false);

        $this->publishedPostWithBothLocales('canonical-post');
        $this->get('/en/journal/canonical-post')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/en/journal/canonical-post">', false);
    }

    public function test_pages_have_hreflang_tags(): void
    {
        $response = $this->get('/en/about');
        $response->assertOk()
            ->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/en/about">', false)
            ->assertSee('<link rel="alternate" hreflang="ro" href="http://localhost/ro/about">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="http://localhost/en/about">', false);

        $post = $this->publishedPostWithBothLocales('astro-reading', 'lectura-astrologica');
        $blogResponse = $this->get('/en/journal/astro-reading');
        $blogResponse->assertOk()
            ->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/en/journal/astro-reading">', false)
            ->assertSee('<link rel="alternate" hreflang="ro" href="http://localhost/ro/journal/lectura-astrologica">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="http://localhost/en/journal/astro-reading">', false);
    }

    public function test_pages_have_rich_descriptive_titles_with_brand(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('<title>AstroTherapia — Astrology Readings &amp; Birth Chart Analysis</title>', false);

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('<title>About AstroTherapia — Understanding the Why Behind Your Choices · AstroTherapia</title>', false);

        $this->get('/en/services')
            ->assertOk()
            ->assertSee('<title>Astrology Services — Natal Chart, Tarot &amp; Relationship Readings · AstroTherapia</title>', false);

        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('<title>Contact AstroTherapia — Book Your Astrology Session</title>', false);

        $this->get('/en/journal')
            ->assertOk()
            ->assertSee('<title>Cosmic Journal — Astrology Insights &amp; Reflections · AstroTherapia</title>', false);

        $this->publishedPostWithBothLocales('title-post');
        $this->get('/en/journal/title-post')
            ->assertOk()
            ->assertSee('<title>Star Signs and Patterns · AstroTherapia</title>', false);
    }

    public function test_about_page_has_h1_heading(): void
    {
        $response = $this->get('/en/about');
        $response->assertOk()
            ->assertSee('<h1 class="about-h2 about-h2--center">Understanding the Why Behind Your Choices</h1>', false);
    }

    public function test_layout_includes_favicon_links_and_favicon_is_valid(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('<link rel="icon" type="image/x-icon" href="http://localhost/favicon.ico">', false)
            ->assertSee('<link rel="apple-touch-icon"', false);

        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')), 'favicon.ico must not be empty');
    }
}
