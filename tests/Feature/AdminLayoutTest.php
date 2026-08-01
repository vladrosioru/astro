<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ThemeManager;
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

    public function test_the_active_theme_has_no_effect_on_admin_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $render = function (string $theme, string $url) use ($admin) {
            SiteSetting::current()->switchTheme($theme);
            // ThemeManager memoises the active theme for the lifetime of the
            // singleton, so replace the instance rather than just the pointer.
            app()->instance('theme.manager', new ThemeManager);

            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            // CSRF tokens are per-request; everything else must be identical.
            return preg_replace('/name="_token" value="[^"]+"/', 'name="_token"', $html);
        };

        $names = array_column(app('theme.manager')->available(), 'name');
        $this->assertContains('default', $names);
        $this->assertContains('solarsystem', $names);

        $this->assertSame($render('default', '/admin/posts'), $render('solarsystem', '/admin/posts'));

        // Control: the same comparison on a public page must show a
        // difference, otherwise the assertion above proves nothing.
        $this->assertNotSame($render('default', '/en'), $render('solarsystem', '/en'));
    }

    public function test_themes_page_is_itself_unthemed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin/themes')->assertOk()->getContent();

        // Screenshots are plain images out of each package; no theme
        // stylesheet is loaded to render this page.
        $this->assertDoesNotMatchRegularExpression('/<link[^>]+themes\/theme_[^>]+\.css/', $content);
        $this->assertStringContainsString('adm-theme-grid', $content);
    }

    public function test_database_page_keeps_destructive_actions_in_a_danger_panel(): void
    {
        config(['database_admin.restore_enabled' => true]);
        config(['database.connections.source.database' => 'prod_db']);

        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin/database')->assertOk()->getContent();

        $this->assertStringContainsString('adm-panel--danger', $content);
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
