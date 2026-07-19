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

    public function test_posts_index_shows_thumbnail_subtitle_and_new_post_button(): void
    {
        $post = Post::create(['status' => 'draft', 'featured_image' => '/storage/media/thumb.jpg']);
        $post->translations()->create([
            'locale' => 'en', 'title' => 'A Great Post', 'slug' => 'a-great-post',
            'subtitle' => 'A punchy subtitle',
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/posts');

        $response->assertOk();
        $response->assertSee('class="btn btn-primary"', false);
        $response->assertSee('New Post');
        $response->assertSee('<img src="/storage/media/thumb.jpg"', false);
        $response->assertSee('A punchy subtitle');
    }

    public function test_posts_index_shows_subtitle_italic_under_title_and_status_above_delete(): void
    {
        $post = Post::create(['status' => 'draft', 'featured_image' => '/storage/media/thumb.jpg']);
        $post->translations()->create([
            'locale' => 'en', 'title' => 'A Great Post', 'slug' => 'a-great-post',
            'subtitle' => 'A punchy subtitle',
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/posts');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('font-style:italic', false);
        $response->assertSee('flex-direction:column', false);
        $response->assertDontSee('calc(1em - 3pt)', false);

        $statusPos = strpos($html, 'draft');
        $deletePos = strpos($html, '>Delete<');
        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($deletePos);
        $this->assertLessThan($deletePos, $statusPos, 'status should render above the Delete button');
    }

    public function test_posts_index_draws_a_divider_line_after_every_row(): void
    {
        $first = Post::create(['status' => 'draft']);
        $first->translations()->create(['locale' => 'en', 'title' => 'First', 'slug' => 'first']);
        $second = Post::create(['status' => 'draft']);
        $second->translations()->create(['locale' => 'en', 'title' => 'Second', 'slug' => 'second']);

        $response = $this->actingAs($this->admin())->get('/admin/posts');
        $html = $response->getContent();

        $response->assertOk();

        // Divider is a border-bottom on each row's own <li> (not a border-top),
        // so it can only ever appear after a row, never above the first one.
        $rowCount = substr_count($html, '<li style="display:flex;align-items:center;gap:1em;line-height:1.4;');
        $dividerCount = substr_count($html, 'border-bottom:1px solid var(--color-muted);');
        $this->assertSame(2, $rowCount);
        $this->assertSame($rowCount, $dividerCount);
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

    public function test_creating_a_post_auto_derives_the_slug_from_the_title(): void
    {
        $this->actingAs($this->admin())->post('/admin/posts', [
            'status' => 'draft',
            'en_title' => 'A Brand New Title',
        ])->assertRedirect('/admin/posts');

        $post = Post::first();
        $this->assertSame('a-brand-new-title', $post->translation('en')->slug);
    }

    public function test_two_posts_with_the_same_title_get_different_slugs_in_the_same_locale(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/posts', [
            'status' => 'draft', 'en_title' => 'Duplicate Title',
        ])->assertRedirect('/admin/posts');

        $this->actingAs($admin)->post('/admin/posts', [
            'status' => 'draft', 'en_title' => 'Duplicate Title',
        ])->assertRedirect('/admin/posts');

        $slugs = Post::with('translations')->get()
            ->map(fn ($post) => $post->translation('en')->slug)
            ->all();

        $this->assertCount(2, array_unique($slugs));
        $this->assertContains('duplicate-title', $slugs);
    }

    public function test_editing_the_title_leaves_the_existing_slug_untouched_by_default(): void
    {
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Original Title', 'slug' => 'original-title']);

        $this->actingAs($this->admin())->put("/admin/posts/{$post->id}", [
            'status' => 'draft',
            'en_title' => 'Completely Different Title',
        ])->assertRedirect('/admin/posts');

        $this->assertSame('original-title', $post->fresh()->translation('en')->slug);
    }

    public function test_regenerate_checkbox_updates_the_slug_while_never_published(): void
    {
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Original Title', 'slug' => 'original-title']);

        $this->actingAs($this->admin())->put("/admin/posts/{$post->id}", [
            'status' => 'draft',
            'en_title' => 'Regenerated Title',
            'en_regenerate_slug' => '1',
        ])->assertRedirect('/admin/posts');

        $this->assertSame('regenerated-title', $post->fresh()->translation('en')->slug);
    }

    public function test_regenerate_is_ignored_once_a_post_has_ever_been_published(): void
    {
        $post = Post::create(['status' => 'published', 'published_at' => now(), 'first_published_at' => now()]);
        $post->translations()->create(['locale' => 'en', 'title' => 'Original Title', 'slug' => 'original-title']);

        // Post is set back to draft, but first_published_at must stay locked.
        $this->actingAs($this->admin())->put("/admin/posts/{$post->id}", [
            'status' => 'draft',
            'en_title' => 'Forged Regenerate Attempt',
            'en_regenerate_slug' => '1',
        ])->assertRedirect('/admin/posts');

        $this->assertSame('original-title', $post->fresh()->translation('en')->slug);
    }

    public function test_regenerate_is_ignored_for_a_currently_published_legacy_post_with_null_first_published_at(): void
    {
        // Simulates a post published before `first_published_at` existed:
        // status is published but the backfill column is still null.
        $post = Post::create(['status' => 'published', 'published_at' => now()]);
        $post->translations()->create(['locale' => 'en', 'title' => 'Original Title', 'slug' => 'original-title']);

        $this->actingAs($this->admin())->put("/admin/posts/{$post->id}", [
            'status' => 'published',
            'en_title' => 'Forged Regenerate Attempt',
            'en_regenerate_slug' => '1',
        ])->assertRedirect('/admin/posts');

        $this->assertSame('original-title', $post->fresh()->translation('en')->slug);
    }
}
