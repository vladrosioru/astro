<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPostFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_uses_ckeditor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertOk()
            ->assertSee('vendor/ckeditor/ckeditor5.umd.js')
            ->assertSee('name="en_body"', false)
            ->assertDontSee('trix-editor');
    }

    public function test_create_form_has_card_image_upload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="card_image"', false);
    }

    public function test_edit_form_has_enctype_and_card_image_controls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $post = Post::create(['status' => 'draft', 'featured_image' => '/storage/media/pic.jpg']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Test', 'slug' => 'test']);

        $this->actingAs($admin)->get(route('admin.posts.edit', $post))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('src="/storage/media/pic.jpg"', false)
            ->assertSee('name="remove_card_image"', false);
    }
}
