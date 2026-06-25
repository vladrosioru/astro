<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_admin_user(): void
    {
        $this->artisan('app:create-admin', ['email' => 'a@b.com', 'password' => 'secret123'])
            ->assertExitCode(0);

        $user = User::where('email', 'a@b.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }
}
