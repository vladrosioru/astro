<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackToTopTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_shows_back_to_top_button(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('id="back-to-top"', false)
            ->assertSee('css/back-to-top.css', false)
            ->assertSee('js/back-to-top.js', false);
    }

    public function test_admin_page_does_not_show_back_to_top_button(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('id="back-to-top"', false)
            ->assertDontSee('js/back-to-top.js', false);
    }
}
