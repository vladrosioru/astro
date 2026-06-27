<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminThemesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_index_lists_available_themes_and_marks_active(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/themes')
            ->assertOk()
            ->assertSee('Solar System')
            ->assertSee('Default (Light)');
    }

    public function test_admin_can_switch_theme(): void
    {
        $this->actingAs($this->admin())
            ->patch('/admin/themes', ['theme' => 'default'])
            ->assertRedirect('/admin/themes');

        $this->assertSame('default', SiteSetting::current()->fresh()->theme);
    }

    public function test_unknown_theme_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->patch('/admin/themes', ['theme' => '../../etc'])
            ->assertSessionHasErrors('theme');

        $this->assertSame('solarsystem', SiteSetting::current()->fresh()->theme);
    }

    public function test_guests_cannot_access(): void
    {
        $this->get('/admin/themes')->assertRedirect('/admin/login');
    }
}
