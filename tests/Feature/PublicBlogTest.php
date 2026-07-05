<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPost(string $slug = 'hello'): Post
    {
        $post = Post::create(['status' => 'published', 'published_at' => now()]);
        $post->translations()->create([
            'locale' => 'en', 'title' => 'Hello', 'slug' => $slug, 'body' => '<p>Hi there</p>',
        ]);

        return $post;
    }

    public function test_index_lists_published_posts(): void
    {
        $this->publishedPost();
        $this->get('/en/journal')->assertOk()->assertSee('Hello');
    }

    public function test_show_renders_published_post_body(): void
    {
        $this->publishedPost('my-post');
        $this->get('/en/journal/my-post')->assertOk()->assertSee('Hi there', false);
    }

    public function test_draft_post_is_not_visible(): void
    {
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Draft', 'slug' => 'draft', 'body' => 'x']);

        $this->get('/en/journal/draft')->assertNotFound();
    }

    public function test_blog_404s_when_section_disabled(): void
    {
        $this->publishedPost();
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['blog' => false] + $setting->sections]);

        $this->get('/en/journal')->assertNotFound();
    }

    public function test_card_shows_image_when_featured_image_set(): void
    {
        $post = $this->publishedPost();
        $post->update(['featured_image' => '/storage/media/pic.jpg']);

        $this->get('/en/journal')->assertOk()
            ->assertSee('card__media', false)
            ->assertSee('/storage/media/pic.jpg', false);
    }

    public function test_card_is_text_only_without_featured_image(): void
    {
        $this->publishedPost();

        $response = $this->get('/en/journal')->assertOk();
        $response->assertSee('blog-grid', false);
        $response->assertDontSee('card__media', false);
    }

    public function test_legacy_blog_urls_redirect_to_journal(): void
    {
        $this->publishedPost('my-post');

        $this->get('/en/blog')->assertRedirect('/en/journal');
        $this->get('/en/blog/my-post')->assertRedirect('/en/journal/my-post');
    }

    public function test_legacy_articles_urls_redirect_to_journal(): void
    {
        $this->publishedPost('my-post');

        $this->get('/en/articles')->assertRedirect('/en/journal');
        $this->get('/en/articles/my-post')->assertRedirect('/en/journal/my-post');
    }
}
