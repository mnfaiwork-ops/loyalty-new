<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\User;
use Tests\TestCase;

final class ShippingTest extends TestCase
{
    use RefreshDatabase;

    private function cs(): User
    {
        return User::create(['name' => 'CS', 'email' => 'cs@ship.id', 'password' => 'rahasia', 'role' => 'cs', 'brand_scope' => config('app.brand')]);
    }

    private function claim(string $status): Claim
    {
        $customer = Customer::create(['phone' => '0812'.random_int(10000000, 99999999), 'brand' => config('app.brand'), 'name' => 'Sari']);

        return Claim::create([
            'brand' => config('app.brand'),
            'customer_id' => $customer->id,
            'source' => 'katalog',
            'prize_name' => 'Gold Serum',
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => $status,
            'expires_at' => now()->addDays(14),
        ]);
    }

    public function test_halaman_pengiriman_menampilkan_diproses_dan_dikirim(): void
    {
        $packing = $this->claim('diproses');
        $shipped = $this->claim('dikirim');

        $this->actingAs($this->cs())->get('/admin/shipping')
            ->assertOk()
            ->assertSee($packing->claim_code)
            ->assertSee($shipped->claim_code);
    }

    public function test_isi_resi_lalu_tandai_diterima(): void
    {
        $cs = $this->cs();
        $claim = $this->claim('diproses');

        $this->actingAs($cs)->post("/admin/shipping/{$claim->claim_code}/ship", ['shipping_awb' => 'JX-HADIAH-9'])
            ->assertRedirect(route('admin.shipping'));
        $this->assertSame('dikirim', $claim->fresh()->status);
        $this->assertSame('JX-HADIAH-9', $claim->fresh()->shipping_awb);

        $this->actingAs($cs)->post("/admin/shipping/{$claim->claim_code}/deliver")
            ->assertRedirect(route('admin.shipping'));
        $this->assertSame('selesai', $claim->fresh()->status);
    }

    public function test_resi_wajib_diisi(): void
    {
        $claim = $this->claim('diproses');

        $this->actingAs($this->cs())->post("/admin/shipping/{$claim->claim_code}/ship", ['shipping_awb' => ''])
            ->assertSessionHasErrors('shipping_awb');
    }

    public function test_finance_tidak_bisa_akses_pengiriman(): void
    {
        $finance = User::create(['name' => 'F', 'email' => 'f@ship.id', 'password' => 'rahasia', 'role' => 'finance', 'brand_scope' => config('app.brand')]);

        $this->actingAs($finance)->get('/admin/shipping')->assertForbidden();
    }
}
