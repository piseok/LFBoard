<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => '관리자',
                'password' => 'admin1234',
                'level' => User::LEVEL_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
                'unsubscribe_token' => Str::random(32),
            ]
        );
    }
}
