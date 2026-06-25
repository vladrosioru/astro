<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email} {password}';

    protected $description = 'Create or promote an admin user';

    public function handle(): int
    {
        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name' => $this->argument('email'),
                'password' => Hash::make($this->argument('password')),
                'is_admin' => true,
            ],
        );

        $this->info("Admin ready: {$user->email}");

        return self::SUCCESS;
    }
}
