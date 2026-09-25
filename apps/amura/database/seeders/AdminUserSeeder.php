<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@amura.local'],
            [
                'name' => 'Super Admin Amura',
                'password' => env('ADMIN_PASSWORD', 'ganti-saya-123'),
                'role' => 'super_admin',
                'brand_scope' => config('app.brand'),
            ],
        );
    }
}
