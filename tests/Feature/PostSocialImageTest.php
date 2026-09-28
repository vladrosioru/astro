<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostSocialImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        Storage::fake('public');
    }

    public function test_post_creation_with_card_image_generates_social_image(): void
    {
        $author = Author::create(['name' => 'Author One']);
        $file = UploadedFile::fake()->image('cover.jpg', 800, 800);

        $response = $this->actingAs($this->admin)->post('/admin/posts', [
            'status' => 'draft',
            'author_id' => $author->id,
            'en_title' => 'Sample Article With Cover',
            'en_body' => '<p>Body text</p>',
            'card_image' => $file,
        ]);

        $response->assertRedirect('/admin/posts');

        $post = Post::whereHas('translations', fn ($q) => $q->where('title', 'Sample Article With Cover'))->first();
        $this->assertNotNull($post);
        $this->assertNotNull($post->featured_image);
        $this->assertNotNull($post->social_image);
        $this->assertStringContainsString('social-', $post->social_image);

        // Check storage file exists and dimensions are 1200x630
        $socialRelativePath = ltrim(parse_url($post->social_image, PHP_URL_PATH), '/');
        // Storage::fake strips 'storage/' prefix
        $storagePath = str_replace('storage/', '', $socialRelativePath);
        Storage::disk('public')->assertExists($storagePath);

        $realPath = Storage::disk('public')->path($storagePath);
        [$w, $h] = getimagesize($realPath);
        $this->assertEquals(1200, $w);
        $this->assertEquals(630, $h);
    }

    public function test_post_creation_without_card_image_generates_logo_social_image(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/posts', [
            'status' => 'draft',
            'en_title' => 'Article Without Cover',
            'en_body' => '<p>Body text</p>',
        ]);

        $response->assertRedirect('/admin/posts');

        $post = Post::whereHas('translations', fn ($q) => $q->where('title', 'Article Without Cover'))->first();
        $this->assertNotNull($post);
        $this->assertNull($post->featured_image);
        $this->assertNotNull($post->social_image);
        $this->assertStringContainsString('social-', $post->social_image);

        $socialRelativePath = ltrim(parse_url($post->social_image, PHP_URL_PATH), '/');
        $storagePath = str_replace('storage/', '', $socialRelativePath);
        Storage::disk('public')->assertExists($storagePath);

        $realPath = Storage::disk('public')->path($storagePath);
        [$w, $h] = getimagesize($realPath);
        $this->assertEquals(1200, $w);
        $this->assertEquals(630, $h);
    }

    public function test_post_update_regenerates_social_image_when_card_image_removed(): void
    {
        $post = Post::create([
            'status' => 'draft',
            'featured_image' => '/storage/media/existing-card.jpg',
            'social_image' => '/storage/media/social-old.jpg',
        ]);
        $post->translations()->create([
            'locale' => 'en',
            'title' => 'Existing Post',
            'slug' => 'existing-post',
            'body' => '<p>Body</p>',
        ]);

        $response = $this->actingAs($this->admin)->put("/admin/posts/{$post->id}", [
            'status' => 'draft',
            'en_title' => 'Existing Post Updated',
            'en_body' => '<p>Body</p>',
            'remove_card_image' => '1',
        ]);

        $response->assertRedirect('/admin/posts');

        $post->refresh();
        $this->assertNull($post->featured_image);
        $this->assertNotNull($post->social_image);
        $this->assertNotEquals('/storage/media/social-old.jpg', $post->social_image);
    }

    public function test_public_article_view_prefers_social_image_in_og_tags(): void
    {
        $post = Post::create([
            'status' => 'published',
            'published_at' => now(),
            'featured_image' => '/storage/media/square-card.jpg',
            'social_image' => '/storage/media/social-wide.jpg',
        ]);
        $post->translations()->create([
            'locale' => 'en',
            'title' => 'Article With Social Image',
            'slug' => 'article-with-social-image',
            'body' => '<p>Body</p>',
        ]);

        $response = $this->get('/en/journal/article-with-social-image');
        $response->assertOk()
            ->assertSee('<meta property="og:image" content="http://localhost/storage/media/social-wide.jpg">', false)
            ->assertSee('<meta name="twitter:image" content="http://localhost/storage/media/social-wide.jpg">', false);
    }

    public function test_batch_generate_command_creates_social_images_for_posts_missing_them(): void
    {
        $post = Post::create([
            'status' => 'published',
            'published_at' => now(),
            'social_image' => null,
        ]);
        $post->translations()->create([
            'locale' => 'en',
            'title' => 'Post Missing Social Image',
            'slug' => 'post-missing-social-image',
            'body' => '<p>Body</p>',
        ]);

        $this->artisan('posts:generate-social-images')
            ->assertSuccessful();

        $post->refresh();
        $this->assertNotNull($post->social_image);
        $this->assertStringContainsString('social-', $post->social_image);
    }
}
