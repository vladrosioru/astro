<?php

namespace Tests\Feature;

use App\Models\Author;
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

    public function test_create_form_slug_field_is_not_editable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/posts/create')->assertOk();

        $response->assertDontSee('name="en_slug"', false);
        $response->assertDontSee('name="ro_slug"', false);
    }

    public function test_edit_form_shows_regenerate_checkbox_while_never_published(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Test', 'slug' => 'test']);

        $this->actingAs($admin)->get(route('admin.posts.edit', $post))
            ->assertOk()
            ->assertSee('name="en_regenerate_slug"', false);
    }

    public function test_edit_form_hides_regenerate_checkbox_once_ever_published(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $post = Post::create(['status' => 'draft', 'first_published_at' => now()->subDay()]);
        $post->translations()->create(['locale' => 'en', 'title' => 'Test', 'slug' => 'test']);

        $this->actingAs($admin)->get(route('admin.posts.edit', $post))
            ->assertOk()
            ->assertDontSee('name="en_regenerate_slug"', false);
    }

    public function test_edit_form_hides_regenerate_checkbox_for_a_currently_published_legacy_post_with_null_first_published_at(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        // Simulates a post published before `first_published_at` existed:
        // status is published but the backfill column is still null.
        $post = Post::create(['status' => 'published', 'published_at' => now()]);
        $post->translations()->create(['locale' => 'en', 'title' => 'Test', 'slug' => 'test']);

        $this->actingAs($admin)->get(route('admin.posts.edit', $post))
            ->assertOk()
            ->assertDontSee('name="en_regenerate_slug"', false)
            ->assertSee('Locked (this post has been published)');
    }

    public function test_create_form_ships_article_css_to_the_preview_frame_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->getContent();

        // article.css is token-driven, so it belongs to the themed article —
        // not to the admin page, which is theme-free. It reaches the isolated
        // preview frame through the asset manifest instead.
        $this->assertDoesNotMatchRegularExpression('/<link[^>]+css\/article\.css/', $content);
        $this->assertMatchesRegularExpression(
            '/id="adm-preview-assets"[^>]*>.*?css\\\\?\/article\.css/s',
            $content
        );
    }

    public function test_create_form_wraps_each_editor_in_the_admin_editor_surface(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->getContent();

        // The editing surface is admin-styled: admin.css re-skins CKEditor's
        // chrome through .adm-editor, so the panel matches the rest of the
        // module instead of the active theme.
        $this->assertMatchesRegularExpression(
            '/<div class="adm-editor"[^>]*>\s*<textarea[^>]*id="editor_en"/',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/<div class="adm-editor"[^>]*>\s*<textarea[^>]*id="editor_ro"/',
            $content
        );
    }

    public function test_create_form_has_a_live_preview_toggle_per_locale(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->getContent();

        foreach (['en', 'ro'] as $locale) {
            // A button that swaps the live CKEditor view for a read-only
            // render of the themed article, so the admin can see exactly what
            // the post will look like once published, unsaved edits included.
            // The render happens inside an iframe so the theme's CSS can never
            // touch the admin page around it.
            $this->assertMatchesRegularExpression(
                '/<button class="preview-toggle[^"]*"[^>]*data-locale="'.$locale.'"/',
                $content
            );
            $this->assertStringContainsString(
                '<iframe class="adm-editor__frame" id="preview_'.$locale.'"',
                $content
            );
        }
    }

    public function test_the_preview_frame_is_handed_the_active_theme_css(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->getContent();

        // Preview must show the real published article, so the frame — and
        // only the frame — receives the active theme's stylesheets and tokens.
        foreach (app('theme.manager')->cssUrls() as $href) {
            $this->assertStringContainsString(str_replace('/', '\/', $href), $content);
        }
        $this->assertDoesNotMatchRegularExpression('/<link[^>]+themes\/theme_/', $content);
    }

    public function test_create_form_shows_date_field_between_status_and_card_image(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->getContent();

        $statusPos = strpos($content, 'name="status"');
        $datePos = strpos($content, 'name="published_date"');
        $cardImagePos = strpos($content, 'name="card_image"');

        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($datePos);
        $this->assertNotFalse($cardImagePos);
        $this->assertTrue($statusPos < $datePos && $datePos < $cardImagePos);
    }

    public function test_create_form_date_field_has_expected_min_and_max(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertOk()
            ->assertSee('min="2026-01-01"', false)
            ->assertSee('max="'.now()->toDateString().'"', false);
    }

    public function test_create_form_date_field_is_not_locked(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertOk()
            ->assertDontSee('class="date-locked"', false)
            ->assertDontSee('<input type="checkbox" name="unlock_date"', false);
    }

    public function test_edit_form_date_field_is_editable_for_a_draft_post(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Test', 'slug' => 'test']);

        $this->actingAs($admin)->get(route('admin.posts.edit', $post))
            ->assertOk()
            ->assertDontSee('class="date-locked"', false)
            ->assertDontSee('<input type="checkbox" name="unlock_date"', false);
    }

    public function test_edit_form_date_field_is_locked_and_red_for_a_published_post(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $post = Post::create(['status' => 'published', 'published_at' => '2026-02-10 00:00:00']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Test', 'slug' => 'test']);

        $content = $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input type="date" name="published_date"[^>]*readonly[^>]*class="date-locked"[^>]*value="2026-02-10"/',
            $content
        );
        $this->assertStringContainsString('name="unlock_date"', $content);
        $this->assertStringContainsString('already published', $content);
    }

    public function test_edit_form_author_dropdown_lists_authors_and_selects_current(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $andrei = Author::create(['name' => 'Andrei | AstroTherapia']);
        $other = Author::create(['name' => 'Someone Else']);
        $post = Post::create(['status' => 'draft', 'author_id' => $other->id]);
        $post->translations()->create(['locale' => 'en', 'title' => 'Test', 'slug' => 'test']);

        $content = $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()->getContent();

        $this->assertStringContainsString('name="author_id"', $content);
        $this->assertStringContainsString('>Andrei | AstroTherapia</option>', $content);
        $this->assertMatchesRegularExpression(
            '/<option value="'.$other->id.'" selected>Someone Else<\/option>/',
            $content
        );
    }

    public function test_edit_form_shows_reading_time_field_after_author(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $post = Post::create(['status' => 'draft', 'reading_time' => 7]);
        $post->translations()->create(['locale' => 'en', 'title' => 'Test', 'slug' => 'test']);

        $content = $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()->getContent();

        $authorPos = strpos($content, 'name="author_id"');
        $readingTimePos = strpos($content, 'name="reading_time"');
        $cardImagePos = strpos($content, 'name="card_image"');

        $this->assertNotFalse($authorPos);
        $this->assertNotFalse($readingTimePos);
        $this->assertNotFalse($cardImagePos);
        $this->assertTrue($authorPos < $readingTimePos && $readingTimePos < $cardImagePos);
        $this->assertStringContainsString('value="7"', $content);
    }

    public function test_reading_time_field_has_min_1_and_max_99(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertOk()
            ->assertSee('name="reading_time"', false)
            ->assertSee('min="1"', false)
            ->assertSee('max="99"', false);
    }
}
