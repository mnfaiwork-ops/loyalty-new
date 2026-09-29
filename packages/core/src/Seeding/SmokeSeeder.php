<?php

namespace LoyaltyNew\Core\Seeding;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Prizes\TierConfig;

/**
 * Data dummy untuk SMOKE TEST semua alur (spec §9), sumber realistis dari
 * FairTrack Lead (status_fulfillment='complete' lolos; 'problem' ditolak).
 *
 * Dipakai HANYA di non-production. Idempotent: aman dijalankan berulang.
 *
 * Yang dibuat:
 *  - 4 user internal (cs, game_admin, finance, super_admin) per brand
 *  - katalog hadiah lengkap 3 tier per brand (+ hadiah besar Emas & Philips)
 *  - kuota hadiah besar (Emas 1, Philips 2) per brand
 *  - beberapa sesi siap-main (bisa langsung buka /play)
 *  - 1 klaim hadiah biasa (menunggu CS) + 1 klaim hadiah besar (menunggu Finance)
 */
final class SmokeSeeder
{
    /** Kredensial dummy — password seragam biar gampang dites. */
    public const PASSWORD = 'smoke12345';

    /** AWB + HP dummy yang PASTI lolos gate (StubOrderGate) untuk main game sendiri. */
    public static function playableOrders(): array
    {
        return [
            ['awb' => 'SMOKE-AMURA-001', 'phone' => '6281100000001'],
            ['awb' => 'SMOKE-AMURA-002', 'phone' => '6281100000002'],
            ['awb' => 'SMOKE-REGLOW-001', 'phone' => '6282200000001'],
            ['awb' => 'SMOKE-REGLOW-002', 'phone' => '6282200000002'],
        ];
    }

    public function run(): void
    {
        $this->seedUsers();
        $this->seedCatalogs();
        $this->seedQuotaCaps();
        $this->seedSessionsAndClaims();
    }

    private function brands(): array
    {
        return ['amura', 'reglow'];
    }

    private function seedUsers(): void
    {
        $people = [
            'cs' => ['name' => 'CS Demo', 'email' => 'cs@%s.local'],
            'game_admin' => ['name' => 'Game Admin Demo', 'email' => 'game@%s.local'],
            'finance' => ['name' => 'Finance Demo', 'email' => 'finance@%s.local'],
            'super_admin' => ['name' => 'Super Admin Demo', 'email' => 'super@%s.local'],
        ];

        foreach ($this->brands() as $brand) {
            foreach ($people as $role => $p) {
                DB::table('users')->updateOrInsert(
                    ['email' => sprintf($p['email'], $brand)],
                    [
                        'name' => $p['name'].' '.ucfirst($brand),
                        'password' => Hash::make(self::PASSWORD),
                        'role' => $role,
                        'brand_scope' => $brand,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function seedCatalogs(): void
    {
        // [brand => [name, tier, point_value, face_value, quota_total, is_capped]]
        $rows = [
            // ---- AMURA ----
            ['amura', 'Poin 50.000', 'poin', 50000, 0, 0, false],
            ['amura', 'Poin 25.000', 'poin', 25000, 0, 0, false],
            ['amura', 'Voucher Belanja Rp100.000', 'voucher', 0, 100000, 0, false],
            ['amura', 'Voucher Gratis Ongkir', 'voucher', 0, 20000, 0, false],
            ['amura', 'Totebag Amura', 'merch', 0, 75000, 0, false],
            ['amura', 'Emas 0,5 gram', 'hadiah_besar', 0, 800000, 1, true],
            ['amura', 'Philips Air Fryer', 'hadiah_besar', 0, 1500000, 2, true],
            // ---- REGLOW ----
            ['reglow', 'Poin 50.000', 'poin', 50000, 0, 0, false],
            ['reglow', 'Poin 25.000', 'poin', 25000, 0, 0, false],
            ['reglow', 'Voucher Belanja Rp100.000', 'voucher', 0, 100000, 0, false],
            ['reglow', 'Voucher Gratis Ongkir', 'voucher', 0, 20000, 0, false],
            ['reglow', 'Pouch Reglow', 'merch', 0, 75000, 0, false],
            ['reglow', 'Emas 0,5 gram', 'hadiah_besar', 0, 800000, 1, true],
            ['reglow', 'Philips Air Fryer', 'hadiah_besar', 0, 1500000, 2, true],
        ];

        foreach ($rows as [$brand, $name, $tier, $points, $face, $quota, $capped]) {
            PrizeCatalog::withoutGlobalScopes()->updateOrCreate(
                ['brand' => $brand, 'name' => $name],
                [
                    'tier' => $tier,
                    'point_value' => $points,
                    'face_value' => $face,
                    'quota_total' => $quota,
                    'quota_used' => 0,
                    'is_capped' => $capped,
                    'active' => true,
                ],
            );
        }
    }

    private function seedQuotaCaps(): void
    {
        $caps = [
            ['amura', 'Emas 0,5 gram', 1],
            ['amura', 'Philips Air Fryer', 2],
            ['reglow', 'Emas 0,5 gram', 1],
            ['reglow', 'Philips Air Fryer', 2],
        ];

        foreach ($caps as [$brand, $prize, $cap]) {
            // used_units tidak di-reset saat dijalankan ulang: hadiah besar seed
            // yang sudah dimenangkan tetap tercatat memakai kuota.
            $exists = DB::table('quota_caps')
                ->where('brand', $brand)->where('prize_name', $prize)->exists();
            if ($exists) {
                continue;
            }

            DB::table('quota_caps')->insert([
                'brand' => $brand,
                'prize_name' => $prize,
                'cap_units' => $cap,
                'used_units' => 0,
                'program_start' => now()->toDateString(),
                'program_end' => now()->addDays(60)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Bikin sesi yang SUDAH selesai dimainkan + klaim, supaya dashboard CS &
     * Finance langsung ada isinya. Termasuk 1 klaim biasa (CS) & 1 klaim besar
     * (menunggu Finance).
     */
    private function seedSessionsAndClaims(): void
    {
        foreach ($this->brands() as $brand) {
            $this->makeFinishedSession(
                brand: $brand,
                phone: $brand === 'amura' ? '6281111111111' : '6282222222222',
                name: $brand === 'amura' ? 'Budi Santoso' : 'Sari Dewi',
                awb: 'SMOKE-CLAIM-'.strtoupper($brand),
                takenTier: 'poin',
                claimed: true,
                claimStatus: 'belum_redeem',
                bigPrize: false,
            );

            $this->makeFinishedSession(
                brand: $brand,
                phone: $brand === 'amura' ? '6281111111112' : '6282222222223',
                name: $brand === 'amura' ? 'Rina Amura' : 'Tono Reglow',
                awb: 'SMOKE-BIG-'.strtoupper($brand),
                takenTier: 'hadiah_besar',
                claimed: true,
                claimStatus: 'perlu_finance',
                bigPrize: true,
            );
        }
    }

    private function makeFinishedSession(
        string $brand,
        string $phone,
        string $name,
        string $awb,
        string $takenTier,
        bool $claimed,
        ?string $claimStatus,
        bool $bigPrize,
    ): void {
        $existing = GameSession::withoutGlobalScopes()
            ->where('brand', $brand)
            ->where('awb', $awb)
            ->first();
        if ($existing) {
            return;
        }

        $customer = Customer::withoutGlobalScopes()->updateOrCreate(
            ['phone' => $phone, 'brand' => $brand],
            ['name' => $name, 'last_seen_at' => now()],
        );

        $session = GameSession::withoutGlobalScopes()->create([
            'customer_id' => $customer->id,
            'brand' => $brand,
            'session_token' => bin2hex(random_bytes(32)),
            'chance_total' => 2,
            'chance_used' => 1,
            'tier_config' => TierConfig::default(),
            'awb' => $awb,
            'gate_source' => 'stub',
            'expires_at' => now()->addDays(30),
        ]);

        // 24 slot: kebanyakan poin/voucher, 1 slot hadiah_besar kalau bigPrize.
        // Slot 1..3 = yang dipilih/dibuka (konsisten dengan alur nyata: ambil 1 dari 3).
        $tiers = array_fill(0, 24, 'poin');
        for ($i = 8; $i < 16; $i++) {
            $tiers[$i] = 'voucher';
        }
        $tiers[20] = 'merch';

        $takenSlot = 1;
        if ($bigPrize) {
            // Jadikan slot yang DIPILIH (slot 1) sebagai hadiah besar.
            $tiers[0] = 'hadiah_besar';
            $takenSlot = 1;
        } elseif ($takenTier === 'voucher') {
            $tiers[1] = 'voucher';
            $takenSlot = 2;
        }

        $picked = [1, 2, 3];

        $prizes = [];
        foreach (range(1, 24) as $slot) {
            $prizes[$slot] = GamePrize::withoutGlobalScopes()->create([
                'game_session_id' => $session->id,
                'slot' => $slot,
                'tier' => $tiers[$slot - 1],
                'picked' => in_array($slot, $picked, true),
                'opened' => in_array($slot, $picked, true),
                'taken' => false,
            ]);
        }

        // Sama seperti alur take nyata: slot yang diambil taken, dua lainnya
        // tetap terbuka tapi tidak lagi "dipilih", supaya sesi bisa lanjut ronde.
        foreach ($prizes as $slot => $prize) {
            $prize->update([
                'taken' => $slot === $takenSlot,
                'picked' => $slot === $takenSlot,
            ]);
        }

        if ($bigPrize) {
            $this->awardSeededBigPrize($brand, $prizes[$takenSlot]);
        }

        if (! $claimed) {
            return;
        }

        $prizeTaken = $prizes[$takenSlot];
        $expiryDays = $takenTier === 'hadiah_besar' ? 30 : 14;

        DB::table('claims')->updateOrInsert(
            ['claim_code' => 'SMOKE-'.$brand.'-'.($bigPrize ? 'BIG' : 'STD')],
            [
                'game_session_id' => $session->id,
                'game_prize_id' => $prizeTaken->id,
                'status' => $claimStatus,
                'handled_by' => null,
                'finance_by' => null,
                'claimed_at' => now(),
                'expires_at' => now()->addDays($expiryDays),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /** Hubungkan hadiah besar seed ke katalog dan potong kuotanya, seperti pemenang nyata. */
    private function awardSeededBigPrize(string $brand, GamePrize $prize): void
    {
        $catalog = PrizeCatalog::withoutGlobalScopes()
            ->where('brand', $brand)
            ->where('tier', 'hadiah_besar')
            ->where('is_capped', true)
            ->orderBy('id')
            ->first();
        if (! $catalog) {
            return;
        }

        $prize->update(['prize_catalog_id' => $catalog->id]);

        DB::table('quota_caps')
            ->where('brand', $brand)
            ->where('prize_name', $catalog->name)
            ->where('used_units', '<', DB::raw('cap_units'))
            ->increment('used_units');
    }
}
