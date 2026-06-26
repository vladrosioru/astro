<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPostCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_create_a_post_and_body_is_sanitized(): void
    {
        $this->actingAs($this->admin())->post('/admin/posts', [
            'status' => 'published',
            'en_title' => 'Hello', 'en_slug' => 'hello',
            'en_body' => '<p>Hi</p><script>alert(1)</script>',
            'ro_title' => 'Salut', 'ro_slug' => 'salut', 'ro_body' => '<p>Buna</p>',
        ])->assertRedirect('/admin/posts');

        $post = Post::first();
        $this->assertNotNull($post);
        $body = $post->translation('en')->body;
        $this->assertStringContainsString('Hi', $body);
        $this->assertStringNotContainsString('<script>', $body);
    }

    public function test_admin_can_delete_a_post(): void
    {
        $post = Post::create(['status' => 'draft']);

        $this->actingAs($this->admin())->delete("/admin/posts/{$post->id}")
            ->assertRedirect('/admin/posts');

        $this->assertSame(0, Post::count());
    }

    public function test_non_admin_cannot_create_a_post(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/admin/posts', [])->assertForbidden();
    }

    public function test_uploading_a_card_image_saves_a_square_featured_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/admin/posts', [
            'status' => 'draft',
            'en_title' => 'Hello', 'en_slug' => 'hello',
            'card_image' => UploadedFile::fake()->image('card.jpg', 2000, 1000),
        ])->assertRedirect('/admin/posts');

        $post = Post::first();
        $this->assertNotNull($post->featured_image);
        // Root-relative URL (portable across host/port/domain), not absolute.
        $this->assertStringStartsWith('/', $post->featured_image);
        $this->assertStringNotContainsString('http', $post->featured_image);

        $media = Media::first();
        $this->assertNotNull($media);
        $this->assertSame(1200, $media->width);
        $this->assertSame(1200, $media->height);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_removing_a_card_image_nulls_featured_image(): void
    {
        $post = Post::create(['status' => 'draft', 'featured_image' => '/storage/media/old.jpg']);
        $post->translations()->create(['locale' => 'en', 'title' => 'T', 'slug' => 't']);

        $this->actingAs($this->admin())->put("/admin/posts/{$post->id}", [
            'status' => 'draft',
            'en_title' => 'T', 'en_slug' => 't',
            'remove_card_image' => '1',
        ])->assertRedirect('/admin/posts');

        $this->assertNull($post->fresh()->featured_image);
    }
}
