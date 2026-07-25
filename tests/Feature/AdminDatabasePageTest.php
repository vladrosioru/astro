<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDatabasePageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_dashboard_links_to_the_database_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Database');
    }

    public function test_database_page_renders_for_an_admin(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Backups');
    }

    public function test_guests_cannot_access_the_database_page(): void
    {
        $this->get('/admin/database')->assertRedirect('/admin/login');
    }
}
