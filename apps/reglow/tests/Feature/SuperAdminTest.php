<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use LoyaltyNew\Core\Models\User;
use Tests\TestCase;

final class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return config('app.brand');
    }

    private function otherBrand(): string
    {
        return $this->brand() === 'reglow' ? 'amura' : 'reglow';
    }

    private function user(string $role, ?string $brand = null): User
    {
        $brand ??= $this->brand();

        return User::create([
            'name' => ucfirst($role).' '.$brand,
            'email' => $role.'-'.$brand.'@x.id',
            'password' => 'rahasia',
            'role' => $role,
            'brand_scope' => $brand,
        ]);
    }

    public function test_super_admin_bisa_lihat_dan_buat_user(): void
    {
        $sa = $this->user('super_admin');

        $this->actingAs($sa)->get('/admin/users')->assertOk();

        $this->actingAs($sa)->post('/admin/users', [
            'name' => 'CS Baru',
            'email' => 'cs2@x.id',
            'password' => 'rahasia12345',
            'role' => 'cs',
            'brand_scope' => $this->brand(),
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'cs2@x.id', 'role' => 'cs']);
    }

    public function test_finance_tidak_bisa_akses_users(): void
    {
        $this->actingAs($this->user('finance'))
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_cs_tidak_bisa_akses_users(): void
    {
        $this->actingAs($this->user('cs'))
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_user_baru_bisa_login(): void
    {
        $sa = $this->user('super_admin');

        $this->actingAs($sa)->post('/admin/users', [
            'name' => 'CS Baru',
            'email' => 'cs2@x.id',
            'password' => 'rahasia12345',
            'role' => 'cs',
            'brand_scope' => $this->brand(),
        ])->assertRedirect();

        Auth::logout();

        $this->assertTrue(Auth::attempt(['email' => 'cs2@x.id', 'password' => 'rahasia12345']));
    }

    public function test_ubah_peran_user(): void
    {
        $sa = $this->user('super_admin');
        $cs = $this->user('cs');

        $this->actingAs($sa)->post('/admin/users/'.$cs->id.'/role', [
            'role' => 'finance',
            'brand_scope' => $this->brand(),
        ])->assertRedirect();

        $this->assertSame('finance', $cs->refresh()->role);
    }

    public function test_store_memaksa_brand_scope_ke_brand_app(): void
    {
        $sa = $this->user('super_admin');

        $this->actingAs($sa)->post('/admin/users', [
            'name' => 'CS Baru',
            'email' => 'cs2@x.id',
            'password' => 'rahasia12345',
            'role' => 'cs',
            'brand_scope' => $this->otherBrand(),
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'cs2@x.id', 'brand_scope' => $this->brand()]);
    }

    public function test_index_brand_scoped(): void
    {
        $sa = $this->user('super_admin');
        $other = $this->user('cs', $this->otherBrand());

        $this->actingAs($sa)
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee($other->email);
    }

    public function test_role_user_brand_lain_404(): void
    {
        $sa = $this->user('super_admin');
        $other = $this->user('cs', $this->otherBrand());

        $this->actingAs($sa)->post('/admin/users/'.$other->id.'/role', [
            'role' => 'finance',
            'brand_scope' => $this->brand(),
        ])->assertNotFound();

        $this->assertSame('cs', $other->refresh()->role);
    }

    public function test_validasi_role_tidak_valid(): void
    {
        $sa = $this->user('super_admin');

        $this->actingAs($sa)
            ->from('/admin/users')
            ->post('/admin/users', [
                'name' => 'Admin Nakal',
                'email' => 'nakal@x.id',
                'password' => 'rahasia12345',
                'role' => 'admin',
                'brand_scope' => $this->brand(),
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'nakal@x.id']);
    }
}
