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
        $this->get('/en/blog')->assertOk()->assertSee('Hello');
    }

    public function test_show_renders_published_post_body(): void
    {
        $this->publishedPost('my-post');
        $this->get('/en/blog/my-post')->assertOk()->assertSee('Hi there', false);
    }

    public function test_draft_post_is_not_visible(): void
    {
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Draft', 'slug' => 'draft', 'body' => 'x']);

        $this->get('/en/blog/draft')->assertNotFound();
    }

    public function test_blog_404s_when_section_disabled(): void
    {
        $this->publishedPost();
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['blog' => false] + $setting->sections]);

        $this->get('/en/blog')->assertNotFound();
    }
}
