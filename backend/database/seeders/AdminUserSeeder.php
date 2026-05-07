<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.login_email') ?? env('ADMIN_LOGIN_EMAIL');
        $password = config('admin.login_password') ?? env('ADMIN_LOGIN_PASSWORD');
        $name = env('ADMIN_NAME', 'Admin');

        if (!$email || !$password) {
            $this->command->warn('AdminUserSeeder skipped: set ADMIN_LOGIN_EMAIL and ADMIN_LOGIN_PASSWORD in .env');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => 'admin',
            ],
        );

        $this->command->info("Admin user ready: {$user->email}");
    }
}
