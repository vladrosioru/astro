<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('secret123'),
            'is_admin' => true,
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'secret123'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('secret123'),
            'is_admin' => true,
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_can_log_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }
}
