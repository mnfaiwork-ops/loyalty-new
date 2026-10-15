<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Password cadangan hanya untuk dev. Di produksi wajib ADMIN_PASSWORD sendiri.
        if (app()->environment('production') && ! env('ADMIN_PASSWORD')) {
            throw new \RuntimeException('Isi ADMIN_PASSWORD di .env sebelum membuat admin produksi.');
        }

        User::firstOrCreate(
            ['email' => 'admin@reglow.local'],
            [
                'name' => 'Super Admin Reglow',
                'password' => env('ADMIN_PASSWORD', 'ganti-saya-123'),
                'role' => 'super_admin',
                'brand_scope' => config('app.brand'),
            ],
        );
    }
}
