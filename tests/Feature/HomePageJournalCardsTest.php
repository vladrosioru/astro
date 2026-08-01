<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageJournalCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_block_hidden_when_blog_section_disabled(): void
    {
        // Every link in the "From the Journal" block points into /journal, which
        // 404s while the blog section is off. The whole block must go with it.
        $post = Post::create(['status' => 'published', 'published_at' => now(), 'featured_image' => '/storage/media/pic.jpg']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Featured', 'slug' => 'featured', 'body' => '<p>Hi</p>']);

        $setting = SiteSetting::current();
        $setting->update(['sections' => ['blog' => false] + $setting->sections]);

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('From the Journal')
            ->assertDontSee('/en/journal');
    }

    public function test_featured_card_shows_reading_time_next_to_date(): void
    {
        $post = Post::create(['status' => 'published', 'published_at' => now(), 'reading_time' => 6]);
        $post->translations()->create(['locale' => 'en', 'title' => 'Featured', 'slug' => 'featured', 'body' => '<p>Hi</p>']);

        $this->get('/en')->assertOk()
            ->assertSee('card__reading-time', false)
            ->assertSee('6 min. read');
    }

    public function test_rest_of_articles_card_shows_reading_time_next_to_date(): void
    {
        $newest = Post::create(['status' => 'published', 'published_at' => now(), 'featured_image' => '/storage/media/newest.jpg']);
        $newest->translations()->create(['locale' => 'en', 'title' => 'Newest', 'slug' => 'newest', 'body' => '<p>Hi</p>']);

        $older = Post::create(['status' => 'published', 'published_at' => now()->subDay(), 'featured_image' => '/storage/media/older.jpg', 'reading_time' => 4]);
        $older->translations()->create(['locale' => 'en', 'title' => 'Older', 'slug' => 'older', 'body' => '<p>Hi</p>']);

        $this->get('/en')->assertOk()
            ->assertSee('about-card__reading-time', false)
            ->assertSee('4 min. read');
    }

    public function test_rest_of_articles_card_omits_reading_time_when_not_set(): void
    {
        $newest = Post::create(['status' => 'published', 'published_at' => now(), 'featured_image' => '/storage/media/newest.jpg']);
        $newest->translations()->create(['locale' => 'en', 'title' => 'Newest', 'slug' => 'newest', 'body' => '<p>Hi</p>']);

        $older = Post::create(['status' => 'published', 'published_at' => now()->subDay(), 'featured_image' => '/storage/media/older.jpg']);
        $older->translations()->create(['locale' => 'en', 'title' => 'Older', 'slug' => 'older', 'body' => '<p>Hi</p>']);

        $this->get('/en')->assertOk()->assertDontSee('about-card__reading-time', false);
    }
}
