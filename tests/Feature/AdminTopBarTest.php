<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTopBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_bar_lists_every_admin_section_and_marks_the_current_one(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        foreach (['Dashboard', 'Posts', 'Authors', 'Themes', 'Database'] as $section) {
            $this->assertStringContainsString('>'.$section.'</a>', $content);
        }

        // Only the section being viewed carries the active marker.
        $this->assertMatchesRegularExpression('/class="adm-bar__link is-on"[^>]*>Dashboard</', $content);
        $this->assertMatchesRegularExpression('/class="adm-bar__link"[^>]*>Posts</', $content);
    }

    public function test_top_bar_marks_the_section_a_nested_route_belongs_to(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $content = $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="adm-bar__link is-on"[^>]*>Posts</', $content);
    }

    public function test_top_bar_offers_the_public_site_the_account_and_log_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('View site')
            ->assertSee($admin->email)
            ->assertSee(route('admin.logout'), false);
    }

    public function test_login_page_has_no_top_bar(): void
    {
        // Nothing to navigate to before signing in.
        $this->get('/admin/login')->assertOk()->assertDontSee('adm-bar__link', false);
    }

    public function test_dashboard_shows_content_counts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('adm-tile', false)
            ->assertSee('Active theme');
    }
}
