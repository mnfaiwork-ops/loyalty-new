<?php

namespace Tests\Feature;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\User;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return config('app.brand');
    }

    private function otherBrand(): string
    {
        return $this->brand() === 'amura' ? 'reglow' : 'amura';
    }

    public function test_login_ditolak_kalau_brand_scope_beda(): void
    {
        $user = User::create([
            'name' => 'CS Brand Lain',
            'email' => 'cs-'.$this->otherBrand().'@x.id',
            'password' => 'rahasia',
            'role' => 'cs',
            'brand_scope' => $this->otherBrand(),
        ]);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'rahasia'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_seeder_membuat_super_admin_dan_bisa_login(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::where('role', 'super_admin')
            ->where('brand_scope', $this->brand())
            ->firstOrFail();

        $this->assertSame($this->brand(), $admin->brand_scope);

        $this->post('/login', ['email' => $admin->email, 'password' => env('ADMIN_PASSWORD', 'ganti-saya-123')])
            ->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame($admin->id, auth()->id());
    }
}
