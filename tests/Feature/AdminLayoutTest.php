<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_the_admin_stylesheet_and_no_theme_css(): void
    {
        $content = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('css/admin.css', $content);

        // The admin module is theme-independent: no theme package asset and no
        // :root token block may reach an admin page.
        $this->assertStringNotContainsString('/themes/theme_', $content);
        $this->assertStringNotContainsString('--color-primary', $content);
    }

    public function test_login_page_has_no_site_nav_footer_or_back_to_top(): void
    {
        $content = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('nav-toggle-input', $content);
        $this->assertStringNotContainsString('site-footer', $content);
        $this->assertStringNotContainsString('back-to-top', $content);
    }

    public function test_login_page_body_carries_the_admin_class(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('<body class="adm-body">', false);
    }

    public function test_posts_index_uses_admin_row_markup_and_no_inline_styles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create([
            'locale' => 'en',
            'title' => 'Draft one',
            'slug' => 'draft-one',
            'body' => '<p>x</p>',
        ]);

        $content = $this->actingAs($admin)->get('/admin/posts')->assertOk()->getContent();

        $this->assertStringContainsString('adm-row', $content);
        $this->assertStringContainsString('adm-pill--draft', $content);
        // The old index hand-rolled its layout with inline style attributes.
        $this->assertStringNotContainsString('style="display:flex', $content);
        $this->assertStringNotContainsString('/themes/theme_', $content);
    }

    public function test_authors_index_uses_round_row_avatars_and_no_theme_css(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Author::create(['name' => 'Ioana P.']);

        $content = $this->actingAs($admin)->get('/admin/authors')->assertOk()->getContent();

        $this->assertStringContainsString('adm-row__thumb is-round', $content);
        $this->assertStringNotContainsString('style="display:flex', $content);
        $this->assertStringNotContainsString('/themes/theme_', $content);
    }

    public function test_author_form_uses_admin_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/authors/create')
            ->assertOk()
            ->assertSee('adm-field', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="picture"', false);
    }
}
