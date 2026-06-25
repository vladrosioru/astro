<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
