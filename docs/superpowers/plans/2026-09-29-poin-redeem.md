# Saldo Poin, Hadiahku & Redeem via WhatsApp Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pelanggan bisa melihat saldo poin, menukar poin dengan hadiah katalog bulanan, melihat Hadiahku, dan me-redeem hadiah lewat WhatsApp CS yang diverifikasi dari dashboard.

**Architecture:** Logika bisnis ada di `packages/core` (dompet poin berbasis lot, mesin status klaim, katalog bulanan, pemberi hadiah Gatcha). App `apps/amura` menambah area pelanggan (cookie perangkat 30 hari) dan merombak dashboard CS/Finance/Game Admin. Di akhir, backend bersama disinkronkan ke `apps/reglow` supaya kedua app tetap hijau (UI pelanggan Reglow belum termasuk).

**Tech Stack:** Laravel 12, PHP 8.5, PostgreSQL 18, Blade + CSS/JS polos (tanpa build), PHPUnit, Playwright (uji jalan manual).

**Spec:** `docs/superpowers/specs/2026-09-29-poin-redeem-design.md`

## Global Constraints

- Bahasa antarmuka Indonesia. Tanpa em dash (`—`) di teks UI yang ditulis (aturan antislop R-02).
- Kedua app berbagi satu DB dev (`loyalty_new`) dan satu DB test (`loyalty_new_test`). Migrasi harus **idempoten** (cek `hasTable`/`hasColumn`) karena dijalankan dari dua app.
- Migrasi disalin per app. Task 1–13 hanya menjalankan suite **core + Amura**. Suite Reglow disinkronkan dan harus hijau di Task 14.
- Poin: **200 per kado poin**, **minimal tukar 500**, **berlaku 12 bulan** (bisa diubah Game Admin, disimpan di `settings`).
- Batas redeem: hadiah biasa **14 hari**, hadiah utama **30 hari**, hadiah katalog **14 hari**, dihitung sejak hadiah didapat. Tidak diperpanjang saat ditolak.
- Status klaim: `belum_redeem`, `diajukan`, `perlu_finance`, `diproses`, `dikirim`, `selesai`, `hangus`. Yang bisa hangus: `belum_redeem`, `diajukan`, `perlu_finance`.
- Cookie perangkat `loyalty_customer`, 30 hari, berisi `id` + `brand`, dienkripsi Laravel.
- Nomor WA CS dari `.env` `CS_WHATSAPP`. Kosong: tombol WA disembunyikan, tampil "Nomor CS belum diatur".
- Isi kado yang belum dibuka tidak pernah dikirim ke browser (`test_board_tidak_bocorkan_tier` tetap lulus).
- Perintah test: core `cd packages/core && vendor/bin/phpunit`, Amura `cd apps/amura && php artisan test`, Reglow `cd apps/reglow && php artisan test`. Jalankan berurutan (berbagi DB test).
- Commit diakhiri baris:
  ```
  Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf
  ```

## Review Focus

1. **Cookie basi** (customer dihapus, brand lain, JSON rusak): pelanggan diarahkan ke `/play` tanpa loop redirect dan tanpa error 500. Test di Task 6.
2. **Nomor pengirim WA diketik dengan format lain** (`+62 812-3456-7890`, spasi, awalan 62/0): tetap dianggap cocok. Test di Task 3.
3. **Item katalog dinonaktifkan atau stok habis setelah halaman dimuat**: tukar ditolak dengan pesan jelas, poin tidak terpotong. Test di Task 5.
4. **Bulan berganti tanpa katalog baru**: katalog bulan lalu tetap tampil dan bisa ditukar. Test di Task 5.
5. **Tukar kedua kali saat saldo sudah tidak cukup** (double submit): klaim kedua tidak terbit dan saldo tidak negatif. Test di Task 5.

---

## File Structure

**Core (`packages/core/src`)**
- `Models/Claim.php` (ubah): kolom baru, hook pengisi brand/customer, relasi, helper `daysLeft()`, `isBigPrize()`.
- `Models/Customer.php` (ubah): helper `maskedPhone()`.
- `Models/ClaimEvent.php`, `Models/PointLot.php`, `Models/PointMovement.php`, `Models/RewardCatalog.php`, `Models/RewardCatalogItem.php` (baru).
- `Points/LoyaltySettings.php`, `Points/PointWallet.php`, `Points/InsufficientPointsException.php` (baru): dompet poin.
- `Prizes/ClaimFlow.php`, `Prizes/ClaimTransitionException.php` (baru): mesin status klaim + label.
- `Prizes/ClaimService.php` (ubah): pembuatan klaim, nama hadiah, expire.
- `Prizes/PrizeAwarder.php` (baru): kado Gatcha jadi poin atau klaim.
- `Prizes/RewardCatalogService.php`, `Prizes/CatalogUnavailableException.php` (baru): katalog bulanan + tukar.
- `Gacha/SessionService.php` (ubah): simpan nama pelanggan, undi item voucher/merch.
- `Gate/OrderStatus.php`, `Gate/GateResult.php`, `Gate/MarkomApiOrderGate.php`, `Gate/FairtrackSqliteOrderStatusSource.php`, `Gate/MarkomHttpOrderStatusSource.php`, `Gate/CachedOrderStatusSource.php` (ubah): bawa `customerName`.
- `Console/ExpirePointsCommand.php` (baru), `CoreServiceProvider.php` (ubah).
- `Seeding/SmokeSeeder.php` (ubah).
- `config/loyalty.php` (ubah): `cs_whatsapp`.

**App Amura (`apps/amura`)**
- `database/migrations/2026_09_29_000001_create_points_and_redeem.php` (baru).
- `app/Http/Middleware/RememberedCustomer.php` (baru), `bootstrap/app.php` (ubah).
- `app/Support/WhatsAppLink.php` (baru).
- `app/Http/Controllers/PlayController.php` (ubah).
- `app/Http/Controllers/Customer/HomeController.php`, `RewardController.php`, `CatalogController.php` (baru).
- `app/Http/Controllers/Admin/CsClaimController.php` (tulis ulang), `ShippingController.php` (baru), `FinanceController.php` (ubah), `RewardCatalogController.php` (baru).
- `app/Providers/AppServiceProvider.php` (ubah): locale Carbon `id`.
- `routes/web.php`, `routes/console.php` (ubah).
- `resources/views/customer/home.blade.php`, `rewards.blade.php`, `reward.blade.php`, `catalog.blade.php`, `partials/claim-row.blade.php` (baru).
- `resources/views/layouts/admin.blade.php` (tulis ulang), `admin/claims.blade.php` (tulis ulang), `admin/claim.blade.php`, `admin/shipping.blade.php`, `admin/catalog.blade.php` (baru), `admin/finance.blade.php` (tulis ulang).
- `resources/views/play/board.blade.php` (ubah), `public/js/gatcha.js` (ubah), `public/css/play.css` (ubah).
- `.env.example` (ubah).
- Test baru: `RedeemSchemaTest`, `PointWalletTest`, `ClaimFlowTest`, `RewardCatalogServiceTest`, `CustomerAreaTest`, `ShippingTest`, `RewardCatalogAdminTest`. Test diubah: `CsClaimTest`, `FinanceTest`, `ExpireClaimsTest`, `PlayFlowTest`, `SpecVerificationTest`, `BugfixRegressionTest`, `SmokeSeederTest`, `SessionServiceTest`.

**Repo**
- `scripts/sync-reglow.sh` (baru): salin backend bersama + test (brand ditukar) ke Reglow.
- `docs/DEPLOY.md` (ubah).

---

### Task 1: Skema data, model, dan koreksi spec

**Files:**
- Create: `apps/amura/database/migrations/2026_09_29_000001_create_points_and_redeem.php`
- Create: `packages/core/src/Models/ClaimEvent.php`, `PointLot.php`, `PointMovement.php`, `RewardCatalog.php`, `RewardCatalogItem.php`
- Modify: `packages/core/src/Models/Claim.php`, `packages/core/src/Models/Customer.php`
- Modify: `docs/superpowers/specs/2026-09-29-poin-redeem-design.md`
- Test: `apps/amura/tests/Feature/RedeemSchemaTest.php`

**Interfaces:**
- Produces: tabel `claim_events`, `point_lots`, `point_movements`, `reward_catalogs`, `reward_catalog_items`; kolom baru `claims`; model `ClaimEvent`, `PointLot`, `PointMovement`, `RewardCatalog` (BrandScope), `RewardCatalogItem` (`inStock(): bool`, `remaining(): ?int`); `Claim::customer()`, `Claim::events()`, `Claim::catalogItem()`, `Claim::daysLeft(): int`, `Claim::isBigPrize(): bool`; `Customer::maskedPhone(): string`.

- [ ] **Step 1: Tulis test skema yang gagal**

`apps/amura/tests/Feature/RedeemSchemaTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use Tests\TestCase;

final class RedeemSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_dan_kolom_baru_ada(): void
    {
        foreach (['claim_events', 'point_lots', 'point_movements', 'reward_catalogs', 'reward_catalog_items'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "tabel {$table} harus ada");
        }

        foreach (['brand', 'customer_id', 'source', 'prize_name', 'reward_catalog_item_id', 'points_spent',
            'redeem_requested_at', 'shipping_awb', 'rejected_reason'] as $column) {
            $this->assertTrue(Schema::hasColumn('claims', $column), "kolom claims.{$column} harus ada");
        }
    }

    public function test_klaim_gatcha_otomatis_mendapat_brand_dan_customer_dari_sesi(): void
    {
        $customer = Customer::create(['phone' => '081200000001', 'brand' => config('app.brand')]);
        $session = GameSession::create([
            'customer_id' => $customer->id,
            'brand' => config('app.brand'),
            'session_token' => bin2hex(random_bytes(8)),
            'tier_config' => [],
            'awb' => 'JX-SCHEMA',
            'expires_at' => now()->addDay(),
        ]);
        $prize = GamePrize::create(['game_session_id' => $session->id, 'slot' => 1, 'tier' => 'voucher']);

        $claim = Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'claim_code' => 'SCHEMA01',
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(14),
        ]);

        $this->assertSame(config('app.brand'), $claim->brand);
        $this->assertSame($customer->id, $claim->customer_id);
        $this->assertSame('gatcha', $claim->fresh()->source);
        $this->assertSame(14, $claim->daysLeft());
    }

    public function test_klaim_katalog_boleh_tanpa_sesi(): void
    {
        $customer = Customer::create(['phone' => '081200000002', 'brand' => config('app.brand')]);

        $claim = Claim::create([
            'brand' => config('app.brand'),
            'customer_id' => $customer->id,
            'source' => 'katalog',
            'prize_name' => 'Pouch Exclusive',
            'claim_code' => 'SCHEMA02',
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(14),
        ]);

        $this->assertNull($claim->game_session_id);
        $this->assertFalse($claim->isBigPrize());
        $this->assertSame('0812••••0002', $customer->maskedPhone());
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=RedeemSchemaTest`
Expected: FAIL (`tabel claim_events harus ada`).

- [ ] **Step 3: Tulis migrasi**

`apps/amura/database/migrations/2026_09_29_000001_create_points_and_redeem.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Saldo poin, katalog bulanan, dan alur redeem (spec 2026-09-29).
// Kedua app berbagi satu database, jadi setiap langkah idempoten.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('claims', 'brand')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->string('brand')->nullable()->index();
                $table->foreignId('customer_id')->nullable()->constrained('customers');
                $table->string('source')->default('gatcha');
                $table->string('prize_name')->nullable();
                $table->unsignedBigInteger('reward_catalog_item_id')->nullable();
                $table->integer('points_spent')->nullable();
                $table->timestamp('redeem_requested_at')->nullable();
                $table->string('shipping_awb')->nullable();
                $table->string('rejected_reason')->nullable();
            });

            Schema::table('claims', function (Blueprint $table) {
                $table->unsignedBigInteger('game_session_id')->nullable()->change();
                $table->unsignedBigInteger('game_prize_id')->nullable()->change();
                $table->string('status')->default('belum_redeem')->change();
            });

            DB::statement(<<<'SQL'
                update claims set brand = gs.brand, customer_id = gs.customer_id
                from game_sessions gs
                where claims.game_session_id = gs.id
            SQL);

            DB::statement(<<<'SQL'
                update claims set prize_name = coalesce(pc.name, case gp.tier
                    when 'hadiah_besar' then 'Hadiah Utama'
                    when 'voucher' then 'Voucher'
                    when 'merch' then 'Merch'
                    else 'Poin' end)
                from game_prizes gp
                left join prize_catalog pc on pc.id = gp.prize_catalog_id
                where claims.game_prize_id = gp.id
            SQL);

            DB::table('claims')->where('status', 'menunggu')->update(['status' => 'belum_redeem']);
        }

        if (! Schema::hasTable('claim_events')) {
            Schema::create('claim_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->string('actor');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('note')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('claim_id');
            });
        }

        if (! Schema::hasTable('point_lots')) {
            Schema::create('point_lots', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->foreignId('customer_id')->constrained('customers');
                $table->foreignId('game_prize_id')->nullable()->unique()->constrained('game_prizes');
                $table->integer('points');
                $table->integer('remaining');
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->index(['customer_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('point_movements')) {
            Schema::create('point_movements', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->foreignId('customer_id')->constrained('customers');
                $table->foreignId('point_lot_id')->constrained('point_lots');
                $table->foreignId('claim_id')->nullable()->constrained('claims');
                $table->string('type');
                $table->integer('points');
                $table->timestamp('created_at')->useCurrent();
                $table->index('customer_id');
                $table->index('claim_id');
            });
        }

        if (! Schema::hasTable('reward_catalogs')) {
            Schema::create('reward_catalogs', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->string('period', 7);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['brand', 'period']);
            });
        }

        if (! Schema::hasTable('reward_catalog_items')) {
            Schema::create('reward_catalog_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reward_catalog_id')->constrained('reward_catalogs')->cascadeOnDelete();
                $table->string('name');
                $table->integer('points_cost');
                $table->string('image_path')->nullable();
                $table->integer('stock')->nullable();
                $table->integer('stock_used')->default(0);
                $table->boolean('active')->default(true);
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_catalog_items');
        Schema::dropIfExists('reward_catalogs');
        Schema::dropIfExists('point_movements');
        Schema::dropIfExists('point_lots');
        Schema::dropIfExists('claim_events');

        if (Schema::hasColumn('claims', 'brand')) {
            DB::table('claims')->where('status', 'belum_redeem')->update(['status' => 'menunggu']);

            Schema::table('claims', function (Blueprint $table) {
                $table->dropConstrainedForeignId('customer_id');
                $table->dropColumn(['brand', 'source', 'prize_name', 'reward_catalog_item_id', 'points_spent',
                    'redeem_requested_at', 'shipping_awb', 'rejected_reason']);
                $table->string('status')->default('menunggu')->change();
            });
        }
    }
};
```

- [ ] **Step 4: Tulis model baru**

`packages/core/src/Models/ClaimEvent.php`:
```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu perpindahan status klaim: jejak audit sekaligus timeline di layar pelanggan. */
class ClaimEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];
}
```

`packages/core/src/Models/PointLot.php`:
```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu kelompok poin dengan tanggal hangus sendiri (satu kado poin = satu lot). */
class PointLot extends Model
{
    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime', 'points' => 'int', 'remaining' => 'int'];
}
```

`packages/core/src/Models/PointMovement.php`:
```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat poin per lot: earn, spend (negatif), refund, expire (negatif). */
class PointMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['points' => 'int', 'created_at' => 'datetime'];
}
```

`packages/core/src/Models/RewardCatalog.php`:
```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Katalog tukar poin satu bulan (period = YYYY-MM). */
class RewardCatalog extends Model
{
    protected $guarded = [];

    protected $casts = ['published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope(new BrandScope);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RewardCatalogItem::class)->orderBy('sort')->orderBy('id');
    }
}
```

`packages/core/src/Models/RewardCatalogItem.php`:
```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardCatalogItem extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'bool', 'points_cost' => 'int', 'stock' => 'int', 'stock_used' => 'int'];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(RewardCatalog::class, 'reward_catalog_id');
    }

    /** Stok kosong berarti tanpa batas. */
    public function inStock(): bool
    {
        return $this->stock === null || $this->stock_used < $this->stock;
    }

    public function remaining(): ?int
    {
        return $this->stock === null ? null : max(0, $this->stock - $this->stock_used);
    }
}
```

- [ ] **Step 5: Ubah model Claim dan Customer**

`packages/core/src/Models/Claim.php` (ganti seluruh isi):
```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    protected $guarded = [];

    protected $casts = [
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'redeem_requested_at' => 'datetime',
        'points_spent' => 'int',
    ];

    protected static function booted(): void
    {
        // Klaim Gatcha yang dibuat tanpa brand/pelanggan mengambilnya dari sesi.
        static::creating(function (Claim $claim) {
            if ($claim->game_session_id && (! $claim->brand || ! $claim->customer_id)) {
                $session = GameSession::withoutGlobalScopes()->find($claim->game_session_id);
                $claim->brand = $claim->brand ?: $session?->brand;
                $claim->customer_id = $claim->customer_id ?: $session?->customer_id;
            }
        });
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function gamePrize(): BelongsTo
    {
        return $this->belongsTo(GamePrize::class, 'game_prize_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(RewardCatalogItem::class, 'reward_catalog_item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClaimEvent::class)->orderBy('id');
    }

    public function isBigPrize(): bool
    {
        return $this->gamePrize?->tier === 'hadiah_besar';
    }

    /** Sisa hari sebelum batas redeem, dibulatkan ke atas, minimal 0. */
    public function daysLeft(): int
    {
        if (! $this->expires_at) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInSeconds($this->expires_at, false) / 86400));
    }
}
```

Tambahkan method ini ke `packages/core/src/Models/Customer.php` (setelah method `sessions()`):
```php
    /** Nomor HP disamarkan: 4 digit awal + •••• + 4 digit akhir. */
    public function maskedPhone(): string
    {
        $phone = (string) $this->phone;

        return strlen($phone) <= 8 ? $phone : substr($phone, 0, 4).'••••'.substr($phone, -4);
    }
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `cd apps/amura && php artisan test --filter=RedeemSchemaTest`
Expected: PASS (3 test).

- [ ] **Step 7: Koreksi spec**

Di `docs/superpowers/specs/2026-09-29-poin-redeem-design.md`:
- Tabel §3, ganti baris terakhir `| \`belum_redeem\`, \`diajukan\` | \`hangus\` | sistem / CS | \`expires_at\` lewat |` menjadi `| \`belum_redeem\`, \`diajukan\`, \`perlu_finance\` | \`hangus\` | sistem | \`expires_at\` lewat (Finance tidak bisa menyetujui klaim lewat batas, jadi \`perlu_finance\` juga harus bisa hangus) |`.
- §9 `point_lots`, ganti `\`game_prize_id\` (unique)` menjadi `\`game_prize_id\` (unique, boleh kosong untuk saldo demo dari seeder)`.
- §12, ganti `**Unit (\`packages/core\`):** \`PointWallet\`` menjadi `**Feature (Amura, butuh DB):** \`PointWallet\``.

- [ ] **Step 8: Jalankan migrasi di DB dev dan cek backfill**

Run:
```bash
cd apps/amura && php artisan migrate --force
php artisan tinker --execute='echo json_encode(DB::table("claims")->select("claim_code","brand","customer_id","status","prize_name")->limit(5)->get());'
```
Expected: setiap klaim punya `brand`, `customer_id`, `prize_name`, dan tidak ada status `menunggu`.

- [ ] **Step 9: Jalankan suite Amura penuh**

Run: `cd apps/amura && php artisan test`
Expected: semua lulus. `ClaimService::create()` masih menulis status `menunggu` secara eksplisit sampai Task 3, jadi test lama belum terpengaruh.

- [ ] **Step 10: Commit**

```bash
git add apps/amura/database/migrations/2026_09_29_000001_create_points_and_redeem.php packages/core/src/Models apps/amura/tests/Feature/RedeemSchemaTest.php docs/superpowers/specs/2026-09-29-poin-redeem-design.md
git commit -m "feat(core): skema poin, katalog bulanan, dan riwayat status klaim

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 2: Pengaturan poin dan dompet poin

**Files:**
- Create: `packages/core/src/Points/LoyaltySettings.php`, `packages/core/src/Points/PointWallet.php`, `packages/core/src/Points/InsufficientPointsException.php`
- Test: `apps/amura/tests/Feature/PointWalletTest.php`

**Interfaces:**
- Consumes: `PointLot`, `PointMovement`, `Claim`, `Customer`, `GamePrize` (Task 1).
- Produces:
  - `LoyaltySettings::current(?string $brand = null): array{per_gift:int,min_redeem:int,validity_months:int}`, `LoyaltySettings::store(array $values, ?string $brand = null): void`, `LoyaltySettings::DEFAULTS`.
  - `new PointWallet(Customer $customer)`, `balance(): int`, `nextExpiring(): ?array{points:int,expires_at:\Carbon\CarbonInterface}`, `credit(GamePrize $prize, int $points, int $validityMonths): PointLot`, `spend(Claim $claim, int $points, int $minimum): void` (lempar `InsufficientPointsException`), `refund(Claim $claim): int`, `static expireDue(string $brand, ?\DateTimeInterface $now = null): int`, `grant(int $points, int $validityMonths): PointLot` (tanpa kado, untuk seeder).
  - `InsufficientPointsException` dengan properti publik `int $balance`, `int $required`.

- [ ] **Step 1: Tulis test yang gagal**

`apps/amura/tests/Feature/PointWalletTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PointLot;
use LoyaltyNew\Core\Models\PointMovement;
use LoyaltyNew\Core\Points\InsufficientPointsException;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;
use Tests\TestCase;

final class PointWalletTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private GameSession $session;
    private int $slot = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create(['phone' => '081200000010', 'brand' => config('app.brand')]);
        $this->session = GameSession::create([
            'customer_id' => $this->customer->id,
            'brand' => config('app.brand'),
            'session_token' => bin2hex(random_bytes(8)),
            'tier_config' => [],
            'awb' => 'JX-WALLET',
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function wallet(): PointWallet
    {
        return new PointWallet($this->customer);
    }

    private function lot(int $points = 200, int $months = 12): PointLot
    {
        $prize = GamePrize::create([
            'game_session_id' => $this->session->id,
            'slot' => ++$this->slot,
            'tier' => 'poin',
            'taken' => true,
            'opened' => true,
        ]);

        return $this->wallet()->credit($prize, $points, $months);
    }

    private function catalogClaim(int $cost): Claim
    {
        return Claim::create([
            'brand' => config('app.brand'),
            'customer_id' => $this->customer->id,
            'source' => 'katalog',
            'prize_name' => 'Hadiah uji',
            'points_spent' => $cost,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(14),
        ]);
    }

    public function test_pengaturan_default_200_500_12(): void
    {
        $this->assertSame(['per_gift' => 200, 'min_redeem' => 500, 'validity_months' => 12], LoyaltySettings::current());

        LoyaltySettings::store(['per_gift' => 250, 'min_redeem' => 600, 'validity_months' => 6]);
        $this->assertSame(['per_gift' => 250, 'min_redeem' => 600, 'validity_months' => 6], LoyaltySettings::current());
        $this->assertSame(LoyaltySettings::DEFAULTS, LoyaltySettings::current('brand-lain'));
    }

    public function test_credit_menambah_saldo_dan_berlaku_12_bulan(): void
    {
        $lot = $this->lot();

        $this->assertSame(200, $this->wallet()->balance());
        $this->assertEqualsWithDelta(now()->addMonths(12)->timestamp, $lot->expires_at->timestamp, 5);
        $this->assertSame(1, PointMovement::where('type', 'earn')->where('points', 200)->count());
    }

    public function test_credit_dua_kali_untuk_kado_sama_tidak_dobel(): void
    {
        $lot = $this->lot();
        $prize = GamePrize::findOrFail($lot->game_prize_id);

        $again = $this->wallet()->credit($prize, 200, 12);

        $this->assertSame($lot->id, $again->id);
        $this->assertSame(200, $this->wallet()->balance());
        $this->assertSame(1, PointMovement::where('type', 'earn')->count());
    }

    public function test_spend_memakai_lot_paling_cepat_hangus_dulu(): void
    {
        $late = $this->lot(200, 12);
        $early = $this->lot(200, 2);
        $claim = $this->catalogClaim(300);

        $this->wallet()->spend($claim, 300, 0);

        $this->assertSame(0, $early->fresh()->remaining);
        $this->assertSame(100, $late->fresh()->remaining);
        $this->assertSame(100, $this->wallet()->balance());
        $this->assertSame(-300, (int) PointMovement::where('claim_id', $claim->id)->where('type', 'spend')->sum('points'));
    }

    public function test_spend_ditolak_kalau_saldo_di_bawah_minimal(): void
    {
        $this->lot(200);
        $this->lot(200);
        $claim = $this->catalogClaim(300);

        try {
            $this->wallet()->spend($claim, 300, 500);
            $this->fail('harus melempar InsufficientPointsException');
        } catch (InsufficientPointsException $e) {
            $this->assertSame(400, $e->balance);
            $this->assertSame(500, $e->required);
        }

        $this->assertSame(400, $this->wallet()->balance());
    }

    public function test_spend_ditolak_kalau_saldo_kurang_dari_harga(): void
    {
        $this->lot(200);
        $this->lot(200);
        $this->lot(200);

        $this->expectException(InsufficientPointsException::class);
        $this->wallet()->spend($this->catalogClaim(1000), 1000, 500);
    }

    public function test_refund_kembali_ke_lot_asal_hanya_sekali(): void
    {
        $a = $this->lot(200, 2);
        $b = $this->lot(200, 12);
        $claim = $this->catalogClaim(300);
        $this->wallet()->spend($claim, 300, 0);

        $this->assertSame(300, $this->wallet()->refund($claim));
        $this->assertSame(0, $this->wallet()->refund($claim));

        $this->assertSame(200, $a->fresh()->remaining);
        $this->assertSame(200, $b->fresh()->remaining);
        $this->assertSame(400, $this->wallet()->balance());
    }

    public function test_refund_ke_lot_kedaluwarsa_tidak_menambah_saldo(): void
    {
        $a = $this->lot(200, 2);
        $b = $this->lot(200, 12);
        $claim = $this->catalogClaim(300);
        $this->wallet()->spend($claim, 300, 0);
        $a->update(['expires_at' => now()->subDay()]);

        $this->wallet()->refund($claim);

        $this->assertSame(0, $a->fresh()->remaining);
        $this->assertSame(200, $b->fresh()->remaining);
        $this->assertSame(200, $this->wallet()->balance());
        $this->assertSame(1, PointMovement::where('point_lot_id', $a->id)->where('type', 'expire')->count());
    }

    public function test_expire_due_mengosongkan_lot_lewat_masa_berlaku(): void
    {
        $old = $this->lot(200, 12);
        $this->lot(200, 12);
        $old->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(200, $this->wallet()->balance());
        $this->assertSame(200, PointWallet::expireDue(config('app.brand')));
        $this->assertSame(0, PointWallet::expireDue(config('app.brand')));
        $this->assertSame(0, $old->fresh()->remaining);
    }

    public function test_next_expiring_menunjukkan_lot_terdekat(): void
    {
        $this->assertNull($this->wallet()->nextExpiring());

        $this->lot(200, 12);
        $early = $this->lot(200, 3);

        $next = $this->wallet()->nextExpiring();
        $this->assertSame(200, $next['points']);
        $this->assertSame($early->expires_at->timestamp, $next['expires_at']->timestamp);
    }

    public function test_grant_tanpa_kado_untuk_seeder(): void
    {
        $this->wallet()->grant(600, 12);

        $this->assertSame(600, $this->wallet()->balance());
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=PointWalletTest`
Expected: FAIL (`Class "LoyaltyNew\Core\Points\PointWallet" not found`).

- [ ] **Step 3: Tulis implementasi**

`packages/core/src/Points/InsufficientPointsException.php`:
```php
<?php

namespace LoyaltyNew\Core\Points;

final class InsufficientPointsException extends \RuntimeException
{
    public function __construct(public readonly int $balance, public readonly int $required)
    {
        parent::__construct('Poinmu kurang '.number_format($required - $balance, 0, ',', '.').' lagi.');
    }
}
```

`packages/core/src/Points/LoyaltySettings.php`:
```php
<?php

namespace LoyaltyNew\Core\Points;

use Illuminate\Support\Facades\DB;

/** Aturan poin per brand (spec §2 #2), bisa diubah Game Admin lewat tabel settings. */
final class LoyaltySettings
{
    public const DEFAULTS = ['per_gift' => 200, 'min_redeem' => 500, 'validity_months' => 12];

    private static function key(?string $brand): string
    {
        return 'points:'.($brand ?? (string) config('app.brand'));
    }

    /** @return array{per_gift:int,min_redeem:int,validity_months:int} */
    public static function current(?string $brand = null): array
    {
        $raw = DB::table('settings')->where('key', self::key($brand))->value('value');
        $stored = $raw ? json_decode($raw, true) : [];
        $values = self::DEFAULTS;

        foreach (array_keys(self::DEFAULTS) as $key) {
            if (is_array($stored) && is_int($stored[$key] ?? null) && $stored[$key] > 0) {
                $values[$key] = $stored[$key];
            }
        }

        return $values;
    }

    public static function store(array $values, ?string $brand = null): void
    {
        $clean = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $clean[$key] = (int) $values[$key];
        }

        DB::table('settings')->updateOrInsert(
            ['key' => self::key($brand)],
            ['value' => json_encode($clean), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
```

`packages/core/src/Points/PointWallet.php`:
```php
<?php

namespace LoyaltyNew\Core\Points;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\PointLot;
use LoyaltyNew\Core\Models\PointMovement;

/**
 * Dompet poin berbasis lot (spec §4). Satu kado poin = satu lot dengan tanggal
 * hangus sendiri. Tukar memotong lot yang paling cepat hangus lebih dulu, dan
 * pengembalian mengisi lot asal supaya masa berlaku tidak berubah.
 */
final class PointWallet
{
    public function __construct(private Customer $customer)
    {
    }

    public function balance(): int
    {
        return (int) PointLot::where('customer_id', $this->customer->id)
            ->where('expires_at', '>', now())
            ->sum('remaining');
    }

    /** @return array{points:int,expires_at:\Carbon\CarbonInterface}|null */
    public function nextExpiring(): ?array
    {
        $lot = PointLot::where('customer_id', $this->customer->id)
            ->where('expires_at', '>', now())
            ->where('remaining', '>', 0)
            ->orderBy('expires_at')
            ->first();

        return $lot ? ['points' => $lot->remaining, 'expires_at' => $lot->expires_at] : null;
    }

    /** Poin dari kado Gatcha. Aman dipanggil ulang: satu kado hanya membuat satu lot. */
    public function credit(GamePrize $prize, int $points, int $validityMonths): PointLot
    {
        $existing = PointLot::where('game_prize_id', $prize->id)->first();
        if ($existing) {
            return $existing;
        }

        return $this->newLot($points, $validityMonths, $prize->id);
    }

    /** Saldo tanpa kado (dipakai seeder demo). */
    public function grant(int $points, int $validityMonths): PointLot
    {
        return $this->newLot($points, $validityMonths, null);
    }

    /**
     * Potong poin untuk klaim katalog. Syarat saldo ≥ max(minimal tukar, harga).
     * Dipanggil di dalam transaksi pemanggil supaya klaim ikut batal kalau gagal.
     */
    public function spend(Claim $claim, int $points, int $minimum): void
    {
        $lots = PointLot::where('customer_id', $this->customer->id)
            ->where('expires_at', '>', now())
            ->where('remaining', '>', 0)
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $balance = (int) $lots->sum('remaining');
        $required = max($minimum, $points);
        if ($balance < $required) {
            throw new InsufficientPointsException($balance, $required);
        }

        $left = $points;
        foreach ($lots as $lot) {
            if ($left <= 0) {
                break;
            }

            $take = min($lot->remaining, $left);
            $lot->decrement('remaining', $take);
            $this->move($lot, 'spend', -$take, $claim->id);
            $left -= $take;
        }
    }

    /** Kembalikan poin klaim katalog ke lot asalnya. Hanya sekali per klaim. */
    public function refund(Claim $claim): int
    {
        return DB::transaction(function () use ($claim) {
            if (PointMovement::where('claim_id', $claim->id)->where('type', 'refund')->exists()) {
                return 0;
            }

            $total = 0;
            $spends = PointMovement::where('claim_id', $claim->id)->where('type', 'spend')->get();

            foreach ($spends as $spend) {
                $lot = PointLot::whereKey($spend->point_lot_id)->lockForUpdate()->firstOrFail();
                $amount = -$spend->points;

                $this->move($lot, 'refund', $amount, $claim->id);

                // Lot yang sudah lewat masa berlaku tetap hangus: poin yang kembali langsung dicatat hangus.
                if ($lot->expires_at->isPast()) {
                    $this->move($lot, 'expire', -$amount, $claim->id);
                } else {
                    $lot->increment('remaining', $amount);
                }

                $total += $amount;
            }

            return $total;
        });
    }

    /** Kosongkan lot yang lewat masa berlaku. Return jumlah poin yang hangus. */
    public static function expireDue(string $brand, ?\DateTimeInterface $now = null): int
    {
        return DB::transaction(function () use ($brand, $now) {
            $lots = PointLot::where('brand', $brand)
                ->where('expires_at', '<=', $now ?? now())
                ->where('remaining', '>', 0)
                ->lockForUpdate()
                ->get();

            $total = 0;
            foreach ($lots as $lot) {
                PointMovement::create([
                    'brand' => $lot->brand,
                    'customer_id' => $lot->customer_id,
                    'point_lot_id' => $lot->id,
                    'type' => 'expire',
                    'points' => -$lot->remaining,
                ]);
                $total += $lot->remaining;
                $lot->update(['remaining' => 0]);
            }

            return $total;
        });
    }

    private function newLot(int $points, int $validityMonths, ?int $prizeId): PointLot
    {
        return DB::transaction(function () use ($points, $validityMonths, $prizeId) {
            $lot = PointLot::create([
                'brand' => $this->customer->brand,
                'customer_id' => $this->customer->id,
                'game_prize_id' => $prizeId,
                'points' => $points,
                'remaining' => $points,
                'expires_at' => now()->addMonths($validityMonths),
            ]);

            $this->move($lot, 'earn', $points, null);

            return $lot;
        });
    }

    private function move(PointLot $lot, string $type, int $points, ?int $claimId): void
    {
        PointMovement::create([
            'brand' => $lot->brand,
            'customer_id' => $lot->customer_id,
            'point_lot_id' => $lot->id,
            'claim_id' => $claimId,
            'type' => $type,
            'points' => $points,
        ]);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `cd apps/amura && php artisan test --filter=PointWalletTest`
Expected: PASS (10 test).

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Points apps/amura/tests/Feature/PointWalletTest.php
git commit -m "feat(core): dompet poin berbasis lot dan pengaturan poin per brand

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 3: Mesin status klaim, pembuatan klaim, dan job hangus

**Files:**
- Create: `packages/core/src/Prizes/ClaimFlow.php`, `packages/core/src/Prizes/ClaimTransitionException.php`, `packages/core/src/Console/ExpirePointsCommand.php`
- Modify: `packages/core/src/Prizes/ClaimService.php`, `packages/core/src/CoreServiceProvider.php`, `packages/core/src/Seeding/SmokeSeeder.php:161,171`, `apps/amura/routes/console.php`
- Modify (test): `apps/amura/tests/Feature/CsClaimTest.php`, `ExpireClaimsTest.php`, `BugfixRegressionTest.php`, `PlayFlowTest.php`, `SpecVerificationTest.php`, `SmokeSeederTest.php`
- Test: `apps/amura/tests/Feature/ClaimFlowTest.php`

**Interfaces:**
- Consumes: `PointWallet::refund()` (Task 2), `Claim`, `ClaimEvent`, `RewardCatalogItem` (Task 1), `BigPrizeQuota`, `AwbMatcher::normalizePhone()`.
- Produces:
  - `ClaimFlow::STATUSES`, `ClaimFlow::EXPIRABLE = ['belum_redeem','diajukan','perlu_finance']`, `ClaimFlow::IN_PROGRESS = ['diajukan','perlu_finance','diproses','dikirim']`, `ClaimFlow::DONE = ['selesai','hangus']`.
  - `ClaimFlow::request(Claim, Customer): Claim`, `confirm(Claim, User, string $senderPhone, ?string $note = null): Claim`, `reject(Claim, User, string $senderPhone, ?string $note = null): Claim`, `approveFinance(Claim, User): Claim`, `ship(Claim, User, string $awb): Claim`, `deliver(Claim, User): Claim`, `expire(Claim, string $actor = 'system', ?User $user = null): Claim`, `phoneMatches(Claim, string $senderPhone): bool`, `label(string $status): string`, `eventLabel(?string $from, string $to): string`, `counts(string $brand): array<string,int>`.
  - `ClaimTransitionException` (pesan bahasa Indonesia).
  - `ClaimService::create(GameSession, GamePrize): Claim` (status `belum_redeem`, event pertama), `ClaimService::createFromCatalog(Customer, RewardCatalogItem): Claim`, `ClaimService::prizeName(GamePrize): string`, `ClaimService::expireOverdue(...)` memakai `ClaimFlow::expire`, `ClaimService::CATALOG_EXPIRY_DAYS = 14`.
  - Artisan `loyalty:expire-points`.

- [ ] **Step 1: Tulis test ClaimFlow yang gagal**

`apps/amura/tests/Feature/ClaimFlowTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\ClaimEvent;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;
use Tests\TestCase;

final class ClaimFlowTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function staff(string $role): User
    {
        return User::create([
            'name' => $role,
            'email' => $role.'@flow.id',
            'password' => 'rahasia',
            'role' => $role,
            'brand_scope' => $this->brand(),
        ]);
    }

    private function claim(string $tier = 'voucher', string $status = 'belum_redeem', string $phone = '081234567890'): Claim
    {
        $customer = Customer::firstOrCreate(['phone' => $phone, 'brand' => $this->brand()], ['name' => 'Sari']);
        $session = GameSession::create([
            'customer_id' => $customer->id,
            'brand' => $this->brand(),
            'session_token' => bin2hex(random_bytes(8)),
            'tier_config' => [],
            'awb' => 'JX'.random_int(10000, 99999),
            'expires_at' => now()->addDays(30),
        ]);
        $prize = GamePrize::create(['game_session_id' => $session->id, 'slot' => 1, 'tier' => $tier, 'taken' => true, 'opened' => true]);
        $claim = ClaimService::create($session, $prize);
        if ($status !== 'belum_redeem') {
            $claim->update(['status' => $status]);
        }

        return $claim->fresh();
    }

    public function test_request_mengubah_ke_diajukan_dan_mencatat_event(): void
    {
        $claim = $this->claim();

        ClaimFlow::request($claim, $claim->customer);

        $claim->refresh();
        $this->assertSame('diajukan', $claim->status);
        $this->assertNotNull($claim->redeem_requested_at);
        $this->assertSame(['belum_redeem', 'diajukan'], $claim->events->pluck('to_status')->all());
        $this->assertSame('customer', $claim->events->last()->actor);
    }

    public function test_request_klaim_milik_pelanggan_lain_ditolak(): void
    {
        $claim = $this->claim();
        $other = Customer::create(['phone' => '089900000000', 'brand' => $this->brand()]);

        $this->expectException(ClaimTransitionException::class);
        ClaimFlow::request($claim, $other);
    }

    public function test_request_lewat_batas_ditolak(): void
    {
        $claim = $this->claim();
        $claim->update(['expires_at' => now()->subMinute()]);

        $this->expectException(ClaimTransitionException::class);
        ClaimFlow::request($claim, $claim->customer);
    }

    public function test_confirm_nomor_cocok_walau_formatnya_beda(): void
    {
        $claim = $this->claim(status: 'diajukan');
        $cs = $this->staff('cs');

        ClaimFlow::confirm($claim, $cs, '+62 812-3456-7890', 'alamat Kendari dicek');

        $claim->refresh();
        $this->assertSame('diproses', $claim->status);
        $this->assertSame($cs->id, $claim->handled_by);
        $this->assertSame('alamat Kendari dicek', $claim->events->last()->note);
    }

    public function test_confirm_hadiah_utama_ke_perlu_finance(): void
    {
        $claim = $this->claim('hadiah_besar', 'diajukan');

        ClaimFlow::confirm($claim, $this->staff('cs'), '081234567890');

        $this->assertSame('perlu_finance', $claim->fresh()->status);
    }

    public function test_confirm_nomor_beda_ditolak_status_tetap(): void
    {
        $claim = $this->claim(status: 'diajukan');

        try {
            ClaimFlow::confirm($claim, $this->staff('cs'), '081111111111');
            $this->fail('harus ditolak');
        } catch (ClaimTransitionException $e) {
            $this->assertStringContainsString('tidak cocok', $e->getMessage());
        }

        $this->assertSame('diajukan', $claim->fresh()->status);
    }

    public function test_reject_kembali_ke_belum_redeem_tanpa_perpanjang_batas(): void
    {
        $claim = $this->claim(status: 'diajukan');
        $expires = $claim->expires_at->timestamp;

        ClaimFlow::reject($claim, $this->staff('cs'), '081111111111');

        $claim->refresh();
        $this->assertSame('belum_redeem', $claim->status);
        $this->assertSame('Nomor pengirim tidak cocok', $claim->rejected_reason);
        $this->assertSame($expires, $claim->expires_at->timestamp);
    }

    public function test_reject_nomor_sama_ditolak(): void
    {
        $claim = $this->claim(status: 'diajukan');

        $this->expectException(ClaimTransitionException::class);
        ClaimFlow::reject($claim, $this->staff('cs'), '0812 3456 7890');
    }

    public function test_approve_finance_ship_deliver_sampai_selesai(): void
    {
        $claim = $this->claim('hadiah_besar', 'perlu_finance');

        ClaimFlow::approveFinance($claim, $this->staff('finance'));
        $this->assertSame('diproses', $claim->fresh()->status);

        $cs = $this->staff('cs');
        ClaimFlow::ship($claim, $cs, 'JX-HADIAH-1');
        $this->assertSame('dikirim', $claim->fresh()->status);
        $this->assertSame('JX-HADIAH-1', $claim->fresh()->shipping_awb);

        ClaimFlow::deliver($claim, $cs);
        $this->assertSame('selesai', $claim->fresh()->status);
    }

    public function test_transisi_dari_status_salah_ditolak(): void
    {
        $claim = $this->claim();

        $this->expectException(ClaimTransitionException::class);
        ClaimFlow::ship($claim, $this->staff('cs'), 'JX-1');
    }

    public function test_expire_hadiah_katalog_mengembalikan_poin_dan_stok(): void
    {
        $customer = Customer::create(['phone' => '081200000099', 'brand' => $this->brand()]);
        $wallet = new PointWallet($customer);
        $wallet->grant(1000, 12);
        $catalog = RewardCatalog::create(['brand' => $this->brand(), 'period' => now()->format('Y-m'), 'published_at' => now()]);
        $item = $catalog->items()->create(['name' => 'Pouch Exclusive', 'points_cost' => 500, 'stock' => 5, 'stock_used' => 1]);

        $claim = ClaimService::createFromCatalog($customer, $item);
        $wallet->spend($claim, 500, 500);
        $this->assertSame(500, $wallet->balance());

        ClaimFlow::expire($claim);

        $this->assertSame('hangus', $claim->fresh()->status);
        $this->assertSame(1000, $wallet->balance());
        $this->assertSame(0, $item->fresh()->stock_used);
    }

    public function test_expire_hadiah_utama_mengembalikan_kuota(): void
    {
        $catalog = PrizeCatalog::create(['brand' => $this->brand(), 'name' => 'Emas uji', 'tier' => 'hadiah_besar', 'is_capped' => true, 'active' => true]);
        DB::table('quota_caps')->insert([
            'brand' => $this->brand(), 'prize_name' => 'Emas uji', 'cap_units' => 1, 'used_units' => 1,
            'program_start' => now(), 'program_end' => now()->addMonth(),
        ]);
        $claim = $this->claim('hadiah_besar', 'perlu_finance');
        $claim->gamePrize->update(['prize_catalog_id' => $catalog->id]);

        ClaimFlow::expire($claim->fresh());

        $this->assertSame(0, (int) DB::table('quota_caps')->where('prize_name', 'Emas uji')->value('used_units'));
    }

    public function test_label_dan_hitungan_antrian(): void
    {
        $this->claim(status: 'diajukan');
        $this->claim(status: 'diajukan', phone: '081200000077');
        $this->claim(status: 'diproses', phone: '081200000078');

        $this->assertSame('Menunggu CS', ClaimFlow::label('diajukan'));
        $this->assertSame('Hadiah didapat', ClaimFlow::eventLabel(null, 'belum_redeem'));
        $counts = ClaimFlow::counts($this->brand());
        $this->assertSame(2, $counts['diajukan']);
        $this->assertSame(1, $counts['diproses']);
        $this->assertSame(0, $counts['perlu_finance']);
        $this->assertSame(0, ClaimEvent::where('to_status', 'diproses')->count());
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=ClaimFlowTest`
Expected: FAIL (`Class "LoyaltyNew\Core\Prizes\ClaimFlow" not found`).

- [ ] **Step 3: Tulis ClaimTransitionException dan ClaimFlow**

`packages/core/src/Prizes/ClaimTransitionException.php`:
```php
<?php

namespace LoyaltyNew\Core\Prizes;

final class ClaimTransitionException extends \RuntimeException
{
}
```

`packages/core/src/Prizes/ClaimFlow.php`:
```php
<?php

namespace LoyaltyNew\Core\Prizes;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\ClaimEvent;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\RewardCatalogItem;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Points\PointWallet;

/**
 * Mesin status klaim (spec §3). Setiap transisi dikunci per klaim, dicek status
 * asalnya, lalu dicatat di claim_events.
 */
final class ClaimFlow
{
    public const STATUSES = ['belum_redeem', 'diajukan', 'perlu_finance', 'diproses', 'dikirim', 'selesai', 'hangus'];

    /** Status yang hangus kalau lewat batas redeem. */
    public const EXPIRABLE = ['belum_redeem', 'diajukan', 'perlu_finance'];

    public const IN_PROGRESS = ['diajukan', 'perlu_finance', 'diproses', 'dikirim'];

    public const DONE = ['selesai', 'hangus'];

    public const REJECT_REASON = 'Nomor pengirim tidak cocok';

    public static function request(Claim $claim, Customer $customer): Claim
    {
        return self::move($claim, ['belum_redeem'], 'diajukan', 'customer', null, null,
            function (Claim $locked) use ($customer) {
                if ((int) $locked->customer_id !== (int) $customer->id) {
                    throw new ClaimTransitionException('Klaim ini bukan milikmu.');
                }
                self::ensureNotExpired($locked);

                return ['redeem_requested_at' => now(), 'rejected_reason' => null];
            });
    }

    public static function confirm(Claim $claim, User $cs, string $senderPhone, ?string $note = null): Claim
    {
        $to = $claim->isBigPrize() ? 'perlu_finance' : 'diproses';

        return self::move($claim, ['diajukan'], $to, 'cs', $cs, $note,
            function (Claim $locked) use ($senderPhone, $cs) {
                self::ensureNotExpired($locked);
                if (! self::phoneMatches($locked, $senderPhone)) {
                    throw new ClaimTransitionException('Nomor pengirim tidak cocok dengan nomor pemenang. Tolak klaim ini.');
                }

                return ['handled_by' => $cs->id, 'claimed_at' => now()];
            });
    }

    public static function reject(Claim $claim, User $cs, string $senderPhone, ?string $note = null): Claim
    {
        return self::move($claim, ['diajukan'], 'belum_redeem', 'cs', $cs, $note,
            function (Claim $locked) use ($senderPhone, $cs) {
                if (self::phoneMatches($locked, $senderPhone)) {
                    throw new ClaimTransitionException('Nomor pengirim cocok dengan nomor pemenang. Konfirmasi klaim ini.');
                }

                return ['handled_by' => $cs->id, 'rejected_reason' => self::REJECT_REASON];
            });
    }

    public static function approveFinance(Claim $claim, User $finance): Claim
    {
        return self::move($claim, ['perlu_finance'], 'diproses', 'finance', $finance, null,
            function (Claim $locked) use ($finance) {
                self::ensureNotExpired($locked);

                return ['finance_by' => $finance->id, 'claimed_at' => now()];
            });
    }

    public static function ship(Claim $claim, User $cs, string $awb): Claim
    {
        return self::move($claim, ['diproses'], 'dikirim', 'cs', $cs, null,
            fn () => ['shipping_awb' => trim($awb), 'handled_by' => $cs->id]);
    }

    public static function deliver(Claim $claim, User $cs): Claim
    {
        return self::move($claim, ['dikirim'], 'selesai', 'cs', $cs, null, fn () => ['handled_by' => $cs->id]);
    }

    /** Hanguskan klaim. Hadiah utama mengembalikan kuota; hadiah katalog mengembalikan poin dan stok. */
    public static function expire(Claim $claim, string $actor = 'system', ?User $user = null): Claim
    {
        $expired = self::move($claim, self::EXPIRABLE, 'hangus', $actor, $user, null, fn () => [],
            function (Claim $locked) {
                ClaimService::releaseBigPrizeQuota($locked, (string) $locked->brand);

                if ($locked->source === 'katalog' && $locked->customer_id) {
                    $customer = Customer::withoutGlobalScopes()->findOrFail($locked->customer_id);
                    (new PointWallet($customer))->refund($locked);

                    if ($locked->reward_catalog_item_id) {
                        RewardCatalogItem::whereKey($locked->reward_catalog_item_id)
                            ->where('stock_used', '>', 0)
                            ->decrement('stock_used');
                    }
                }
            });

        return $expired;
    }

    public static function phoneMatches(Claim $claim, string $senderPhone): bool
    {
        $winner = Customer::withoutGlobalScopes()->whereKey($claim->customer_id)->value('phone');
        $sender = AwbMatcher::normalizePhone($senderPhone);

        return $winner !== null && $sender !== '' && AwbMatcher::normalizePhone($winner) === $sender;
    }

    /** Label status untuk pelanggan dan dashboard. */
    public static function label(string $status): string
    {
        return match ($status) {
            'belum_redeem' => 'Belum di-redeem',
            'diajukan' => 'Menunggu CS',
            'perlu_finance' => 'Diperiksa Finance',
            'diproses' => 'Dikemas',
            'dikirim' => 'Dikirim',
            'selesai' => 'Diterima',
            'hangus' => 'Hangus',
            default => $status,
        };
    }

    /** Label satu langkah di timeline status (layar 3d). */
    public static function eventLabel(?string $from, string $to): string
    {
        return match (true) {
            $to === 'belum_redeem' && $from === null => 'Hadiah didapat',
            $to === 'belum_redeem' => 'Ditolak CS: nomor pengirim tidak cocok',
            $to === 'diajukan' => 'Redeem diajukan',
            $to === 'perlu_finance' => 'Dikonfirmasi CS, diperiksa Finance',
            $to === 'diproses' && $from === 'perlu_finance' => 'Disetujui Finance, sedang dikemas',
            $to === 'diproses' => 'Dikonfirmasi CS, sedang dikemas',
            $to === 'dikirim' => 'Dalam pengiriman',
            $to === 'selesai' => 'Diterima',
            $to === 'hangus' => 'Hangus: lewat batas redeem',
            default => self::label($to),
        };
    }

    /** @return array{diajukan:int,perlu_finance:int,diproses:int,dikirim:int} */
    public static function counts(string $brand): array
    {
        $rows = Claim::where('brand', $brand)
            ->whereIn('status', ['diajukan', 'perlu_finance', 'diproses', 'dikirim'])
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'diajukan' => (int) ($rows['diajukan'] ?? 0),
            'perlu_finance' => (int) ($rows['perlu_finance'] ?? 0),
            'diproses' => (int) ($rows['diproses'] ?? 0),
            'dikirim' => (int) ($rows['dikirim'] ?? 0),
        ];
    }

    private static function ensureNotExpired(Claim $claim): void
    {
        if ($claim->expires_at?->isPast()) {
            throw new ClaimTransitionException("Klaim {$claim->claim_code} sudah lewat batas redeem.");
        }
    }

    /**
     * @param  array<int,string>  $from
     * @param  callable(Claim):array  $check  validasi + kolom tambahan yang ikut disimpan
     * @param  callable(Claim):void|null  $after  efek samping setelah status berubah
     */
    private static function move(Claim $claim, array $from, string $to, string $actor, ?User $user, ?string $note,
        callable $check, ?callable $after = null): Claim
    {
        return DB::transaction(function () use ($claim, $from, $to, $actor, $user, $note, $check, $after) {
            $locked = Claim::with('gamePrize')->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $from, true)) {
                $current = self::label($locked->status);
                throw new ClaimTransitionException("Klaim {$locked->claim_code} sudah berstatus {$current}, tidak bisa diubah.");
            }

            $extra = $check($locked);
            $previous = $locked->status;
            $locked->update(['status' => $to] + $extra);

            ClaimEvent::create([
                'claim_id' => $locked->id,
                'from_status' => $previous,
                'to_status' => $to,
                'actor' => $actor,
                'user_id' => $user?->id,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ]);

            if ($after) {
                $after($locked);
            }

            return $locked;
        });
    }
}
```

- [ ] **Step 4: Ubah ClaimService**

`packages/core/src/Prizes/ClaimService.php` (ganti seluruh isi):
```php
<?php

namespace LoyaltyNew\Core\Prizes;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\ClaimEvent;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\RewardCatalogItem;

final class ClaimService
{
    /** Dipertahankan untuk kode lama: status yang masih bisa hangus. */
    public const OPEN_STATUSES = ClaimFlow::EXPIRABLE;

    public const CATALOG_EXPIRY_DAYS = 14;

    /** Batas redeem hadiah Gatcha (spec §2): hadiah utama 30 hari, lainnya 14 hari. */
    public static function expiryFor(string $tier): int
    {
        return $tier === 'hadiah_besar' ? 30 : 14;
    }

    /**
     * Hanguskan klaim brand ini yang lewat batas dan belum diproses.
     *
     * @return int jumlah klaim yang di-hangus
     */
    public static function expireOverdue(?\DateTimeInterface $now = null, ?string $brand = null, bool $dryRun = false): int
    {
        $brand = $brand ?: (string) config('app.brand');

        $query = Claim::query()
            ->where('brand', $brand)
            ->where('expires_at', '<', $now ?? now())
            ->whereIn('status', ClaimFlow::EXPIRABLE);

        if ($dryRun) {
            return $query->count();
        }

        $count = 0;
        foreach ($query->pluck('id') as $id) {
            try {
                ClaimFlow::expire(Claim::findOrFail($id));
                $count++;
            } catch (ClaimTransitionException) {
                // Statusnya sudah diubah proses lain sejak query di atas: lewati.
            }
        }

        return $count;
    }

    /** Hadiah utama yang hangus mengembalikan kuotanya. Panggil sekali per transisi ke hangus. */
    public static function releaseBigPrizeQuota(Claim $claim, string $brand): void
    {
        $prize = $claim->gamePrize;

        if ($prize && $prize->tier === 'hadiah_besar') {
            BigPrizeQuota::release($brand, $prize);
        }
    }

    /** Nama hadiah untuk klaim dan layar: nama katalog kalau ada, kalau tidak label tier. */
    public static function prizeName(GamePrize $prize): string
    {
        if ($prize->prize_catalog_id) {
            $name = PrizeCatalog::whereKey($prize->prize_catalog_id)->value('name');
            if ($name) {
                return $name;
            }
        }

        return match ($prize->tier) {
            'hadiah_besar' => 'Hadiah Utama',
            'voucher' => 'Voucher',
            'merch' => 'Merch',
            default => 'Poin',
        };
    }

    public static function create(GameSession $session, GamePrize $prize): Claim
    {
        $claim = Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'brand' => $session->brand,
            'customer_id' => $session->customer_id,
            'source' => 'gatcha',
            'prize_name' => self::prizeName($prize),
            'claim_code' => self::uniqueCode(),
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(self::expiryFor($prize->tier)),
        ]);

        self::firstEvent($claim, 'system');

        return $claim;
    }

    public static function createFromCatalog(Customer $customer, RewardCatalogItem $item): Claim
    {
        $claim = Claim::create([
            'brand' => $customer->brand,
            'customer_id' => $customer->id,
            'source' => 'katalog',
            'prize_name' => $item->name,
            'reward_catalog_item_id' => $item->id,
            'points_spent' => $item->points_cost,
            'claim_code' => self::uniqueCode(),
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(self::CATALOG_EXPIRY_DAYS),
        ]);

        self::firstEvent($claim, 'customer');

        return $claim;
    }

    private static function firstEvent(Claim $claim, string $actor): void
    {
        ClaimEvent::create([
            'claim_id' => $claim->id,
            'from_status' => null,
            'to_status' => 'belum_redeem',
            'actor' => $actor,
        ]);
    }

    private static function uniqueCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
        } while (Claim::where('claim_code', $code)->exists());

        return $code;
    }
}
```

- [ ] **Step 5: Tulis ExpirePointsCommand dan daftarkan**

`packages/core/src/Console/ExpirePointsCommand.php`:
```php
<?php

namespace LoyaltyNew\Core\Console;

use Illuminate\Console\Command;
use LoyaltyNew\Core\Points\PointWallet;

/**
 * Hanguskan poin yang lewat masa berlaku (spec §4). Dijadwalkan harian. Idempotent.
 *
 *   php artisan loyalty:expire-points
 */
final class ExpirePointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Hanguskan poin yang lewat masa berlaku (brand ini saja)';

    public function handle(): int
    {
        $points = PointWallet::expireDue((string) config('app.brand'));

        $this->info("Poin hangus: {$points}");

        return self::SUCCESS;
    }
}
```

Di `packages/core/src/CoreServiceProvider.php`, tambahkan `use LoyaltyNew\Core\Console\ExpirePointsCommand;` dan ganti baris
`$this->commands([SmokeSeedCommand::class, ExpireClaimsCommand::class]);` menjadi
`$this->commands([SmokeSeedCommand::class, ExpireClaimsCommand::class, ExpirePointsCommand::class]);`.

Di `apps/amura/routes/console.php`, setelah baris `Schedule::command('loyalty:expire-claims')->dailyAt('01:00');` tambahkan:
```php

// Poin lewat masa berlaku 12 bulan (spec poin-redeem §4).
Schedule::command('loyalty:expire-points')->dailyAt('01:05');
```

- [ ] **Step 6: Sesuaikan status di SmokeSeeder**

Di `packages/core/src/Seeding/SmokeSeeder.php` pada `seedSessionsAndClaims()`, ganti `claimStatus: 'menunggu',` menjadi `claimStatus: 'belum_redeem',`. Seeder dirombak penuh di Task 13.

- [ ] **Step 7: Jalankan test ClaimFlow, pastikan lulus**

Run: `cd apps/amura && php artisan test --filter=ClaimFlowTest`
Expected: PASS (13 test).

- [ ] **Step 8: Sesuaikan test lama ke status baru**

Jalankan dari `apps/amura`:
```bash
sed -i "s/'menunggu'/'belum_redeem'/g" tests/Feature/CsClaimTest.php tests/Feature/ExpireClaimsTest.php tests/Feature/BugfixRegressionTest.php tests/Feature/FinanceTest.php tests/Feature/SmokeSeederTest.php
```

Lalu edit manual:

`tests/Feature/CsClaimTest.php`, ganti method `test_hadiah_besar_butuh_finance` dengan:
```php
    public function test_klaim_baru_selalu_belum_redeem_termasuk_hadiah_besar(): void
    {
        $prize = $this->prize('hadiah_besar');
        $claim = ClaimService::create($prize->session, $prize);

        $this->assertSame('belum_redeem', $claim->status);
        $this->assertSame('Hadiah Utama', $claim->prize_name);
    }
```
Di `test_cs_tidak_bisa_set_selesai_untuk_hadiah_besar` dan `test_cs_tidak_bisa_set_diproses_untuk_hadiah_besar`, ganti `$this->assertSame('perlu_finance', $claim->status);` menjadi `$this->assertSame('belum_redeem', $claim->status);`.

`tests/Feature/PlayFlowTest.php` pada `test_take_membuat_klaim`, ganti:
```php
        $expectedStatus = $claim->gamePrize->tier === 'hadiah_besar' ? 'perlu_finance' : 'menunggu';
        $this->assertSame($expectedStatus, $claim->status);
```
dengan:
```php
        $this->assertSame('belum_redeem', $claim->status);
```

`tests/Feature/SpecVerificationTest.php` pada `test_06_batas_klaim_dan_auto_hangus`, ganti `$this->assertSame('perlu_finance', $utama->fresh()->status);` menjadi `$this->assertSame('belum_redeem', $utama->fresh()->status);`.

`tests/Feature/BugfixRegressionTest.php`, pada `test_2_reveal_hadiah_utama_langsung_masuk_keranjang` dan `test_2_take_tanpa_reveal_hadiah_utama_tetap_yang_diambil`, ganti `$this->assertSame('perlu_finance', Claim::firstOrFail()->status);` menjadi `$this->assertSame('belum_redeem', Claim::firstOrFail()->status);`.

`tests/Feature/ExpireClaimsTest.php`: di `makeClaim()`, ganti blok `return Claim::create([` sampai `]);` dengan:
```php
        return Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'brand' => $brand,
            'customer_id' => $customer->id,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
```
lalu ganti method `test_klaim_perlu_finance_dan_diproses_juga_di_hanguskan` dengan:
```php
    public function test_perlu_finance_hangus_tapi_diproses_tidak(): void
    {
        $a = $this->makeClaim('amura', 'perlu_finance', now()->subDay()->toDateTimeString());
        $b = $this->makeClaim('amura', 'diproses', now()->subDay()->toDateTimeString());

        $this->artisan('loyalty:expire-claims');

        $this->assertSame('hangus', $a->fresh()->status);
        $this->assertSame('diproses', $b->fresh()->status);
    }

    public function test_expire_points_command_jalan(): void
    {
        $this->artisan('loyalty:expire-points')->assertExitCode(0);
    }
```

`tests/Feature/SmokeSeederTest.php`, ganti `test_klaim_biasa_menunggu_dan_klaim_besar_perlu_finance` menjadi sementara:
```php
    public function test_klaim_biasa_belum_redeem_dan_klaim_besar_perlu_finance(): void
    {
        $this->seedSmoke();

        $std = Claim::where('claim_code', 'SMOKE-amura-STD')->firstOrFail();
        $this->assertSame('belum_redeem', $std->status);

        $big = Claim::where('claim_code', 'SMOKE-amura-BIG')->firstOrFail();
        $this->assertSame('perlu_finance', $big->status);
        $this->assertSame('hadiah_besar', $big->gamePrize->tier);
    }
```

- [ ] **Step 9: Jalankan suite Amura dan core**

Run: `cd packages/core && vendor/bin/phpunit && cd ../../apps/amura && php artisan test`
Expected: semua lulus.

- [ ] **Step 10: Commit**

```bash
git add packages/core/src apps/amura/routes/console.php apps/amura/tests/Feature
git commit -m "feat(core): mesin status klaim redeem, klaim katalog, dan job hangus poin

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 4: Nama pelanggan dari gate, isi kado voucher/merch, dan kado poin ke saldo

**Files:**
- Modify: `packages/core/src/Gate/OrderStatus.php`, `GateResult.php`, `MarkomApiOrderGate.php`, `FairtrackSqliteOrderStatusSource.php`, `MarkomHttpOrderStatusSource.php`, `CachedOrderStatusSource.php`
- Modify: `packages/core/src/Gacha/SessionService.php`
- Create: `packages/core/src/Prizes/PrizeAwarder.php`
- Modify: `apps/amura/app/Http/Controllers/PlayController.php`
- Test: `packages/core/tests/Gate/CustomerNameTest.php`, `apps/amura/tests/Feature/PlayFlowTest.php`, `apps/amura/tests/Feature/SessionServiceTest.php`

**Interfaces:**
- Consumes: `PointWallet`, `LoyaltySettings` (Task 2), `ClaimService::create()`, `ClaimService::prizeName()` (Task 3).
- Produces:
  - `OrderStatus::$customerName` (?string, argumen terakhir, default null), `GateResult::$customerName`, `GateResult::delivered(?string $customerName = null)`.
  - `PrizeAwarder::award(GameSession, GamePrize): array` → `['type' => 'points', 'points' => int, 'balance' => int]` atau `['type' => 'claim', 'claim' => Claim]`.
  - JSON `take`/`reveal` (auto) menambah `points`, `balance`, `prize_name`, dan `claim_code`/`claim_expires_at` bernilai null untuk kado poin. Item `revealed[]` berisi `slot`, `tier`, `label`, `name` (nama untuk non-poin), `points` (untuk poin).
  - Board payload `taken[]` berisi `slot`, `tier`, `label`, `name`, `points`, `claim_code`, `claim_expires_at`.

- [ ] **Step 1: Tulis test core yang gagal**

`packages/core/tests/Gate/CustomerNameTest.php`:
```php
<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\FairtrackSqliteOrderStatusSource;
use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use LoyaltyNew\Core\Gate\OrderStatus;
use LoyaltyNew\Core\Gate\OrderStatusSource;
use PHPUnit\Framework\TestCase;

final class CustomerNameTest extends TestCase
{
    public function test_fairtrack_membaca_customer_name(): void
    {
        $db = tempnam(sys_get_temp_dir(), 'ftn').'.db';
        $pdo = new \PDO('sqlite:'.$db);
        $pdo->exec('create table orders (awb text, customer_phone text, customer_name text, status_order text, status_fulfillment text)');
        $pdo->exec("insert into orders values ('LNCHN','628111','Sari Wulandari','completed','complete')");

        $status = (new FairtrackSqliteOrderStatusSource($db))->find('LNCHN');
        @unlink($db);

        $this->assertSame('Sari Wulandari', $status->customerName);
    }

    public function test_fairtrack_tanpa_kolom_nama_tetap_jalan(): void
    {
        $db = tempnam(sys_get_temp_dir(), 'ftx').'.db';
        $pdo = new \PDO('sqlite:'.$db);
        $pdo->exec('create table orders (awb text, customer_phone text, status_order text, status_fulfillment text)');
        $pdo->exec("insert into orders values ('LNCHX','628111','completed','complete')");

        $status = (new FairtrackSqliteOrderStatusSource($db))->find('LNCHX');
        @unlink($db);

        $this->assertNotNull($status);
        $this->assertNull($status->customerName);
    }

    public function test_gate_meneruskan_nama_saat_lolos(): void
    {
        $source = new class implements OrderStatusSource {
            public function find(string $awb): ?OrderStatus
            {
                return new OrderStatus('JX1', '628123456789', 'completed', 'complete', 'Sari');
            }
        };

        $result = (new MarkomApiOrderGate($source))->isDelivered('JX1', '08123456789');

        $this->assertTrue($result->delivered);
        $this->assertSame('Sari', $result->customerName);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd packages/core && vendor/bin/phpunit --filter CustomerNameTest`
Expected: FAIL (properti `customerName` tidak ada).

- [ ] **Step 3: Bawa nama pelanggan lewat gate**

`packages/core/src/Gate/OrderStatus.php`, ganti konstruktor menjadi:
```php
    public function __construct(
        public readonly string $awb,
        public readonly ?string $phone,
        public readonly ?string $statusOrder,
        public readonly ?string $statusFulfillment,
        public readonly ?string $customerName = null,
    ) {
    }
```

`packages/core/src/Gate/GateResult.php` (ganti seluruh isi):
```php
<?php

namespace LoyaltyNew\Core\Gate;

final class GateResult
{
    public function __construct(
        public readonly bool $delivered,
        public readonly ?string $reason = null,
        public readonly ?string $customerName = null,
    ) {
    }

    public static function delivered(?string $customerName = null): self
    {
        return new self(true, null, $customerName);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason);
    }
}
```

`packages/core/src/Gate/MarkomApiOrderGate.php`: ganti baris terakhir `return GateResult::delivered();` menjadi `return GateResult::delivered($order->customerName);`.

`packages/core/src/Gate/FairtrackSqliteOrderStatusSource.php`: ganti query
```php
            $stmt = $pdo->prepare(
                'select awb, customer_phone, status_order, status_fulfillment '
                .'from '.$this->ordersTable.' where awb = :awb limit 1'
            );
```
menjadi
```php
            // select * supaya DB lama tanpa kolom customer_name tetap terbaca.
            $stmt = $pdo->prepare('select * from '.$this->ordersTable.' where awb = :awb limit 1');
```
dan ganti pembuatan objek di akhir method menjadi:
```php
        return new OrderStatus(
            awb: (string) $row['awb'],
            phone: $row['customer_phone'] ?? null,
            statusOrder: $row['status_order'] ?? null,
            statusFulfillment: $row['status_fulfillment'] ?? null,
            customerName: $row['customer_name'] ?? null,
        );
```

`packages/core/src/Gate/MarkomHttpOrderStatusSource.php`: di `new OrderStatus(...)` terakhir tambahkan argumen `customerName: $data['customer_name'] ?? null,` setelah `statusFulfillment`.

`packages/core/src/Gate/CachedOrderStatusSource.php`: di `new OrderStatus(...)` dari cache tambahkan `customerName: $cached['customer_name'] ?? null,`; di `Cache::put($key, [...])` tambahkan `'customer_name' => $status->customerName,`.

- [ ] **Step 4: Jalankan test core**

Run: `cd packages/core && vendor/bin/phpunit`
Expected: semua lulus (termasuk 3 test baru).

- [ ] **Step 5: Tulis test Amura yang gagal**

Di `apps/amura/tests/Feature/PlayFlowTest.php`, tambahkan `use LoyaltyNew\Core\Models\PointLot;` dan `use LoyaltyNew\Core\Models\PrizeCatalog;` di atas, tambahkan helper setelah `start()`:
```php
    private function forceTiers(GameSession $session, array $slots, string $tier): void
    {
        $session->prizes()->whereIn('slot', $slots)->update(['tier' => $tier, 'prize_catalog_id' => null]);
    }
```
Pada `test_take_membuat_klaim`, sisipkan `$this->forceTiers($session, [1, 2, 3], 'voucher');` tepat setelah `$session = $this->start();`. Pada `test_take_dua_kali_tidak_membuat_dua_klaim`, sisipkan baris yang sama di posisi yang sama. Lalu tambahkan test baru:
```php
    public function test_take_kado_poin_masuk_saldo_tanpa_klaim(): void
    {
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'poin');

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->postJson("/play/{$session->session_token}/take", ['slot' => 2])
            ->assertOk()
            ->assertJsonPath('points', 200)
            ->assertJsonPath('balance', 200)
            ->assertJsonPath('claim_code', null);

        $this->assertSame(0, Claim::count());
        $this->assertSame(1, PointLot::count());
    }

    public function test_reveal_voucher_diberi_nama_item_katalog(): void
    {
        PrizeCatalog::create(['brand' => config('app.brand'), 'name' => 'Voucher Gratis Ongkir', 'tier' => 'voucher', 'active' => true]);
        PrizeCatalog::create(['brand' => config('app.brand'), 'name' => 'Item Nonaktif', 'tier' => 'voucher', 'active' => false]);
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'voucher');

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $response = $this->postJson("/play/{$session->session_token}/reveal")->assertOk();

        $this->assertSame(['Voucher Gratis Ongkir'], array_values(array_unique(array_column($response->json('revealed'), 'name'))));

        $this->postJson("/play/{$session->session_token}/take", ['slot' => 1])
            ->assertOk()
            ->assertJsonPath('prize_name', 'Voucher Gratis Ongkir');
        $this->assertSame('Voucher Gratis Ongkir', Claim::firstOrFail()->prize_name);
    }

    public function test_reveal_kado_poin_menyertakan_nilai_poin(): void
    {
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'poin');

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->postJson("/play/{$session->session_token}/reveal")
            ->assertOk()
            ->assertJsonPath('revealed.0.points', 200);
    }
```

Di `apps/amura/tests/Feature/SessionServiceTest.php`, tambahkan test (sesuaikan `use` dengan `LoyaltyNew\Core\Gate\GateResult`, `LoyaltyNew\Core\Gate\OrderGate`, `LoyaltyNew\Core\Gacha\SessionService`, `LoyaltyNew\Core\Models\Customer` bila belum ada):
```php
    public function test_open_menyimpan_nama_pelanggan_dari_gate(): void
    {
        $gate = new class implements OrderGate {
            public function isDelivered(string $awb, string $phone): GateResult
            {
                return GateResult::delivered('Sari Wulandari');
            }
        };

        $service = new SessionService($gate, (string) config('app.brand'));
        $session = $service->open('JX-NAMA', '081234560000');

        $this->assertSame('Sari Wulandari', Customer::findOrFail($session->customer_id)->name);
    }
```

- [ ] **Step 6: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter='PlayFlowTest|SessionServiceTest'`
Expected: FAIL pada 4 test baru.

- [ ] **Step 7: Ubah SessionService**

Di `packages/core/src/Gacha/SessionService.php::open()`, ganti
```php
        $customer->update(['last_seen_at' => now()]);
```
dengan
```php
        // Nama dari data order (spec poin-redeem §2 #8). Gate stub tidak memberi nama.
        $customer->update(array_filter(['last_seen_at' => now(), 'name' => $result->customerName]));
```

Di `openRound()`, ganti isi loop menjadi:
```php
        foreach ($round as $prize) {
            if ($prize->opened) {
                continue;
            }

            if ($prize->tier === 'hadiah_besar' && ! BigPrizeQuota::reserve($this->brand, $prize)) {
                $prize->tier = $this->drawRegularTier($session);
                $prize->prize_catalog_id = null;
            }

            // Voucher/Merch mendapat item spesifik supaya CS tahu apa yang dikirim.
            if (in_array($prize->tier, ['voucher', 'merch'], true) && ! $prize->prize_catalog_id) {
                $prize->prize_catalog_id = PrizeCatalog::where('brand', $this->brand)
                    ->where('tier', $prize->tier)
                    ->where('active', true)
                    ->inRandomOrder()
                    ->value('id');
            }

            $prize->opened = true;
            $prize->save();
        }
```

- [ ] **Step 8: Tulis PrizeAwarder**

`packages/core/src/Prizes/PrizeAwarder.php`:
```php
<?php

namespace LoyaltyNew\Core\Prizes;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;

/** Kado yang diambil: poin langsung masuk saldo, lainnya menjadi klaim belum_redeem. */
final class PrizeAwarder
{
    /** @return array{type:'points',points:int,balance:int}|array{type:'claim',claim:Claim} */
    public static function award(GameSession $session, GamePrize $prize): array
    {
        if ($prize->tier === 'poin') {
            $settings = LoyaltySettings::current($session->brand);
            $customer = Customer::withoutGlobalScopes()->findOrFail($session->customer_id);
            $wallet = new PointWallet($customer);
            $lot = $wallet->credit($prize, $settings['per_gift'], $settings['validity_months']);

            return ['type' => 'points', 'points' => $lot->points, 'balance' => $wallet->balance()];
        }

        $claim = Claim::where('game_prize_id', $prize->id)->first() ?? ClaimService::create($session, $prize);

        return ['type' => 'claim', 'claim' => $claim];
    }
}
```

- [ ] **Step 9: Ubah PlayController Amura**

Di `apps/amura/app/Http/Controllers/PlayController.php`:

Tambahkan import:
```php
use LoyaltyNew\Core\Models\PointLot;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\PrizeAwarder;
```
dan hapus `use LoyaltyNew\Core\Models\PrizeCatalog;` serta `use LoyaltyNew\Core\Prizes\ClaimService;` kalau tidak dipakai lagi setelah perubahan di bawah (masih dipakai: `ClaimService::prizeName`, jadi pertahankan `ClaimService`).

Ganti method `keep()` dengan:
```php
    /** Simpan satu hadiah dari ronde, lepas dua lainnya, lalu beri poin atau terbitkan klaim. */
    private function keep(GameSession $session, Collection $round, GamePrize $chosen): array
    {
        $chosen->update(['taken' => true, 'opened' => true]);

        $others = $round->reject(fn (GamePrize $prize) => $prize->id === $chosen->id);
        $session->prizes()->whereIn('id', $others->pluck('id'))->update(['picked' => false]);

        $award = PrizeAwarder::award($session, $chosen);
        $claim = $award['type'] === 'claim' ? $award['claim'] : null;

        return [
            'ok' => true,
            'revealed' => $this->revealed($round),
            'chosen' => $chosen->slot,
            'prize_name' => $claim?->prize_name,
            'points' => $award['type'] === 'points' ? $award['points'] : null,
            'balance' => $award['type'] === 'points' ? $award['balance'] : null,
            'claim_code' => $claim?->claim_code,
            'claim_expires_at' => $claim?->expires_at->toDateString(),
            'chance_left' => max(0, $session->chance_total - $session->fresh()->chance_used),
            'chance_total' => $session->chance_total,
        ];
    }
```

Ganti `revealed()`, `taken()`, dan `prizeName()` (hapus `prizeName()` lama) dengan:
```php
    /** @return array<int, array{slot:int, tier:string, label:string, name:?string, points:?int}> */
    private function revealed(Collection $round): array
    {
        $perGift = LoyaltySettings::current()['per_gift'];

        return $round->map(fn (GamePrize $prize) => [
            'slot' => $prize->slot,
            'tier' => $prize->tier,
            'label' => $this->tierLabel($prize->tier),
            'name' => $prize->tier === 'poin' ? null : ClaimService::prizeName($prize),
            'points' => $prize->tier === 'poin' ? $perGift : null,
        ])->values()->all();
    }

    /** Hadiah yang sudah diambil di sesi ini, untuk ringkasan di papan. */
    private function taken(GameSession $session): array
    {
        return $session->prizes()->where('taken', true)->orderBy('updated_at')->get()
            ->map(function (GamePrize $prize) {
                $claim = Claim::where('game_prize_id', $prize->id)->first();

                return [
                    'slot' => $prize->slot,
                    'tier' => $prize->tier,
                    'label' => $this->tierLabel($prize->tier),
                    'name' => $claim?->prize_name,
                    'points' => $prize->tier === 'poin' ? PointLot::where('game_prize_id', $prize->id)->value('points') : null,
                    'claim_code' => $claim?->claim_code,
                    'claim_expires_at' => $claim?->expires_at?->toDateString(),
                ];
            })->values()->all();
    }
```

- [ ] **Step 10: Jalankan suite core + Amura**

Run: `cd packages/core && vendor/bin/phpunit && cd ../../apps/amura && php artisan test`
Expected: semua lulus, termasuk `test_board_tidak_bocorkan_tier`.

- [ ] **Step 11: Commit**

```bash
git add packages/core apps/amura/app/Http/Controllers/PlayController.php apps/amura/tests/Feature
git commit -m "feat: nama pelanggan dari gate, item voucher/merch diundi, kado poin masuk saldo

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 5: Katalog bulanan dan tukar poin (core)

**Files:**
- Create: `packages/core/src/Prizes/RewardCatalogService.php`, `packages/core/src/Prizes/CatalogUnavailableException.php`
- Test: `apps/amura/tests/Feature/RewardCatalogServiceTest.php`

**Interfaces:**
- Consumes: `RewardCatalog`, `RewardCatalogItem` (Task 1), `PointWallet`, `LoyaltySettings`, `InsufficientPointsException` (Task 2), `ClaimService::createFromCatalog()` (Task 3).
- Produces:
  - `RewardCatalogService::active(string $brand, ?\DateTimeInterface $now = null): ?RewardCatalog`
  - `RewardCatalogService::exchange(Customer $customer, int $itemId): Claim` (lempar `InsufficientPointsException` atau `CatalogUnavailableException`)
  - `RewardCatalogService::forPeriod(string $brand, string $period): RewardCatalog` (buat draft kalau belum ada)
  - `RewardCatalogService::copyFrom(string $brand, string $fromPeriod, string $toPeriod): int`
  - `RewardCatalogService::publish(string $brand, string $period): RewardCatalog`
  - `RewardCatalogService::previousPeriod(string $period): string`

- [ ] **Step 1: Tulis test yang gagal**

`apps/amura/tests/Feature/RewardCatalogServiceTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Points\InsufficientPointsException;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\CatalogUnavailableException;
use LoyaltyNew\Core\Prizes\RewardCatalogService;
use Tests\TestCase;

final class RewardCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function customerWith(int $points): Customer
    {
        $customer = Customer::create(['phone' => '0812'.random_int(10000000, 99999999), 'brand' => $this->brand()]);
        if ($points > 0) {
            (new PointWallet($customer))->grant($points, 12);
        }

        return $customer;
    }

    private function catalog(string $period, bool $published = true): RewardCatalog
    {
        return RewardCatalog::create([
            'brand' => $this->brand(),
            'period' => $period,
            'published_at' => $published ? now() : null,
        ]);
    }

    public function test_active_memakai_katalog_terbit_terbaru_yang_sudah_berjalan(): void
    {
        $this->catalog(now()->subMonth()->format('Y-m'));
        $current = $this->catalog(now()->format('Y-m'));
        $this->catalog(now()->addMonth()->format('Y-m'));

        $this->assertSame($current->id, RewardCatalogService::active($this->brand())->id);
    }

    public function test_bulan_baru_belum_terbit_katalog_lama_tetap_aktif(): void
    {
        $old = $this->catalog(now()->subMonth()->format('Y-m'));
        $this->catalog(now()->format('Y-m'), published: false);

        $this->assertSame($old->id, RewardCatalogService::active($this->brand())->id);
    }

    public function test_tukar_memotong_poin_stok_dan_membuat_klaim(): void
    {
        $customer = $this->customerWith(1200);
        $item = $this->catalog(now()->format('Y-m'))->items()->create(['name' => 'Voucher Free Ongkir', 'points_cost' => 1000, 'stock' => 3]);

        $claim = RewardCatalogService::exchange($customer, $item->id);

        $this->assertSame('belum_redeem', $claim->status);
        $this->assertSame('katalog', $claim->source);
        $this->assertSame('Voucher Free Ongkir', $claim->prize_name);
        $this->assertSame(1000, $claim->points_spent);
        $this->assertEqualsWithDelta(14, now()->diffInDays($claim->expires_at), 0.1);
        $this->assertSame(200, (new PointWallet($customer))->balance());
        $this->assertSame(1, $item->fresh()->stock_used);
    }

    public function test_tukar_kedua_saat_saldo_tidak_cukup_tidak_membuat_klaim(): void
    {
        $customer = $this->customerWith(1200);
        $item = $this->catalog(now()->format('Y-m'))->items()->create(['name' => 'Voucher Free Ongkir', 'points_cost' => 1000]);

        RewardCatalogService::exchange($customer, $item->id);

        try {
            RewardCatalogService::exchange($customer, $item->id);
            $this->fail('tukar kedua harus ditolak');
        } catch (InsufficientPointsException $e) {
            $this->assertSame(200, $e->balance);
        }

        $this->assertSame(1, Claim::count());
        $this->assertSame(200, (new PointWallet($customer))->balance());
    }

    public function test_item_nonaktif_atau_stok_habis_ditolak_tanpa_memotong_poin(): void
    {
        $customer = $this->customerWith(2000);
        $catalog = $this->catalog(now()->format('Y-m'));
        $off = $catalog->items()->create(['name' => 'Nonaktif', 'points_cost' => 500, 'active' => false]);
        $empty = $catalog->items()->create(['name' => 'Habis', 'points_cost' => 500, 'stock' => 1, 'stock_used' => 1]);

        foreach ([$off, $empty] as $item) {
            try {
                RewardCatalogService::exchange($customer, $item->id);
                $this->fail("{$item->name} harus ditolak");
            } catch (CatalogUnavailableException) {
                // diharapkan
            }
        }

        $this->assertSame(0, Claim::count());
        $this->assertSame(2000, (new PointWallet($customer))->balance());
    }

    public function test_item_dari_katalog_yang_tidak_aktif_ditolak(): void
    {
        $customer = $this->customerWith(2000);
        $this->catalog(now()->format('Y-m'));
        $future = $this->catalog(now()->addMonth()->format('Y-m'))->items()->create(['name' => 'Bulan depan', 'points_cost' => 500]);

        $this->expectException(CatalogUnavailableException::class);
        RewardCatalogService::exchange($customer, $future->id);
    }

    public function test_salin_dan_terbitkan_katalog(): void
    {
        $from = now()->subMonth()->format('Y-m');
        $to = now()->format('Y-m');
        $source = $this->catalog($from);
        $source->items()->create(['name' => 'Pouch Exclusive', 'points_cost' => 500, 'stock' => 10, 'stock_used' => 7, 'sort' => 1]);
        $source->items()->create(['name' => 'Exclusive ToteBag', 'points_cost' => 700, 'sort' => 2]);

        $this->assertSame($from, RewardCatalogService::previousPeriod($to));
        $this->assertSame(2, RewardCatalogService::copyFrom($this->brand(), $from, $to));

        $copy = RewardCatalogService::forPeriod($this->brand(), $to);
        $this->assertNull($copy->published_at);
        $this->assertSame(['Pouch Exclusive', 'Exclusive ToteBag'], $copy->items->pluck('name')->all());
        $this->assertSame(0, $copy->items->first()->stock_used);

        RewardCatalogService::publish($this->brand(), $to);
        $this->assertSame($copy->id, RewardCatalogService::active($this->brand())->id);

        $this->expectException(CatalogUnavailableException::class);
        RewardCatalogService::copyFrom($this->brand(), $from, $to);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=RewardCatalogServiceTest`
Expected: FAIL (`Class ... RewardCatalogService not found`).

- [ ] **Step 3: Tulis implementasi**

`packages/core/src/Prizes/CatalogUnavailableException.php`:
```php
<?php

namespace LoyaltyNew\Core\Prizes;

final class CatalogUnavailableException extends \RuntimeException
{
}
```

`packages/core/src/Prizes/RewardCatalogService.php`:
```php
<?php

namespace LoyaltyNew\Core\Prizes;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\RewardCatalogItem;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;

/**
 * Katalog tukar poin bulanan (spec §5). Katalog aktif = katalog terbit terbaru
 * yang bulannya sudah berjalan, jadi katalog lama tetap tampil sampai yang baru terbit.
 */
final class RewardCatalogService
{
    public static function active(string $brand, ?\DateTimeInterface $now = null): ?RewardCatalog
    {
        $period = ($now ? Carbon::instance($now) : now())->format('Y-m');

        return RewardCatalog::withoutGlobalScopes()
            ->where('brand', $brand)
            ->whereNotNull('published_at')
            ->where('period', '<=', $period)
            ->orderByDesc('period')
            ->first();
    }

    public static function exchange(Customer $customer, int $itemId): Claim
    {
        return DB::transaction(function () use ($customer, $itemId) {
            $active = self::active($customer->brand);
            $item = RewardCatalogItem::whereKey($itemId)->lockForUpdate()->first();

            if (! $active || ! $item || (int) $item->reward_catalog_id !== (int) $active->id || ! $item->active) {
                throw new CatalogUnavailableException('Hadiah ini tidak ada di katalog bulan ini.');
            }

            if (! $item->inStock()) {
                throw new CatalogUnavailableException('Stok hadiah ini sudah habis.');
            }

            $settings = LoyaltySettings::current($customer->brand);
            $claim = ClaimService::createFromCatalog($customer, $item);
            (new PointWallet($customer))->spend($claim, $item->points_cost, $settings['min_redeem']);
            $item->increment('stock_used');

            return $claim;
        });
    }

    public static function forPeriod(string $brand, string $period): RewardCatalog
    {
        return RewardCatalog::withoutGlobalScopes()->firstOrCreate(['brand' => $brand, 'period' => $period]);
    }

    /** Salin item dari bulan lain ke draft bulan tujuan. Ditolak kalau tujuan sudah berisi. */
    public static function copyFrom(string $brand, string $fromPeriod, string $toPeriod): int
    {
        return DB::transaction(function () use ($brand, $fromPeriod, $toPeriod) {
            $source = RewardCatalog::withoutGlobalScopes()->where('brand', $brand)->where('period', $fromPeriod)->first();
            if (! $source) {
                throw new CatalogUnavailableException("Katalog {$fromPeriod} tidak ditemukan.");
            }

            $target = self::forPeriod($brand, $toPeriod);
            if ($target->items()->exists()) {
                throw new CatalogUnavailableException("Katalog {$toPeriod} sudah berisi item.");
            }

            $count = 0;
            foreach ($source->items as $item) {
                $target->items()->create([
                    'name' => $item->name,
                    'points_cost' => $item->points_cost,
                    'image_path' => $item->image_path,
                    'stock' => $item->stock,
                    'stock_used' => 0,
                    'active' => $item->active,
                    'sort' => $item->sort,
                ]);
                $count++;
            }

            return $count;
        });
    }

    public static function publish(string $brand, string $period): RewardCatalog
    {
        $catalog = self::forPeriod($brand, $period);
        if (! $catalog->published_at) {
            $catalog->update(['published_at' => now()]);
        }

        return $catalog;
    }

    public static function previousPeriod(string $period): string
    {
        return Carbon::createFromFormat('Y-m-d', $period.'-01')->subMonthNoOverflow()->format('Y-m');
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `cd apps/amura && php artisan test --filter=RewardCatalogServiceTest`
Expected: PASS (7 test).

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Prizes apps/amura/tests/Feature/RewardCatalogServiceTest.php
git commit -m "feat(core): katalog tukar poin bulanan dan penukaran poin

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 6: Perangkat diingat 30 hari dan Beranda pelanggan

**Files:**
- Create: `apps/amura/app/Http/Middleware/RememberedCustomer.php`
- Create: `apps/amura/app/Http/Controllers/Customer/HomeController.php`
- Create: `apps/amura/resources/views/customer/home.blade.php`, `apps/amura/resources/views/customer/partials/claim-row.blade.php`
- Modify: `apps/amura/bootstrap/app.php`, `apps/amura/routes/web.php`, `apps/amura/app/Http/Controllers/PlayController.php` (`start()`), `apps/amura/app/Providers/AppServiceProvider.php`, `apps/amura/config/play.php`, `apps/amura/public/css/play.css`
- Test: `apps/amura/tests/Feature/CustomerAreaTest.php`, `apps/amura/tests/Feature/ExampleTest.php`

**Interfaces:**
- Consumes: `PointWallet` (Task 2), `ClaimFlow::label()` (Task 3), `Claim::daysLeft()`, `Customer::maskedPhone()` (Task 1).
- Produces:
  - Middleware alias `customer` (`RememberedCustomer`), konstanta `RememberedCustomer::COOKIE = 'loyalty_customer'`, `RememberedCustomer::cookieFor(Customer): Cookie`, `RememberedCustomer::resolve(Request): ?Customer`. Pelanggan tersedia di `$request->attributes->get('customer')`.
  - Route: `GET /` name `home`, `POST /keluar` name `customer.logout`. View Beranda menautkan ke `rewards`, `rewards.show`, dan `catalog`, jadi semua route area pelanggan didaftarkan di task ini (Step 5). Controller `RewardController` dan `CatalogController` dibuat kosong di sini dan diisi di Task 7 dan 8.
  - Partial `customer.partials.claim-row` dengan variabel `$claim`.

- [ ] **Step 1: Tulis test yang gagal**

`apps/amura/tests/Feature/CustomerAreaTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Http\Middleware\RememberedCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Points\PointWallet;
use Tests\TestCase;

final class CustomerAreaTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $phone = '081234567890', ?string $name = 'Sari'): Customer
    {
        return Customer::firstOrCreate(['phone' => $phone, 'brand' => config('app.brand')], ['name' => $name]);
    }

    private function asCustomer(Customer $customer): self
    {
        return $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => $customer->id, 'brand' => $customer->brand]));
    }

    public function test_masuk_memasang_cookie_perangkat(): void
    {
        $this->post('/play/start', ['awb' => 'JX-COOKIE', 'phone' => '081234567890'])
            ->assertRedirect()
            ->assertCookie(RememberedCustomer::COOKIE);
    }

    public function test_masuk_saat_kesempatan_habis_diarahkan_ke_beranda(): void
    {
        $this->post('/play/start', ['awb' => 'JX-HABIS', 'phone' => '081234567890']);
        GameSession::firstOrFail()->update(['chance_used' => 2]);

        $this->post('/play/start', ['awb' => 'JX-HABIS', 'phone' => '081234567890'])
            ->assertRedirect(route('home'));
    }

    public function test_beranda_tanpa_cookie_diarahkan_ke_form_tanpa_pesan_error(): void
    {
        $this->get('/')->assertRedirect(route('play.form'))->assertSessionHasNoErrors();
    }

    public function test_cookie_basi_diarahkan_ke_form_dengan_pesan(): void
    {
        $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => 999999, 'brand' => config('app.brand')]))
            ->get('/')
            ->assertRedirect(route('play.form'))
            ->assertSessionHasErrors('awb');

        $this->withCookie(RememberedCustomer::COOKIE, 'bukan-json')->get('/')->assertRedirect(route('play.form'));
    }

    public function test_cookie_brand_lain_ditolak(): void
    {
        $customer = $this->customer();

        $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => $customer->id, 'brand' => 'brand-lain']))
            ->get('/')
            ->assertRedirect(route('play.form'));
    }

    public function test_beranda_menampilkan_nama_saldo_dan_kosong(): void
    {
        $customer = $this->customer();
        (new PointWallet($customer))->grant(1400, 12);

        $this->asCustomer($customer)->get('/')
            ->assertOk()
            ->assertSee('Halo, Sari')
            ->assertSee('1.400')
            ->assertSee('Belum ada hadiah');
    }

    public function test_keluar_menghapus_cookie(): void
    {
        $customer = $this->customer();

        $this->asCustomer($customer)->post('/keluar')
            ->assertRedirect(route('play.form'))
            ->assertCookieExpired(RememberedCustomer::COOKIE);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=CustomerAreaTest`
Expected: FAIL (`Class "App\Http\Middleware\RememberedCustomer" not found`).

- [ ] **Step 3: Tulis middleware**

`apps/amura/app/Http/Middleware/RememberedCustomer.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Customer;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pelanggan yang sudah masuk dengan resi + HP diingat di perangkat ini 30 hari
 * (spec poin-redeem §7). Cookie dienkripsi Laravel dan hanya berlaku untuk brand ini.
 */
final class RememberedCustomer
{
    public const COOKIE = 'loyalty_customer';

    public function handle(Request $request, Closure $next): Response
    {
        $customer = self::resolve($request);

        if (! $customer) {
            $redirect = redirect()->route('play.form')->withoutCookie(self::COOKIE);

            return $request->hasCookie(self::COOKIE)
                ? $redirect->withErrors(['awb' => 'Masuk lagi dengan resi dan nomor HP.'])
                : $redirect;
        }

        $request->attributes->set('customer', $customer);

        return $next($request);
    }

    public static function resolve(Request $request): ?Customer
    {
        $raw = $request->cookie(self::COOKIE);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data) || ! isset($data['id']) || ($data['brand'] ?? null) !== config('app.brand')) {
            return null;
        }

        // BrandScope memastikan pelanggan brand lain tidak ditemukan.
        return Customer::find((int) $data['id']);
    }

    public static function cookieFor(Customer $customer): Cookie
    {
        $minutes = 60 * 24 * (int) config('play.remember_days', 30);

        return cookie(self::COOKIE, json_encode(['id' => $customer->id, 'brand' => $customer->brand]), $minutes);
    }
}
```

Di `apps/amura/bootstrap/app.php`, ganti alias menjadi:
```php
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'customer' => \App\Http\Middleware\RememberedCustomer::class,
        ]);
```

Di `apps/amura/config/play.php`, tambahkan sebelum `'themes' =>`:
```php
    // Perangkat pelanggan diingat sekian hari setelah resi + HP cocok.
    'remember_days' => 30,

```

Di `apps/amura/app/Providers/AppServiceProvider.php::boot()`, tambahkan baris pertama:
```php
        // Nama bulan/hari di layar pelanggan dalam bahasa Indonesia.
        \Carbon\Carbon::setLocale('id');
```

- [ ] **Step 4: Ubah `PlayController::start()`**

Tambahkan `use App\Http\Middleware\RememberedCustomer;` dan `use LoyaltyNew\Core\Models\Customer;` di atas. Ganti dua baris terakhir `start()`:
```php
        $limiter->clear($data['awb'], $data['phone']);

        return redirect()->route('play.board', $session->session_token);
```
dengan:
```php
        $limiter->clear($data['awb'], $data['phone']);

        $customer = Customer::findOrFail($session->customer_id);
        $target = $session->chance_used < $session->chance_total
            ? redirect()->route('play.board', $session->session_token)
            : redirect()->route('home');

        return $target->withCookie(RememberedCustomer::cookieFor($customer));
```

- [ ] **Step 5: Daftarkan route pelanggan**

Di `apps/amura/routes/web.php`, tambahkan import:
```php
use App\Http\Controllers\Customer\CatalogController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\RewardController;
```
Ganti blok `Route::get('/', function () { return view('welcome'); });` dengan:
```php
// Area pelanggan: perangkat diingat 30 hari setelah resi + HP cocok.
Route::middleware('customer')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/hadiah', [RewardController::class, 'index'])->name('rewards');
    Route::get('/hadiah/{code}', [RewardController::class, 'show'])->name('rewards.show');
    Route::post('/hadiah/{code}/redeem', [RewardController::class, 'redeem'])->name('rewards.redeem');
    Route::get('/katalog', [CatalogController::class, 'index'])->name('catalog');
    Route::post('/katalog/{item}/tukar', [CatalogController::class, 'exchange'])->name('catalog.exchange');
});
Route::post('/keluar', [HomeController::class, 'logout'])->name('customer.logout');
```
Hapus `resources/views/welcome.blade.php` (tidak dipakai lagi). Karena `/` sekarang mengarah ke form untuk tamu, ganti isi method di `apps/amura/tests/Feature/ExampleTest.php` menjadi:
```php
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertRedirect(route('play.form'));
        $this->get('/play')->assertOk();
    }
```
dan tambahkan `use Illuminate\Foundation\Testing\RefreshDatabase;` + `use RefreshDatabase;` di kelasnya (halaman `/play` tidak butuh DB, tapi middleware `customer` membaca tabel `customers` saat ada cookie).

Buat kerangka controller supaya route valid (diisi di Task 7 dan 8):

`apps/amura/app/Http/Controllers/Customer/RewardController.php`:
```php
<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

final class RewardController extends Controller
{
}
```

`apps/amura/app/Http/Controllers/Customer/CatalogController.php`:
```php
<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

final class CatalogController extends Controller
{
}
```

- [ ] **Step 6: Tulis HomeController**

`apps/amura/app/Http/Controllers/Customer/HomeController.php`:
```php
<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RememberedCustomer;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Points\PointWallet;

final class HomeController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $wallet = new PointWallet($customer);
        $claims = Claim::where('brand', config('app.brand'))->where('customer_id', $customer->id);

        $sessions = GameSession::where('customer_id', $customer->id)
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->get();
        $playable = $sessions->first(fn (GameSession $s) => $s->chance_used < $s->chance_total);

        return view('customer.home', [
            'customer' => $customer,
            'balance' => $wallet->balance(),
            'expiring' => $wallet->nextExpiring(),
            'open' => (clone $claims)->where('status', 'belum_redeem')->where('expires_at', '>', now())->orderBy('expires_at')->get(),
            'recent' => (clone $claims)->latest('id')->limit(3)->get(),
            'session' => $playable,
            'chances' => $sessions->sum(fn (GameSession $s) => max(0, $s->chance_total - $s->chance_used)),
        ]);
    }

    public function logout()
    {
        return redirect()->route('play.form')->withoutCookie(RememberedCustomer::COOKIE);
    }
}
```

- [ ] **Step 7: Tulis view Beranda dan partial**

`apps/amura/resources/views/customer/partials/claim-row.blade.php`:
```blade
<a class="claim-row" href="{{ route('rewards.show', $claim->claim_code) }}">
    <span class="thumb {{ $claim->isBigPrize() ? 'is-gold' : '' }}" aria-hidden="true"></span>
    <span class="claim-row-body">
        <b>{{ $claim->prize_name }}</b>
        <small>Kode {{ $claim->claim_code }} · {{ $claim->created_at->translatedFormat('j M') }}</small>
    </span>
    <span class="status status-{{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span>
</a>
```

`apps/amura/resources/views/customer/home.blade.php`:
```blade
@extends('layouts.play')

@section('title', $theme['name'].' · Poin & Hadiah')

@section('content')
    <header class="brandbar">
        <div class="brand-tile" aria-hidden="true">{{ $theme['initial'] }}</div>
        <div class="brandbar-text">
            <div class="brand-name">{{ $theme['name'] }}</div>
            <div class="brand-sub">Poin &amp; Hadiah</div>
        </div>
        <form method="POST" action="{{ route('customer.logout') }}">
            @csrf
            <button type="submit" class="link-btn">Bukan kamu? Keluar</button>
        </form>
    </header>

    <div class="page">
        @if (session('ok'))
            <div class="notice" role="status">{{ session('ok') }}</div>
        @endif

        <section class="wallet" aria-label="Saldo poin">
            <div class="wallet-hi">Halo, {{ $customer->name ?: 'Pelanggan '.$theme['name'] }} · {{ $customer->maskedPhone() }}</div>
            <div class="wallet-label">Poin {{ $theme['name'] }}</div>
            <div class="wallet-balance serif">{{ number_format($balance, 0, ',', '.') }}</div>
            <div class="wallet-note">
                @if ($expiring)
                    {{ number_format($expiring['points'], 0, ',', '.') }} poin hangus {{ $expiring['expires_at']->translatedFormat('j F Y') }}
                @else
                    Poin didapat dari kado Gatcha.
                @endif
            </div>
        </section>

        @if ($open->isNotEmpty())
            @php($first = $open->first())
            <section class="due">
                <div class="due-head">
                    <b>{{ $open->count() }} hadiah belum di-redeem</b>
                    <span class="pill">{{ $first->daysLeft() }} hari lagi</span>
                </div>
                <div class="due-item">
                    <span class="thumb {{ $first->isBigPrize() ? 'is-gold' : '' }}" aria-hidden="true"></span>
                    <span>
                        <b>{{ $first->prize_name }}</b>
                        <small>Kode {{ $first->claim_code }} · redeem sebelum {{ $first->expires_at->translatedFormat('j M') }}</small>
                    </span>
                </div>
                <a class="btn btn-wa" href="{{ route('rewards.show', $first->claim_code) }}">Redeem sekarang</a>
            </section>
        @endif

        <section>
            <div class="section-head">
                <h2>Hadiahku</h2>
                <a href="{{ route('rewards') }}">Lihat semua</a>
            </div>
            <div class="claim-list">
                @forelse ($recent as $claim)
                    @include('customer.partials.claim-row', ['claim' => $claim])
                @empty
                    <p class="empty-card">Belum ada hadiah. Main Gatcha untuk mendapatkan poin dan hadiah.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="entry-foot">
        <div class="home-actions">
            <a class="btn btn-outline" href="{{ route('catalog') }}">Katalog</a>
            @if ($session)
                <a class="btn btn-brand" href="{{ route('play.board', $session->session_token) }}">Gatcha ({{ $chances }})</a>
            @else
                <span class="btn btn-off" aria-disabled="true">Gatcha (0)</span>
            @endif
        </div>
    </div>
@endsection
```

- [ ] **Step 8: Tambah gaya area pelanggan**

Tambahkan di akhir `apps/amura/public/css/play.css` (sebelum blok `@media (prefers-reduced-motion`):
```css
/* ── Area pelanggan: Beranda, Hadiahku, Voucher, Katalog (desain 3c, 3d, 2d, 1f) ── */
.brandbar-text { flex: 1; min-width: 0; }
.link-btn { border: 0; background: transparent; color: var(--muted); font: 700 13px/1 inherit; font-family: inherit; min-height: 44px; padding: 0 4px; cursor: pointer; text-decoration: underline; }
.page { flex: 1; padding: 4px 16px 16px; display: flex; flex-direction: column; gap: 18px; }
.page h1 { margin: 0; font-size: 22px; font-weight: 800; }
.page h2 { margin: 0; font-size: 16px; font-weight: 800; }
.back { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; color: var(--ink); font-weight: 800; text-decoration: none; }
.notice { border-radius: 12px; padding: 12px 14px; background: var(--t); color: var(--d); font-size: 13px; font-weight: 700; }

.wallet { border-radius: 20px; padding: 18px; color: #fff; background: linear-gradient(160deg, var(--p), var(--d)); box-shadow: 0 10px 24px -12px rgba(15, 65, 60, .6); }
.wallet-hi { font-size: 12px; opacity: .85; }
.wallet-label { margin-top: 10px; font-size: 13px; font-weight: 700; opacity: .9; }
.wallet-balance { font-size: 44px; line-height: 1.05; }
.wallet-note { margin-top: 4px; font-size: 12px; opacity: .85; }

.due { border-radius: 18px; padding: 14px; background: #FBF1DE; border: 1px solid #EFD9AE; display: flex; flex-direction: column; gap: 12px; }
.due-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 14px; }
.pill { font-size: 11px; font-weight: 800; padding: 4px 8px; border-radius: 999px; background: #fff; color: var(--gold-edge); white-space: nowrap; }
.due-item { display: flex; gap: 12px; align-items: center; }
.due-item b { display: block; font-size: 15px; }
.due-item small { font-size: 12px; color: var(--muted); }
.btn-wa { display: grid; place-items: center; text-decoration: none; background: #2E7A4E; color: #fff; box-shadow: 0 5px 0 #1C5233; }
.btn-wa:active { transform: translateY(4px); box-shadow: 0 1px 0 #1C5233; }

.section-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 10px; }
.section-head a { color: var(--p); font-size: 13px; font-weight: 800; }
.claim-list { display: flex; flex-direction: column; gap: 10px; }
.claim-row { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 16px; background: #fff; border: 1px solid var(--line); color: var(--ink); text-decoration: none; min-height: 64px; }
.claim-row-body { flex: 1; min-width: 0; }
.claim-row-body b { display: block; font-size: 15px; overflow-wrap: anywhere; }
.claim-row-body small { font-size: 12px; color: var(--muted); }
.thumb { width: 44px; height: 44px; flex: none; border-radius: 10px; background: repeating-linear-gradient(135deg, var(--t) 0 6px, #fff 6px 12px); border: 1px solid var(--line); }
.thumb.is-gold { background: repeating-linear-gradient(135deg, #F6EBD3 0 6px, #fff 6px 12px); border-color: #EFD9AE; }
.status { font-size: 11px; font-weight: 800; padding: 4px 8px; border-radius: 999px; white-space: nowrap; background: var(--soft); color: var(--muted); }
.status-belum_redeem { background: #FBF1DE; color: var(--gold-edge); }
.status-diajukan, .status-perlu_finance, .status-diproses, .status-dikirim { background: #E3EEF9; color: #2B5B8A; }
.status-selesai { background: #E2F1E7; color: var(--ok); }
.status-hangus { background: #F3E3E1; color: var(--danger); }
.empty-card { margin: 0; padding: 16px; border-radius: 16px; background: #fff; border: 1px dashed var(--line); font-size: 14px; line-height: 1.5; color: var(--muted); }

.home-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.btn-outline { display: grid; place-items: center; text-decoration: none; background: #fff; color: var(--ink); border: 1.5px solid var(--line); }
.btn-off { display: grid; place-items: center; background: #EFE7DC; color: #8C7D71; cursor: not-allowed; }
.home-actions .btn-brand { display: grid; place-items: center; text-decoration: none; }
```

- [ ] **Step 9: Jalankan test, pastikan lulus**

Run: `cd apps/amura && php artisan test --filter=CustomerAreaTest`
Expected: PASS (7 test).

- [ ] **Step 10: Jalankan suite Amura penuh**

Run: `cd apps/amura && php artisan test`
Expected: semua lulus.

- [ ] **Step 11: Commit**

```bash
git add apps/amura
git commit -m "feat(amura): perangkat pelanggan diingat 30 hari dan Beranda poin

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 7: Hadiahku, voucher, dan Redeem via WhatsApp

**Files:**
- Modify: `apps/amura/app/Http/Controllers/Customer/RewardController.php`
- Create: `apps/amura/app/Support/WhatsAppLink.php`
- Create: `apps/amura/resources/views/customer/rewards.blade.php`, `apps/amura/resources/views/customer/reward.blade.php`
- Modify: `packages/core/config/loyalty.php`, `apps/amura/.env.example`, `apps/amura/public/css/play.css`
- Test: `apps/amura/tests/Feature/CustomerAreaTest.php`

**Interfaces:**
- Consumes: `RememberedCustomer` (Task 6), `ClaimFlow::request()`, `ClaimFlow::label()`, `ClaimFlow::eventLabel()`, `ClaimFlow::IN_PROGRESS`, `ClaimFlow::DONE` (Task 3).
- Produces: `WhatsAppLink::number(): ?string` (format `62…`), `WhatsAppLink::message(Claim, Customer): string`, `WhatsAppLink::url(Claim, Customer): ?string`; config `loyalty.cs_whatsapp`.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `apps/amura/tests/Feature/CustomerAreaTest.php` (tambah `use LoyaltyNew\Core\Models\Claim;` dan `use LoyaltyNew\Core\Prizes\ClaimService;` di atas):
```php
    private function claimFor(Customer $customer, string $status = 'belum_redeem', string $name = 'Serum 20 ml'): Claim
    {
        $claim = Claim::create([
            'brand' => config('app.brand'),
            'customer_id' => $customer->id,
            'source' => 'katalog',
            'prize_name' => $name,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => $status,
            'expires_at' => now()->addDays(14),
        ]);

        return $claim;
    }

    public function test_hadiahku_memisahkan_tab(): void
    {
        $customer = $this->customer();
        $this->claimFor($customer, 'belum_redeem', 'Pouch Exclusive');
        $this->claimFor($customer, 'diproses', 'Gold Serum');
        $this->claimFor($customer, 'hangus', 'Voucher Lama');

        $this->asCustomer($customer)->get('/hadiah')->assertOk()->assertSee('Pouch Exclusive')->assertDontSee('Gold Serum');
        $this->asCustomer($customer)->get('/hadiah?tab=proses')->assertOk()->assertSee('Gold Serum')->assertDontSee('Pouch Exclusive');
        $this->asCustomer($customer)->get('/hadiah?tab=selesai')->assertOk()->assertSee('Voucher Lama');
    }

    public function test_voucher_menampilkan_nama_dan_nomor_pemenang(): void
    {
        $customer = $this->customer();
        $claim = $this->claimFor($customer);

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('Sari')
            ->assertSee('081234567890')
            ->assertSee($claim->claim_code)
            ->assertSee('Redeem sekarang');
    }

    public function test_voucher_milik_pelanggan_lain_404(): void
    {
        $owner = $this->customer();
        $claim = $this->claimFor($owner);
        $other = $this->customer('081200000999', 'Budi');

        $this->asCustomer($other)->get("/hadiah/{$claim->claim_code}")->assertNotFound();
        $this->asCustomer($other)->post("/hadiah/{$claim->claim_code}/redeem")->assertNotFound();
    }

    public function test_redeem_mengubah_status_dan_menampilkan_link_wa(): void
    {
        config(['loyalty.cs_whatsapp' => '0811 4000 2626']);
        $customer = $this->customer();
        $claim = $this->claimFor($customer);

        $this->asCustomer($customer)->post("/hadiah/{$claim->claim_code}/redeem")
            ->assertRedirect(route('rewards.show', $claim->claim_code));
        $this->assertSame('diajukan', $claim->fresh()->status);

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('https://wa.me/6281140002626', false)
            ->assertSee('Redeem diajukan');
    }

    public function test_redeem_ganda_tidak_error(): void
    {
        $customer = $this->customer();
        $claim = $this->claimFor($customer);

        $this->asCustomer($customer)->post("/hadiah/{$claim->claim_code}/redeem");
        $this->asCustomer($customer)->post("/hadiah/{$claim->claim_code}/redeem")
            ->assertRedirect(route('rewards.show', $claim->claim_code));

        // Klaim dibuat langsung (tanpa event awal), jadi hanya ada satu event: diajukan.
        $this->assertSame(1, $claim->events()->count());
    }

    public function test_tanpa_nomor_cs_tombol_wa_disembunyikan(): void
    {
        config(['loyalty.cs_whatsapp' => null]);
        $customer = $this->customer();
        $claim = $this->claimFor($customer, 'diajukan');

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('Nomor CS belum diatur')
            ->assertDontSee('wa.me', false);
    }

    public function test_klaim_ditolak_menampilkan_alasan(): void
    {
        $customer = $this->customer();
        $claim = $this->claimFor($customer);
        $claim->update(['rejected_reason' => 'Nomor pengirim tidak cocok']);

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('Kirim dari nomor pemenang');
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=CustomerAreaTest`
Expected: FAIL pada 7 test baru (method controller belum ada).

- [ ] **Step 3: Config nomor CS dan WhatsAppLink**

Di `packages/core/config/loyalty.php`, tambahkan di dalam array (setelah `'lockout' => [...]`):
```php

    /*
     | Nomor WhatsApp resmi CS brand ini (spec poin-redeem §10). Kosong: tombol WA
     | disembunyikan dan voucher menampilkan "Nomor CS belum diatur".
     */
    'cs_whatsapp' => env('CS_WHATSAPP'),
```

Di `apps/amura/.env.example`, tambahkan di akhir:
```
# Nomor WhatsApp resmi CS (boleh 08.. atau 62..). Wajib diisi sebelum rilis.
CS_WHATSAPP=
```

`apps/amura/app/Support/WhatsAppLink.php`:
```php
<?php

namespace App\Support;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;

/** Link wa.me ke CS resmi dengan pesan redeem yang sudah terisi (desain 2d). */
final class WhatsAppLink
{
    /** Nomor CS dalam format 62xxxx, atau null kalau belum diatur. */
    public static function number(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) config('loyalty.cs_whatsapp'));
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    public static function message(Claim $claim, Customer $customer): string
    {
        $brand = config('play.themes.'.config('app.brand').'.name', 'Amura');

        return implode("\n", [
            "Halo CS {$brand}, saya mau redeem hadiah.",
            "Hadiah: {$claim->prize_name}",
            "Kode: {$claim->claim_code}",
            'Nama: '.($customer->name ?: '-'),
            "Nomor pemenang: {$customer->phone}",
        ]);
    }

    public static function url(Claim $claim, Customer $customer): ?string
    {
        $number = self::number();

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode(self::message($claim, $customer)) : null;
    }
}
```

- [ ] **Step 4: Tulis RewardController**

`apps/amura/app/Http/Controllers/Customer/RewardController.php` (ganti seluruh isi):
```php
<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Support\WhatsAppLink;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;

final class RewardController extends Controller
{
    private const TABS = [
        'belum' => ['belum_redeem'],
        'proses' => ClaimFlow::IN_PROGRESS,
        'selesai' => ClaimFlow::DONE,
    ];

    public function index(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'belum';

        $claims = Claim::where('brand', config('app.brand'))
            ->where('customer_id', $customer->id)
            ->whereIn('status', self::TABS[$tab])
            ->orderByDesc('id')
            ->get();

        return view('customer.rewards', ['claims' => $claims, 'tab' => $tab]);
    }

    public function show(Request $request, string $code)
    {
        $customer = $request->attributes->get('customer');
        $claim = $this->owned($request, $code);

        return view('customer.reward', [
            'claim' => $claim->load('events'),
            'customer' => $customer,
            'waUrl' => WhatsAppLink::url($claim, $customer),
            'waNumber' => WhatsAppLink::number(),
            'waMessage' => WhatsAppLink::message($claim, $customer),
        ]);
    }

    public function redeem(Request $request, string $code)
    {
        $claim = $this->owned($request, $code);

        // Tombol ditekan dua kali: klaim sudah diajukan, cukup tampilkan lagi.
        if ($claim->status === 'diajukan') {
            return redirect()->route('rewards.show', $code);
        }

        try {
            ClaimFlow::request($claim, $request->attributes->get('customer'));
        } catch (ClaimTransitionException $e) {
            return redirect()->route('rewards.show', $code)->withErrors(['redeem' => $e->getMessage()]);
        }

        return redirect()->route('rewards.show', $code)
            ->with('ok', 'Redeem diajukan. Kirim pesan ke WhatsApp CS dari nomor pemenang.');
    }

    private function owned(Request $request, string $code): Claim
    {
        return Claim::where('brand', config('app.brand'))
            ->where('customer_id', $request->attributes->get('customer')->id)
            ->where('claim_code', $code)
            ->firstOrFail();
    }
}
```

- [ ] **Step 5: Tulis view Hadiahku dan voucher**

`apps/amura/resources/views/customer/rewards.blade.php`:
```blade
@extends('layouts.play')

@section('title', 'Hadiahku · '.$theme['name'])

@section('content')
    <div class="page">
        <a class="back" href="{{ route('home') }}"><span aria-hidden="true">‹</span> Beranda</a>
        <h1>Hadiahku</h1>

        <nav class="tabs" aria-label="Status hadiah">
            @foreach (['belum' => 'Belum di-redeem', 'proses' => 'Sedang diproses', 'selesai' => 'Selesai & hangus'] as $key => $label)
                <a href="{{ route('rewards', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="claim-list">
            @forelse ($claims as $claim)
                @include('customer.partials.claim-row', ['claim' => $claim])
            @empty
                <p class="empty-card">
                    @if ($tab === 'belum')
                        Tidak ada hadiah yang menunggu redeem. Main Gatcha atau tukar poin di <a href="{{ route('catalog') }}">Katalog</a>.
                    @elseif ($tab === 'proses')
                        Belum ada hadiah yang sedang diproses CS.
                    @else
                        Belum ada hadiah yang selesai atau hangus.
                    @endif
                </p>
            @endforelse
        </div>
    </div>
@endsection
```

`apps/amura/resources/views/customer/reward.blade.php`:
```blade
@extends('layouts.play')

@section('title', $claim->prize_name.' · '.$theme['name'])

@section('content')
    <div class="page">
        <a class="back" href="{{ route('rewards') }}"><span aria-hidden="true">‹</span> Hadiahku</a>

        @if (session('ok'))
            <div class="notice" role="status">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="voucher {{ $claim->isBigPrize() ? 'is-gold' : '' }}" aria-label="Voucher hadiah">
            <div class="voucher-top">
                <span class="chip">{{ $claim->source === 'katalog' ? 'TUKAR POIN' : ($claim->isBigPrize() ? 'HADIAH UTAMA' : 'HADIAH GATCHA') }}</span>
                <span class="status status-{{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span>
            </div>
            <div class="voucher-name serif">{{ $claim->prize_name }}</div>
            <dl class="voucher-meta">
                <div><dt>Nama pemenang</dt><dd>{{ $customer->name ?: 'Nama belum tersedia' }}</dd></div>
                <div><dt>Nomor pemenang</dt><dd>{{ $customer->phone }}</dd></div>
                <div><dt>Kode</dt><dd class="voucher-code">{{ $claim->claim_code }}</dd></div>
                <div><dt>Redeem sebelum</dt><dd>{{ $claim->expires_at->translatedFormat('j F Y') }}</dd></div>
            </dl>
        </section>

        @if ($claim->status === 'belum_redeem')
            @if ($claim->rejected_reason)
                <div class="alert" role="alert"><b>Klaim sebelumnya ditolak.</b> {{ $claim->rejected_reason }}. Kirim dari nomor pemenang.</div>
            @endif
            @if ($claim->daysLeft() > 0)
                <form method="POST" action="{{ route('rewards.redeem', $claim->claim_code) }}" class="redeem-form">
                    @csrf
                    <button type="submit" class="btn btn-brand">Redeem sekarang</button>
                    <small>Setelah ditekan, kirim pesan ke WhatsApp CS dari nomor {{ $customer->phone }}.</small>
                </form>
            @endif
        @elseif ($claim->status === 'diajukan')
            @if ($waUrl)
                <a class="btn btn-wa" href="{{ $waUrl }}" target="_blank" rel="noopener">Buka WhatsApp CS {{ $theme['name'] }}</a>
                <details class="fallback">
                    <summary>WhatsApp tidak terbuka?</summary>
                    <ol>
                        <li>
                            Simpan nomor CS {{ $theme['name'] }}: <b>{{ $waNumber }}</b>
                            <button type="button" class="copy-btn" data-copy="{{ $waNumber }}">Salin nomor</button>
                        </li>
                        <li>
                            Kirim pesan ini dari nomor pemenang:
                            <pre class="wa-text">{{ $waMessage }}</pre>
                            <button type="button" class="copy-btn" data-copy="{{ $waMessage }}">Salin pesan</button>
                        </li>
                    </ol>
                </details>
            @else
                <div class="alert" role="alert">Nomor CS belum diatur. Hubungi admin {{ $theme['name'] }}.</div>
            @endif
        @endif

        <section aria-label="Status redeem">
            <h2>Status</h2>
            <ol class="timeline">
                @foreach ($claim->events as $event)
                    <li>
                        <b>{{ \LoyaltyNew\Core\Prizes\ClaimFlow::eventLabel($event->from_status, $event->to_status) }}</b>
                        <small>
                            {{ $event->created_at->translatedFormat('j M, H.i') }}
                            @if ($event->to_status === 'dikirim' && $claim->shipping_awb) · Resi {{ $claim->shipping_awb }} @endif
                        </small>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.copy-btn').forEach(btn => btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(btn.dataset.copy);
            btn.textContent = 'Tersalin';
        } catch (e) {
            btn.textContent = 'Salin manual ya';
        }
    }));
</script>
@endpush
```

- [ ] **Step 6: Tambah gaya voucher, tab, timeline**

Tambahkan di `apps/amura/public/css/play.css` setelah blok area pelanggan dari Task 6:
```css
.tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; }
.tabs a { flex: none; min-height: 40px; display: grid; place-items: center; padding: 0 14px; border-radius: 999px; background: #fff; border: 1px solid var(--line); color: var(--muted); font-size: 13px; font-weight: 800; text-decoration: none; }
.tabs a[aria-current="page"] { background: var(--ink); border-color: var(--ink); color: #fff; }

.voucher { border-radius: 20px; padding: 18px; background: #fff; border: 1.5px dashed var(--p); display: flex; flex-direction: column; gap: 12px; }
.voucher.is-gold { border-color: #C99A3A; background: #FFFCF5; }
.voucher-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.voucher .chip { font-size: 11px; font-weight: 800; letter-spacing: .04em; color: var(--p); }
.voucher.is-gold .chip { color: var(--gold-edge); }
.voucher-name { font-size: 30px; line-height: 1.1; overflow-wrap: anywhere; }
.voucher-meta { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; }
.voucher-meta dt { font-size: 11px; color: var(--muted); font-weight: 700; }
.voucher-meta dd { margin: 2px 0 0; font-size: 14px; font-weight: 800; overflow-wrap: anywhere; }
.voucher-code { letter-spacing: .1em; }
.redeem-form { display: flex; flex-direction: column; gap: 8px; }
.redeem-form small { font-size: 12px; color: var(--muted); text-align: center; }
.fallback { border-radius: 14px; background: #fff; border: 1px solid var(--line); padding: 12px 14px; font-size: 13px; line-height: 1.5; }
.fallback summary { min-height: 32px; font-weight: 800; cursor: pointer; }
.fallback ol { margin: 8px 0 0; padding-left: 18px; display: flex; flex-direction: column; gap: 10px; }
.wa-text { margin: 6px 0; white-space: pre-wrap; font-family: inherit; font-size: 13px; background: var(--soft); border-radius: 10px; padding: 10px; }
.copy-btn { min-height: 40px; padding: 0 12px; border-radius: 10px; border: 1.5px solid var(--ink); background: #fff; color: var(--ink); font: 800 13px/1 inherit; font-family: inherit; cursor: pointer; }
.timeline { list-style: none; margin: 10px 0 0; padding: 0 0 0 18px; border-left: 2px solid var(--line); display: flex; flex-direction: column; gap: 14px; }
.timeline li { position: relative; }
.timeline li::before { content: ""; position: absolute; left: -25px; top: 3px; width: 12px; height: 12px; border-radius: 50%; background: var(--p); }
.timeline b { display: block; font-size: 14px; }
.timeline small { font-size: 12px; color: var(--muted); }
```

- [ ] **Step 7: Jalankan test, pastikan lulus**

Run: `cd apps/amura && php artisan test --filter=CustomerAreaTest`
Expected: PASS (14 test).

- [ ] **Step 8: Commit**

```bash
git add apps/amura packages/core/config/loyalty.php
git commit -m "feat(amura): Hadiahku, voucher pemenang, dan redeem lewat WhatsApp CS

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 8: Halaman Katalog dan tukar poin

**Files:**
- Modify: `apps/amura/app/Http/Controllers/Customer/CatalogController.php`
- Create: `apps/amura/resources/views/customer/catalog.blade.php`
- Modify: `apps/amura/public/css/play.css`
- Test: `apps/amura/tests/Feature/CustomerAreaTest.php`

**Interfaces:**
- Consumes: `RewardCatalogService::active()`, `RewardCatalogService::exchange()`, `CatalogUnavailableException` (Task 5), `InsufficientPointsException`, `LoyaltySettings`, `PointWallet` (Task 2).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `CustomerAreaTest.php` (tambah `use LoyaltyNew\Core\Models\RewardCatalog;`):
```php
    private function catalogItem(string $name = 'Voucher Free Ongkir', int $cost = 1000, ?int $stock = null)
    {
        $catalog = RewardCatalog::firstOrCreate(
            ['brand' => config('app.brand'), 'period' => now()->format('Y-m')],
            ['published_at' => now()],
        );

        return $catalog->items()->create(['name' => $name, 'points_cost' => $cost, 'stock' => $stock]);
    }

    public function test_katalog_menampilkan_item_dan_kekurangan_poin(): void
    {
        $customer = $this->customer();
        (new PointWallet($customer))->grant(600, 12);
        $this->catalogItem('Pouch Exclusive', 500);
        $this->catalogItem('Voucher Free Ongkir', 1000);

        $this->asCustomer($customer)->get('/katalog')
            ->assertOk()
            ->assertSee('Pouch Exclusive')
            ->assertSee('Kurang 400 poin');
    }

    public function test_katalog_kosong_menampilkan_pesan(): void
    {
        $this->asCustomer($this->customer())->get('/katalog')->assertOk()->assertSee('Katalog bulan ini segera hadir');
    }

    public function test_tukar_berhasil_arah_ke_voucher(): void
    {
        $customer = $this->customer();
        (new PointWallet($customer))->grant(1200, 12);
        $item = $this->catalogItem();

        $response = $this->asCustomer($customer)->post("/katalog/{$item->id}/tukar");

        $claim = Claim::firstOrFail();
        $response->assertRedirect(route('rewards.show', $claim->claim_code));
        $this->assertSame(200, (new PointWallet($customer))->balance());
    }

    public function test_tukar_poin_kurang_kembali_dengan_pesan(): void
    {
        $customer = $this->customer();
        (new PointWallet($customer))->grant(600, 12);
        $item = $this->catalogItem();

        $this->asCustomer($customer)->from('/katalog')->post("/katalog/{$item->id}/tukar")
            ->assertRedirect('/katalog')
            ->assertSessionHasErrors('catalog');

        $this->assertSame(0, Claim::count());
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=CustomerAreaTest`
Expected: FAIL pada 4 test baru.

- [ ] **Step 3: Tulis CatalogController**

`apps/amura/app/Http/Controllers/Customer/CatalogController.php` (ganti seluruh isi):
```php
<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Points\InsufficientPointsException;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\CatalogUnavailableException;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\RewardCatalogService;

final class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $catalog = RewardCatalogService::active((string) config('app.brand'));

        return view('customer.catalog', [
            'catalog' => $catalog,
            'items' => $catalog ? $catalog->items()->where('active', true)->get() : collect(),
            'balance' => (new PointWallet($customer))->balance(),
            'minimum' => LoyaltySettings::current()['min_redeem'],
            'redeemBy' => now()->addDays(ClaimService::CATALOG_EXPIRY_DAYS),
        ]);
    }

    public function exchange(Request $request, string $item)
    {
        try {
            $claim = RewardCatalogService::exchange($request->attributes->get('customer'), (int) $item);
        } catch (InsufficientPointsException|CatalogUnavailableException $e) {
            return back()->withErrors(['catalog' => $e->getMessage()]);
        }

        return redirect()->route('rewards.show', $claim->claim_code)
            ->with('ok', 'Poin berhasil ditukar. Redeem hadiah ini sebelum batas waktunya.');
    }
}
```

- [ ] **Step 4: Tulis view Katalog**

`apps/amura/resources/views/customer/catalog.blade.php`:
```blade
@extends('layouts.play')

@section('title', 'Katalog · '.$theme['name'])

@section('content')
    <div class="page">
        <a class="back" href="{{ route('home') }}"><span aria-hidden="true">‹</span> Beranda</a>
        <div class="catalog-head">
            <h1>Tukar poin</h1>
            <span class="balance-chip">Saldo <b>{{ number_format($balance, 0, ',', '.') }}</b> poin</span>
        </div>

        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        @if (! $catalog || $items->isEmpty())
            <p class="empty-card">Katalog bulan ini segera hadir. Poinmu tetap aman.</p>
        @else
            <p class="catalog-note">Minimal tukar {{ number_format($minimum, 0, ',', '.') }} poin. Ongkir hadiah ditanggung penerima.</p>
            <div class="catalog-list">
                @foreach ($items as $item)
                    @php
                        $short = max($minimum, $item->points_cost) - $balance;
                    @endphp
                    <article class="reward-item">
                        <span class="thumb" aria-hidden="true"></span>
                        <div class="reward-body">
                            <b>{{ $item->name }}</b>
                            <span class="reward-cost">{{ number_format($item->points_cost, 0, ',', '.') }} poin</span>
                            @if ($item->stock !== null)
                                <small>Sisa {{ $item->remaining() }}</small>
                            @endif
                        </div>
                        @if (! $item->inStock())
                            <span class="btn-sm is-off">Stok habis</span>
                        @elseif ($short > 0)
                            <span class="btn-sm is-off">Kurang {{ number_format($short, 0, ',', '.') }} poin</span>
                        @else
                            <button type="button" class="btn-sm" data-open="confirm-{{ $item->id }}" aria-expanded="false" aria-controls="confirm-{{ $item->id }}">Tukar</button>
                        @endif

                        <form id="confirm-{{ $item->id }}" class="reward-confirm" method="POST" action="{{ route('catalog.exchange', $item->id) }}" hidden>
                            @csrf
                            <p>Poin dipotong {{ number_format($item->points_cost, 0, ',', '.') }}. Hadiah masuk Hadiahku, redeem sebelum {{ $redeemBy->translatedFormat('j F Y') }}.</p>
                            <div class="reward-confirm-actions">
                                <button type="submit" class="btn-sm">Ya, tukar</button>
                                <button type="button" class="btn-sm is-ghost" data-close="confirm-{{ $item->id }}">Batal</button>
                            </div>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-open]').forEach(btn => btn.addEventListener('click', () => {
        const panel = document.getElementById(btn.dataset.open);
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        panel.querySelector('button[type="submit"]').focus();
    }));
    document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => {
        const panel = document.getElementById(btn.dataset.close);
        panel.hidden = true;
        const opener = document.querySelector(`[data-open="${btn.dataset.close}"]`);
        opener?.setAttribute('aria-expanded', 'false');
        opener?.focus();
    }));
    document.querySelectorAll('.reward-confirm').forEach(form => form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"]').disabled = true;
    }));
</script>
@endpush
```

- [ ] **Step 5: Tambah gaya katalog**

Tambahkan di `apps/amura/public/css/play.css`:
```css
.catalog-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.balance-chip { font-size: 13px; padding: 8px 12px; border-radius: 999px; background: var(--t); color: var(--d); }
.catalog-note { margin: 0; font-size: 12px; color: var(--muted); }
.catalog-list { display: flex; flex-direction: column; gap: 10px; }
.reward-item { display: grid; grid-template-columns: 44px 1fr auto; align-items: center; gap: 12px; padding: 12px; border-radius: 16px; background: #fff; border: 1px solid var(--line); }
.reward-body { min-width: 0; }
.reward-body b { display: block; font-size: 15px; overflow-wrap: anywhere; }
.reward-cost { font-size: 13px; font-weight: 800; color: var(--p); }
.reward-body small { display: block; font-size: 12px; color: var(--muted); }
.btn-sm { min-height: 44px; padding: 0 14px; border-radius: 12px; border: 0; background: var(--p); color: #fff; font: 800 14px/1 inherit; font-family: inherit; cursor: pointer; display: grid; place-items: center; text-align: center; }
.btn-sm.is-off { background: #EFE7DC; color: #6F6259; font-size: 12px; cursor: default; }
.btn-sm.is-ghost { background: transparent; color: var(--ink); border: 1.5px solid var(--line); }
.btn-sm[disabled] { opacity: .6; }
.reward-confirm { grid-column: 1 / -1; border-top: 1px solid var(--line); padding-top: 10px; }
.reward-confirm p { margin: 0 0 10px; font-size: 13px; line-height: 1.5; }
.reward-confirm-actions { display: flex; gap: 8px; }
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `cd apps/amura && php artisan test --filter=CustomerAreaTest`
Expected: PASS (18 test).

- [ ] **Step 7: Commit**

```bash
git add apps/amura
git commit -m "feat(amura): katalog bulanan dan tukar poin untuk pelanggan

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 9: Hasil Gatcha menampilkan poin masuk saldo dan tautan voucher

**Files:**
- Modify: `apps/amura/resources/views/play/board.blade.php`
- Modify: `apps/amura/public/js/gatcha.js`
- Modify: `apps/amura/public/css/play.css`

**Interfaces:**
- Consumes: JSON `take`/`reveal` dan payload `taken` dari Task 4 (`points`, `balance`, `prize_name`, `claim_code`, `claim_expires_at`, `revealed[].points`, `revealed[].name`). Route `home`, `rewards`, `rewards.show` (Task 6–7).

- [ ] **Step 1: Tambah URL ke payload board dan ubah tautan keluar**

Di `apps/amura/resources/views/play/board.blade.php`, pada array `$payload` ganti `'formUrl' => route('play.form'),` dengan:
```php
        'formUrl' => route('play.form'),
        'homeUrl' => route('home'),
        'rewardsUrl' => route('rewards'),
        'voucherUrl' => url('/hadiah').'/',
```
Ganti `<a class="icon-btn" href="{{ route('play.form') }}" aria-label="Keluar dari Gatcha">` dengan `<a class="icon-btn" href="{{ route('home') }}" aria-label="Kembali ke Beranda">`.
Ganti `<a class="btn-ghost" href="{{ route('play.form') }}" id="laterLink">Nanti saja, kesempatan tersimpan</a>` dengan `<a class="btn-ghost" href="{{ route('home') }}" id="laterLink">Nanti saja, kesempatan tersimpan</a>`.

- [ ] **Step 2: Ubah label tier poin di gatcha.js**

Di `apps/amura/public/js/gatcha.js`, ganti fungsi
```js
    const tierOf = item => TIER[item.tier] || { cls: 'tier-ivory', chip: item.label.toUpperCase(), token: item.label, v: item.label };
    const prizeName = item => item.name || tierOf(item).v;
```
dengan
```js
    const tierOf = item => TIER[item.tier] || { cls: 'tier-ivory', chip: item.label.toUpperCase(), token: item.label, v: item.label };
    const prizeName = item => (item.tier === 'poin' && item.points ? `+${item.points} poin` : item.name || tierOf(item).v);
```
Di `setCardPrize`, ganti baris
```js
        card.querySelector('.token').textContent = item.tier === 'hadiah_besar' && item.name ? item.name : t.token;
```
dengan
```js
        card.querySelector('.token').textContent = item.tier === 'poin' && item.points
            ? `+${item.points}`
            : (item.tier === 'hadiah_besar' && item.name ? item.name : t.token);
```

- [ ] **Step 3: Simpan poin dan saldo di state hasil**

Di `finishTake(res)`, ganti blok `state.result = { ... };` dan baris `state.taken.push(...)` dengan:
```js
        state.result = {
            chosen,
            burned: res.revealed.filter(i => i.slot !== res.chosen),
            code: res.claim_code,
            expires: res.claim_expires_at,
            points: res.points,
            balance: res.balance,
            chance: data.chanceTotal - res.chance_left,
        };
        state.taken.push({ ...chosen, claim_code: res.claim_code, claim_expires_at: res.claim_expires_at, points: res.points ?? chosen.points });
```

- [ ] **Step 4: Kartu hasil untuk poin dan voucher**

Ganti fungsi `claimNote` dengan:
```js
    function claimNote(item, expires) {
        const until = formatDate(expires);
        if (item.tier === 'hadiah_besar') {
            return `Hadiah utama masuk Hadiahku. Redeem sebelum ${until}, lalu CS dan Finance memprosesnya.`;
        }
        return `Masuk Hadiahku. Redeem sebelum ${until} lewat WhatsApp CS ${data.brand}.`;
    }
```

Di `showResult()`, ganti bagian dari `const card = el('div', 'win-card');` sampai `view.appendChild(win);` dengan:
```js
        const card = el('div', 'win-card');
        card.appendChild(el('span', 'chip', t.chip));
        card.appendChild(el('div', 'name', prizeName({ ...chosen, points: state.result.points ?? chosen.points })));
        if (chosen.tier === 'poin') {
            const saldo = el('div', 'saldo');
            saldo.append('Saldo poinmu ');
            const num = el('b', null, String(Math.max(0, (state.result.balance ?? 0) - (state.result.points ?? 0))));
            saldo.append(num, ' poin');
            card.appendChild(saldo);
            card.appendChild(el('div', 'note', 'Poin langsung masuk saldo dan bisa ditukar di Katalog.'));
            win.appendChild(card);
            view.appendChild(win);
            countUp(num, state.result.balance ?? 0);
            coinBurst(card, saldo);
        } else {
            if (code) {
                card.appendChild(el('div', 'note', 'Kode klaim'));
                card.appendChild(el('div', 'code', code));
            }
            card.appendChild(el('div', 'note', claimNote(chosen, expires)));
            if (code) {
                const link = el('a', 'voucher-link', 'Lihat voucher');
                link.href = data.voucherUrl + encodeURIComponent(code);
                card.appendChild(link);
            }
            win.appendChild(card);
            view.appendChild(win);
        }
```

Tambahkan dua fungsi baru tepat sebelum `function showDone()`:
```js
    // Angka saldo bergulir 0,9 s (spesifikasi Poin).
    function countUp(node, target) {
        const start = Number(node.textContent) || 0;
        if (reduced) { node.textContent = target.toLocaleString('id-ID'); return; }
        const t0 = performance.now();
        const step = now => {
            const p = Math.min(1, (now - t0) / 900);
            const eased = 1 - Math.pow(1 - p, 3);
            node.textContent = Math.round(start + (target - start) * eased).toLocaleString('id-ID');
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    // 8 koin terbang melengkung dari kartu ke angka saldo, jeda 90 ms (spesifikasi Poin).
    function coinBurst(fromEl, toEl) {
        if (reduced) return;
        const fx = $('fx');
        const s = stage.getBoundingClientRect();
        const a = fromEl.getBoundingClientRect();
        const b = toEl.getBoundingClientRect();
        for (let i = 0; i < 8; i++) {
            const coin = el('span', 'fly-coin');
            coin.style.left = `${a.left - s.left + a.width / 2}px`;
            coin.style.top = `${a.top - s.top + a.height * .35}px`;
            fx.appendChild(coin);
            const dx = b.left - a.left - a.width / 2 + b.width / 2;
            const dy = b.top - a.top - a.height * .35 + b.height / 2;
            coin.animate([
                { transform: 'translate(-50%, -50%) scale(.6)', opacity: 0 },
                { transform: `translate(calc(-50% + ${dx * .4}px), calc(-50% + ${dy * .4 - 60}px)) scale(1)`, opacity: 1, offset: .45 },
                { transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) scale(.5)`, opacity: 0 },
            ], { duration: 550, delay: 250 + i * 90, easing: 'cubic-bezier(.3, .7, .4, 1)', fill: 'backwards' })
                .finished.then(() => { coin.remove(); buzz(8); if (i === 0) sound.tone(1320, .08, 'triangle', .08); });
        }
    }
```

- [ ] **Step 5: Ringkasan akhir menautkan ke Hadiahku**

Di `showDone()`, ganti blok `if (item.claim_code) { ... }` di dalam `state.taken.forEach` dengan:
```js
            if (item.tier === 'poin') {
                body.appendChild(el('div', 'sub', 'Masuk saldo poin'));
            } else if (item.claim_code) {
                const sub = el('div', 'sub', 'Kode ');
                sub.appendChild(el('b', null, item.claim_code));
                sub.appendChild(document.createTextNode(` · redeem sebelum ${formatDate(item.claim_expires_at)}`));
                body.appendChild(sub);
            }
```
lalu ganti blok akhir
```js
        if (state.taken.length) {
            view.appendChild(el('div', 'empty-note', `Kirim kode klaim ke WhatsApp CS ${data.brand}. CS mencocokkan kode dengan nomor HP-mu, lalu memproses hadiahnya.`));
        }

        $('nextBtn').hidden = true;
        $('laterLink').hidden = false;
        $('laterLink').textContent = 'Kembali ke halaman awal';
```
dengan
```js
        if (state.taken.some(i => i.tier !== 'poin')) {
            view.appendChild(el('div', 'empty-note', 'Hadiah nyata ada di Hadiahku. Tekan Redeem di sana, lalu kirim pesan ke WhatsApp CS dari nomor pemenang.'));
        }

        $('nextBtn').hidden = false;
        $('nextBtn').textContent = 'Lihat Hadiahku';
        $('nextBtn').onclick = () => { location.href = data.rewardsUrl; };
        $('laterLink').hidden = false;
        $('laterLink').textContent = 'Ke Beranda';
```
Terakhir, ganti handler `$('nextBtn').addEventListener('click', () => { ... });` dengan:
```js
    $('nextBtn').addEventListener('click', () => {
        if ($('nextBtn').onclick) return;
        if (chanceLeft() > 0) enterPick(false);
        else showDone();
    });
```

- [ ] **Step 6: Gaya saldo, koin, dan tautan voucher**

Tambahkan di `apps/amura/public/css/play.css` sebelum blok `/* ── Efek hadiah utama`:
```css
.win-card .saldo { margin-top: 8px; font-size: 14px; color: var(--muted); }
.win-card .saldo b { font-size: 22px; color: var(--p); }
.voucher-link { display: grid; place-items: center; min-height: 44px; margin-top: 12px; border-radius: 12px; background: var(--p); color: #fff; font-weight: 800; text-decoration: none; }
.fly-coin { position: absolute; width: 18px; height: 18px; border-radius: 50%; background: var(--gold); box-shadow: inset 0 -2px 0 var(--gold-edge), 0 0 8px rgba(245, 218, 154, .7); }
```

- [ ] **Step 7: Uji jalan di browser**

Jalankan server `cd apps/amura && php artisan serve --port=8001`, lalu buka `http://127.0.0.1:8001/play` (ukuran 390×844) dan catat hasilnya:
- Resi `TEST-UI-1` + HP `81299887766` → papan Gatcha, cookie `loyalty_customer` terpasang.
- Pilih 3, buka, ambil kado poin → kartu "+200 poin", saldo bergulir, koin terbang, tanpa kode klaim.
- Ambil kado voucher → kartu nama item, kode, tautan "Lihat voucher" membuka `/hadiah/{kode}`.
- Setelah kesempatan habis → "Lihat Hadiahku" membuka `/hadiah`, "Ke Beranda" membuka `/`.
- Console tanpa error.

Jalankan juga `cd apps/amura && php artisan test` → semua lulus.

- [ ] **Step 8: Commit**

```bash
git add apps/amura/resources/views/play/board.blade.php apps/amura/public
git commit -m "feat(amura): hasil Gatcha menampilkan poin masuk saldo dan tautan voucher

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 10: Dashboard CS: antrian redeem, cocokkan nomor, pengiriman

**Files:**
- Modify (tulis ulang): `apps/amura/resources/views/layouts/admin.blade.php`, `apps/amura/app/Http/Controllers/Admin/CsClaimController.php`, `apps/amura/resources/views/admin/claims.blade.php`
- Create: `apps/amura/app/Http/Controllers/Admin/ShippingController.php`, `apps/amura/resources/views/admin/claim.blade.php`, `apps/amura/resources/views/admin/shipping.blade.php`
- Modify: `apps/amura/routes/web.php`
- Modify (test): `apps/amura/tests/Feature/CsClaimTest.php`, `apps/amura/tests/Feature/BugfixRegressionTest.php`
- Test: `apps/amura/tests/Feature/ShippingTest.php`

**Interfaces:**
- Consumes: `ClaimFlow::confirm()`, `reject()`, `ship()`, `deliver()`, `phoneMatches()`, `counts()`, `label()`, `eventLabel()` (Task 3).
- Produces: route `admin.claims`, `admin.claims.show`, `admin.claims.confirm`, `admin.claims.reject`, `admin.shipping`, `admin.shipping.ship`, `admin.shipping.deliver`. Route `admin.claims.resolve` **dihapus**. Layout admin memakai `ClaimFlow::counts()` untuk angka menu dan menampilkan menu Katalog poin (route `admin.catalog`, dibuat di Task 12; sampai Task 12, bungkus tautan dengan `@if (Route::has('admin.catalog'))`).

- [ ] **Step 1: Tulis ulang test CS**

Di `apps/amura/tests/Feature/CsClaimTest.php`, hapus method `test_resolve_claim`, `test_cs_tidak_bisa_set_selesai_untuk_hadiah_besar`, `test_cs_tidak_bisa_set_diproses_untuk_hadiah_besar`, `test_cs_bisa_set_hangus_untuk_hadiah_besar`, `test_cs_bisa_set_selesai_untuk_hadiah_biasa`, dan `test_dashboard_brand_scoped`. Tambahkan `use LoyaltyNew\Core\Prizes\ClaimFlow;` lalu tambahkan:
```php
    private function submitted(string $tier = 'voucher', string $brand = 'amura'): Claim
    {
        $prize = $this->prize($tier, $brand);
        $claim = ClaimService::create($prize->session, $prize);
        $claim->update(['status' => 'diajukan', 'redeem_requested_at' => now()]);

        return $claim->fresh();
    }

    private function winnerPhone(Claim $claim): string
    {
        return \LoyaltyNew\Core\Models\Customer::withoutGlobalScopes()->whereKey($claim->customer_id)->value('phone');
    }

    public function test_antrian_hanya_klaim_diajukan_brand_ini(): void
    {
        $waiting = $this->submitted();
        $fresh = ClaimService::create(($p = $this->prize())->session, $p);
        $reglow = $this->submitted('voucher', 'reglow');

        $this->actingAs($this->csUser())->get('/admin/claims')
            ->assertOk()
            ->assertSee($waiting->claim_code)
            ->assertDontSee($fresh->claim_code)
            ->assertDontSee($reglow->claim_code);
    }

    public function test_cari_kode_menemukan_klaim_status_apa_pun(): void
    {
        $prize = $this->prize();
        $claim = ClaimService::create($prize->session, $prize);

        $this->actingAs($this->csUser())->get('/admin/claims?q='.$claim->claim_code)
            ->assertOk()
            ->assertSee($claim->claim_code);
    }

    public function test_detail_menampilkan_nama_dan_nomor_pemenang(): void
    {
        $claim = $this->submitted();

        $this->actingAs($this->csUser())->get('/admin/claims/'.$claim->claim_code)
            ->assertOk()
            ->assertSee('Pelanggan')
            ->assertSee($this->winnerPhone($claim));
    }

    public function test_konfirmasi_nomor_cocok(): void
    {
        $cs = $this->csUser();
        $claim = $this->submitted();

        $this->actingAs($cs)
            ->post('/admin/claims/'.$claim->claim_code.'/confirm', ['sender_phone' => $this->winnerPhone($claim), 'note' => 'alamat dicek'])
            ->assertRedirect(route('admin.claims'));

        $this->assertSame('diproses', $claim->fresh()->status);
        $this->assertSame($cs->id, $claim->fresh()->handled_by);
    }

    public function test_konfirmasi_nomor_beda_ditolak(): void
    {
        $claim = $this->submitted();

        $this->actingAs($this->csUser())
            ->from('/admin/claims/'.$claim->claim_code)
            ->post('/admin/claims/'.$claim->claim_code.'/confirm', ['sender_phone' => '089999999999'])
            ->assertSessionHasErrors('status');

        $this->assertSame('diajukan', $claim->fresh()->status);
    }

    public function test_tolak_nomor_beda_kembali_ke_belum_redeem(): void
    {
        $claim = $this->submitted();

        $this->actingAs($this->csUser())
            ->post('/admin/claims/'.$claim->claim_code.'/reject', ['sender_phone' => '089999999999'])
            ->assertRedirect(route('admin.claims'));

        $this->assertSame('belum_redeem', $claim->fresh()->status);
        $this->assertSame(ClaimFlow::REJECT_REASON, $claim->fresh()->rejected_reason);
    }

    public function test_hadiah_utama_dikonfirmasi_ke_finance(): void
    {
        $claim = $this->submitted('hadiah_besar');

        $this->actingAs($this->csUser())
            ->post('/admin/claims/'.$claim->claim_code.'/confirm', ['sender_phone' => $this->winnerPhone($claim)]);

        $this->assertSame('perlu_finance', $claim->fresh()->status);
    }

    public function test_klaim_brand_lain_404(): void
    {
        $reglow = $this->submitted('voucher', 'reglow');

        $this->actingAs($this->csUser())->get('/admin/claims/'.$reglow->claim_code)->assertNotFound();
    }
```

- [ ] **Step 2: Tulis ulang test regresi yang memakai `resolve`**

Di `apps/amura/tests/Feature/BugfixRegressionTest.php`:

Ganti `test_2_cs_menghanguskan_klaim_hadiah_utama_kuota_kembali` dengan:
```php
    public function test_2_klaim_hadiah_utama_hangus_lewat_flow_kuota_kembali_sekali(): void
    {
        $this->bigPrizeCatalog(cap: 1);
        $session = $this->start();
        $this->pickBigPrizeRound($session);
        $this->postJson("/play/{$session->session_token}/reveal")->assertOk();
        $claim = Claim::firstOrFail();

        \LoyaltyNew\Core\Prizes\ClaimFlow::expire($claim);
        $this->assertSame(0, $this->usedUnits());

        $this->expectException(\LoyaltyNew\Core\Prizes\ClaimTransitionException::class);
        try {
            \LoyaltyNew\Core\Prizes\ClaimFlow::expire($claim);
        } finally {
            $this->assertSame(0, $this->usedUnits());
        }
    }
```

Ganti keempat test `test_4_cs_tidak_bisa_mengubah_klaim_hangus`, `test_4_cs_tidak_bisa_mengubah_klaim_selesai`, `test_4_klaim_lewat_batas_hanya_bisa_dihanguskan`, `test_4_pesan_error_tampil_di_panel_admin` dengan:
```php
    public function test_4_cs_tidak_bisa_mengubah_klaim_hangus(): void
    {
        $claim = $this->claim('hangus', expired: true);

        $this->actingAs($this->staff('cs'))
            ->post("/admin/claims/{$claim->claim_code}/confirm", ['sender_phone' => '08123456789'])
            ->assertSessionHasErrors('status');

        $this->assertSame('hangus', $claim->fresh()->status);
    }

    public function test_4_cs_tidak_bisa_mengubah_klaim_selesai(): void
    {
        $claim = $this->claim('selesai');

        $this->actingAs($this->staff('cs'))
            ->post("/admin/claims/{$claim->claim_code}/reject", ['sender_phone' => '089999999999'])
            ->assertSessionHasErrors('status');

        $this->assertSame('selesai', $claim->fresh()->status);
    }

    public function test_4_klaim_diajukan_lewat_batas_tidak_bisa_dikonfirmasi(): void
    {
        $claim = $this->claim('diajukan', expired: true);

        $this->actingAs($this->staff('cs'))
            ->post("/admin/claims/{$claim->claim_code}/confirm", ['sender_phone' => '08123456789'])
            ->assertSessionHasErrors('status');

        $this->assertSame('diajukan', $claim->fresh()->status);
    }

    public function test_4_pesan_error_tampil_di_panel_admin(): void
    {
        $claim = $this->claim('hangus', expired: true);

        $this->actingAs($this->staff('cs'))
            ->from("/admin/claims/{$claim->claim_code}")
            ->followingRedirects()
            ->post("/admin/claims/{$claim->claim_code}/confirm", ['sender_phone' => '08123456789'])
            ->assertSee('tidak bisa diubah');
    }
```
Catatan: helper `claim()` di file ini memakai HP acak. Keempat test di atas tetap valid karena penolakan terjadi sebelum nomor dicek (status asal salah, atau klaim lewat batas).

- [ ] **Step 3: Tulis test pengiriman yang gagal**

`apps/amura/tests/Feature/ShippingTest.php`:
```php
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
```

- [ ] **Step 4: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter='CsClaimTest|ShippingTest|BugfixRegressionTest'`
Expected: FAIL (route `confirm`, `reject`, `shipping` belum ada).

- [ ] **Step 5: Ubah route CS**

Di `apps/amura/routes/web.php`, tambahkan `use App\Http\Controllers\Admin\ShippingController;` dan ganti grup `role:cs,super_admin` dengan:
```php
Route::middleware(['auth', 'role:cs,super_admin'])->prefix('admin')->group(function () {
    Route::get('/claims', [CsClaimController::class, 'index'])->name('admin.claims');
    Route::get('/claims/{code}', [CsClaimController::class, 'show'])->name('admin.claims.show');
    Route::post('/claims/{code}/confirm', [CsClaimController::class, 'confirm'])->name('admin.claims.confirm');
    Route::post('/claims/{code}/reject', [CsClaimController::class, 'reject'])->name('admin.claims.reject');
    Route::get('/shipping', [ShippingController::class, 'index'])->name('admin.shipping');
    Route::post('/shipping/{code}/ship', [ShippingController::class, 'ship'])->name('admin.shipping.ship');
    Route::post('/shipping/{code}/deliver', [ShippingController::class, 'deliver'])->name('admin.shipping.deliver');
});
```

- [ ] **Step 6: Tulis controller CS dan pengiriman**

`apps/amura/app/Http/Controllers/Admin/CsClaimController.php` (ganti seluruh isi):
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;

/** Antrian redeem WhatsApp (desain 3i): CS mencocokkan nomor pengirim dengan nomor pemenang. */
final class CsClaimController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $claims = Claim::with('customer')
            ->where('brand', config('app.brand'))
            ->when($q !== '', fn ($query) => $query->where('claim_code', 'ilike', "%{$q}%"))
            ->when($q === '', fn ($query) => $query->where('status', 'diajukan')->orderBy('redeem_requested_at'))
            ->when($q !== '', fn ($query) => $query->latest('id'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.claims', ['claims' => $claims, 'q' => $q]);
    }

    public function show(string $code)
    {
        return view('admin.claim', ['claim' => $this->find($code)->load(['customer', 'events', 'gamePrize'])]);
    }

    public function confirm(Request $request, string $code)
    {
        $data = $this->validated($request);

        try {
            $claim = ClaimFlow::confirm($this->find($code), $request->user(), $data['sender_phone'], $data['note'] ?? null);
        } catch (ClaimTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        $next = $claim->status === 'perlu_finance' ? 'diteruskan ke Finance' : 'masuk Pengiriman';

        return redirect()->route('admin.claims')->with('ok', "Klaim {$claim->claim_code} dikonfirmasi dan {$next}.");
    }

    public function reject(Request $request, string $code)
    {
        $data = $this->validated($request);

        try {
            $claim = ClaimFlow::reject($this->find($code), $request->user(), $data['sender_phone'], $data['note'] ?? null);
        } catch (ClaimTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.claims')->with('ok', "Klaim {$claim->claim_code} ditolak dan kembali ke daftar pelanggan.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'sender_phone' => ['required', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['sender_phone.required' => 'Isi nomor pengirim WhatsApp dulu.']);
    }

    private function find(string $code): Claim
    {
        return Claim::where('brand', config('app.brand'))->where('claim_code', $code)->firstOrFail();
    }
}
```

`apps/amura/app/Http/Controllers/Admin/ShippingController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;

final class ShippingController extends Controller
{
    public function index()
    {
        $brand = config('app.brand');

        return view('admin.shipping', [
            'packing' => Claim::with('customer')->where('brand', $brand)->where('status', 'diproses')->orderBy('updated_at')->get(),
            'shipped' => Claim::with('customer')->where('brand', $brand)->where('status', 'dikirim')->orderBy('updated_at')->get(),
        ]);
    }

    public function ship(Request $request, string $code)
    {
        $data = $request->validate(['shipping_awb' => ['required', 'string', 'max:64']], [
            'shipping_awb.required' => 'Isi resi pengiriman hadiah dulu.',
        ]);

        return $this->run(fn () => ClaimFlow::ship($this->find($code), $request->user(), $data['shipping_awb']), 'dikirim');
    }

    public function deliver(Request $request, string $code)
    {
        return $this->run(fn () => ClaimFlow::deliver($this->find($code), $request->user()), 'ditandai diterima');
    }

    private function run(callable $action, string $done)
    {
        try {
            $claim = $action();
        } catch (ClaimTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.shipping')->with('ok', "Klaim {$claim->claim_code} {$done}.");
    }

    private function find(string $code): Claim
    {
        return Claim::where('brand', config('app.brand'))->where('claim_code', $code)->firstOrFail();
    }
}
```

- [ ] **Step 7: Tulis ulang layout admin sesuai desain 3i/3k**

`apps/amura/resources/views/layouts/admin.blade.php` (ganti seluruh isi):
```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel Admin') · Sinergi Loyalty</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #2B221D; --muted: #6F6259; --line: #E7DDD0; --bg: #F4EFE8; --panel: #FBF8F4;
            --amura: #1E6B63; --reglow: #A8461F; --ok: #2E7A4E; --danger: #B4372C; --gold: #8A6318;
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink); background: var(--bg); min-height: 100vh; }
        :focus-visible { outline: 3px solid var(--amura); outline-offset: 2px; }
        .shell { display: grid; grid-template-columns: 232px 1fr; min-height: 100vh; }
        aside { background: var(--panel); border-right: 1px solid var(--line); padding: 22px 14px; display: flex; flex-direction: column; gap: 18px; }
        .org b { display: block; font-size: 16px; font-weight: 800; }
        .org small { font-size: 12px; color: var(--muted); }
        .menu { display: flex; flex-direction: column; gap: 4px; }
        .menu a { display: flex; justify-content: space-between; align-items: center; min-height: 44px; padding: 0 12px; border-radius: 10px; color: var(--ink); font-size: 14px; font-weight: 700; text-decoration: none; }
        .menu a:hover { background: #F0E8DD; }
        .menu a[aria-current="page"] { background: var(--ink); color: #fff; }
        .menu a span { font-size: 12px; font-weight: 800; }
        .logout { margin-top: auto; }
        .logout button { width: 100%; min-height: 40px; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--muted); font: 700 13px/1 inherit; font-family: inherit; cursor: pointer; }
        main { padding: 26px 28px 48px; max-width: 1180px; width: 100%; }
        h1 { margin: 0 0 4px; font-size: 26px; font-weight: 800; }
        h2 { margin: 24px 0 10px; font-size: 18px; font-weight: 800; }
        .lead { margin: 0 0 18px; font-size: 13px; color: var(--muted); }
        .flash { background: #E2F1E7; border: 1px solid #C7E3D1; color: var(--ok); border-radius: 12px; padding: 10px 14px; font-size: 14px; font-weight: 700; margin-bottom: 16px; }
        .flash-error { background: #FBE9E6; border: 1px solid #F1C9C3; color: var(--danger); border-radius: 12px; padding: 10px 14px; font-size: 14px; font-weight: 700; margin-bottom: 16px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid #EFE7DC; vertical-align: middle; }
        th { background: #F5EFE6; font-size: 12px; font-weight: 800; color: var(--muted); }
        tr:last-child td { border-bottom: none; }
        code { font-size: 13px; font-weight: 800; letter-spacing: .04em; }
        .badge { display: inline-block; font-size: 11px; font-weight: 800; border-radius: 6px; padding: 3px 8px; background: #F0E8DD; color: var(--ink); }
        .badge.amura { background: var(--amura); color: #fff; }
        .badge.reglow { background: var(--reglow); color: #fff; }
        .badge.gold { background: #F6EBD3; color: var(--gold); }
        .status { display: inline-block; font-size: 11px; font-weight: 800; border-radius: 999px; padding: 3px 9px; background: #F0E8DD; color: var(--muted); }
        .status.diajukan, .status.perlu_finance, .status.diproses, .status.dikirim { background: #E3EEF9; color: #2B5B8A; }
        .status.selesai { background: #E2F1E7; color: var(--ok); }
        .status.hangus { background: #F3E3E1; color: var(--danger); }
        .status.belum_redeem { background: #FBF1DE; color: var(--gold); }
        .muted { color: var(--muted); font-size: 12.5px; }
        .empty { padding: 28px 16px; text-align: center; color: var(--muted); }
        form.search { display: flex; gap: 8px; margin-bottom: 16px; max-width: 520px; }
        input[type=text], input[type=number], input[type=month], input[type=email], input[type=password], select, textarea {
            font-family: inherit; font-size: 14px; min-height: 44px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--ink);
        }
        form.search input { flex: 1; }
        .btn { display: inline-grid; place-items: center; min-height: 44px; border: none; border-radius: 10px; padding: 0 16px; font: 800 14px/1 inherit; font-family: inherit; color: #fff; background: var(--ink); cursor: pointer; text-decoration: none; }
        .btn.ok { background: var(--ok); }
        .btn.danger { background: #fff; color: var(--danger); border: 1.5px solid #E7B8B1; }
        .btn.light { background: #fff; color: var(--ink); border: 1.5px solid var(--line); }
        .btn[disabled] { opacity: .45; cursor: not-allowed; }
        a.link { color: var(--amura); font-weight: 800; text-decoration: none; }
        .pager { margin-top: 16px; font-size: 13px; }
        .pager a { color: var(--amura); font-weight: 700; text-decoration: none; margin-right: 10px; }
        @media (max-width: 820px) {
            .shell { grid-template-columns: 1fr; }
            aside { border-right: 0; border-bottom: 1px solid var(--line); padding: 14px 16px; }
            .menu { flex-direction: row; flex-wrap: wrap; }
            .logout { margin-top: 0; }
            main { padding: 20px 16px 40px; }
        }
    </style>
    @stack('head')
</head>
<body>
    @php
        $user = auth()->user();
        $role = $user?->role;
        $counts = \LoyaltyNew\Core\Prizes\ClaimFlow::counts((string) config('app.brand'));
        $roleName = ['cs' => 'CS Staff', 'finance' => 'Finance', 'game_admin' => 'Game Admin', 'super_admin' => 'Super Admin'][$role] ?? 'Staf';
        $is = fn (...$roles) => in_array($role, $roles, true);
    @endphp
    <div class="shell">
        <aside>
            <div class="org">
                <b>Sinergi Loyalty</b>
                <small>{{ $roleName }} · {{ $user?->name }} · {{ ucfirst((string) config('app.brand')) }}</small>
            </div>
            <nav class="menu" aria-label="Menu panel">
                @if ($is('cs', 'super_admin'))
                    <a href="{{ route('admin.claims') }}" @if (request()->routeIs('admin.claims*')) aria-current="page" @endif>Klaim WhatsApp <span>{{ $counts['diajukan'] }}</span></a>
                    <a href="{{ route('admin.shipping') }}" @if (request()->routeIs('admin.shipping*')) aria-current="page" @endif>Pengiriman <span>{{ $counts['diproses'] + $counts['dikirim'] }}</span></a>
                @endif
                @if ($is('finance', 'super_admin'))
                    <a href="{{ route('admin.finance') }}" @if (request()->routeIs('admin.finance*')) aria-current="page" @endif>Finance <span>{{ $counts['perlu_finance'] }}</span></a>
                @endif
                @if ($is('game_admin', 'super_admin'))
                    @if (Route::has('admin.catalog'))
                        <a href="{{ route('admin.catalog') }}" @if (request()->routeIs('admin.catalog*')) aria-current="page" @endif>Katalog poin</a>
                    @endif
                    <a href="{{ route('admin.game') }}" @if (request()->routeIs('admin.game*')) aria-current="page" @endif>Hadiah Gatcha</a>
                @endif
                @if ($is('super_admin'))
                    <a href="{{ route('admin.users') }}" @if (request()->routeIs('admin.users*')) aria-current="page" @endif>Pengguna</a>
                @endif
            </nav>
            <form class="logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        </aside>
        <main>
            @if (session('ok'))
                <div class="flash" role="status">{{ session('ok') }}</div>
            @endif
            @if ($errors->any())
                <div class="flash-error" role="alert">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
```

- [ ] **Step 8: Tulis view antrian, detail, pengiriman**

`apps/amura/resources/views/admin/claims.blade.php` (ganti seluruh isi):
```blade
@extends('layouts.admin')

@section('title', 'Klaim WhatsApp')

@section('content')
    <h1>Klaim WhatsApp</h1>
    <p class="lead">Pelanggan yang sudah menekan Redeem. Cocokkan nomor pengirim WhatsApp dengan nomor pemenang sebelum konfirmasi.</p>

    <form class="search" method="get" action="{{ route('admin.claims') }}">
        <input type="text" name="q" value="{{ $q }}" placeholder="Tempel kode dari chat WhatsApp" aria-label="Cari kode klaim">
        <button class="btn" type="submit">Cari</button>
        @if ($q !== '')
            <a class="btn light" href="{{ route('admin.claims') }}">Antrian</a>
        @endif
    </form>

    <div class="card">
        @if ($claims->isEmpty())
            <p class="empty">{{ $q !== '' ? 'Kode tidak ditemukan.' : 'Tidak ada klaim yang menunggu. Antrian kosong.' }}</p>
        @else
            <table>
                <thead>
                    <tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Status</th><th>Diajukan</th><th>Batas</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($claims as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: 'Nama belum tersedia' }}<div class="muted">{{ $claim->customer?->phone }}</div></td>
                            <td>{{ $claim->prize_name }}<div class="muted">{{ $claim->source === 'katalog' ? 'Tukar poin' : 'Gatcha' }}</div></td>
                            <td><span class="status {{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span></td>
                            <td class="muted">{{ $claim->redeem_requested_at?->format('d/m H:i') ?? '-' }}</td>
                            <td class="muted">{{ $claim->expires_at->format('d/m/Y') }}</td>
                            <td><a class="link" href="{{ route('admin.claims.show', $claim->claim_code) }}">Periksa</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="pager">{{ $claims->links() }}</div>
@endsection
```

`apps/amura/resources/views/admin/claim.blade.php`:
```blade
@extends('layouts.admin')

@section('title', 'Klaim '.$claim->claim_code)

@push('head')
<style>
    .detail { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .panel { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px; }
    .kv { display: grid; grid-template-columns: 150px 1fr; gap: 10px 14px; margin: 0; font-size: 14px; }
    .kv dt { color: var(--muted); font-weight: 700; }
    .kv dd { margin: 0; font-weight: 800; overflow-wrap: anywhere; }
    .verify label { display: block; font-size: 13px; font-weight: 800; margin: 12px 0 6px; }
    .verify input, .verify textarea { width: 100%; }
    .match { margin-top: 8px; font-size: 13px; font-weight: 800; min-height: 20px; }
    .match.yes { color: var(--ok); }
    .match.no { color: var(--danger); }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .events { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; font-size: 13px; }
    .events small { color: var(--muted); }
    @media (max-width: 820px) { .detail { grid-template-columns: 1fr; } .kv { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <a class="link" href="{{ route('admin.claims') }}">‹ Klaim WhatsApp</a>
    <h1 style="margin-top:10px">Klaim <code>{{ $claim->claim_code }}</code></h1>
    <p class="lead"><span class="status {{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span></p>

    <div class="detail">
        <div class="panel">
            <dl class="kv">
                <dt>Nama pemenang</dt><dd>{{ $claim->customer?->name ?: 'Nama belum tersedia' }}</dd>
                <dt>Nomor pemenang</dt><dd id="winner" data-phone="{{ $claim->customer?->phone }}">{{ $claim->customer?->phone }}</dd>
                <dt>Hadiah</dt><dd>{{ $claim->prize_name }} @if ($claim->isBigPrize()) <span class="badge gold">Hadiah utama</span> @endif</dd>
                <dt>Sumber</dt><dd>{{ $claim->source === 'katalog' ? 'Tukar poin ('.$claim->points_spent.' poin)' : 'Gatcha' }}</dd>
                <dt>Batas redeem</dt><dd>{{ $claim->expires_at->format('d/m/Y') }}</dd>
                @if ($claim->rejected_reason)
                    <dt>Penolakan terakhir</dt><dd>{{ $claim->rejected_reason }}</dd>
                @endif
            </dl>

            <h2>Riwayat</h2>
            <ol class="events">
                @foreach ($claim->events as $event)
                    <li><b>{{ \LoyaltyNew\Core\Prizes\ClaimFlow::eventLabel($event->from_status, $event->to_status) }}</b>
                        <small>· {{ $event->created_at->format('d/m H:i') }} · {{ $event->actor }}@if ($event->note) · {{ $event->note }}@endif</small></li>
                @endforeach
            </ol>
        </div>

        @if ($claim->status === 'diajukan')
            <div class="panel verify">
                <h2 style="margin-top:0">Cocokkan nomor</h2>
                <p class="muted">Tempel nomor pengirim dari chat WhatsApp. Konfirmasi hanya kalau nomornya sama dengan nomor pemenang.</p>
                <form method="POST" id="verifyForm">
                    @csrf
                    <label for="sender_phone">Nomor pengirim WhatsApp</label>
                    <input type="text" id="sender_phone" name="sender_phone" inputmode="tel" autocomplete="off" required value="{{ old('sender_phone') }}">
                    <div class="match" id="match" aria-live="polite"></div>
                    <label for="note">Catatan (opsional)</label>
                    <textarea id="note" name="note" rows="2" maxlength="255" placeholder="Misal: alamat Kendari dicek">{{ old('note') }}</textarea>
                    <div class="actions">
                        <button class="btn ok" type="submit" formaction="{{ route('admin.claims.confirm', $claim->claim_code) }}" id="confirmBtn" disabled>Konfirmasi</button>
                        <button class="btn danger" type="submit" formaction="{{ route('admin.claims.reject', $claim->claim_code) }}" id="rejectBtn" disabled>Tolak: nomor tidak cocok</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const input = document.getElementById('sender_phone');
        if (!input) return;
        const norm = v => { let d = String(v).replace(/\D/g, ''); if (d.startsWith('62')) d = '0' + d.slice(2); return d; };
        const winner = norm(document.getElementById('winner').dataset.phone);
        const match = document.getElementById('match');
        const sync = () => {
            const value = norm(input.value);
            const filled = value.length >= 8;
            const same = filled && value === winner;
            document.getElementById('confirmBtn').disabled = !same;
            document.getElementById('rejectBtn').disabled = !filled || same;
            match.textContent = !filled ? '' : (same ? 'Nomor cocok dengan pemenang.' : 'Nomor berbeda dengan pemenang.');
            match.className = 'match ' + (!filled ? '' : (same ? 'yes' : 'no'));
        };
        input.addEventListener('input', sync);
        sync();
    })();
</script>
@endpush
```

`apps/amura/resources/views/admin/shipping.blade.php`:
```blade
@extends('layouts.admin')

@section('title', 'Pengiriman')

@section('content')
    <h1>Pengiriman</h1>
    <p class="lead">Hadiah yang sudah dikonfirmasi. Isi resi saat dikirim, lalu tandai diterima.</p>

    <h2>Dikemas ({{ $packing->count() }})</h2>
    <div class="card">
        @if ($packing->isEmpty())
            <p class="empty">Tidak ada hadiah yang perlu dikirim.</p>
        @else
            <table>
                <thead><tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Resi hadiah</th></tr></thead>
                <tbody>
                    @foreach ($packing as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: '-' }}<div class="muted">{{ $claim->customer?->phone }}</div></td>
                            <td>{{ $claim->prize_name }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.shipping.ship', $claim->claim_code) }}" style="display:flex;gap:6px;flex-wrap:wrap">
                                    @csrf
                                    <input type="text" name="shipping_awb" aria-label="Resi untuk {{ $claim->claim_code }}" placeholder="Nomor resi" required>
                                    <button class="btn" type="submit">Kirim</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Dalam pengiriman ({{ $shipped->count() }})</h2>
    <div class="card">
        @if ($shipped->isEmpty())
            <p class="empty">Belum ada hadiah dalam pengiriman.</p>
        @else
            <table>
                <thead><tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Resi</th><th></th></tr></thead>
                <tbody>
                    @foreach ($shipped as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: '-' }}<div class="muted">{{ $claim->customer?->phone }}</div></td>
                            <td>{{ $claim->prize_name }}</td>
                            <td>{{ $claim->shipping_awb }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.shipping.deliver', $claim->claim_code) }}">
                                    @csrf
                                    <button class="btn ok" type="submit">Tandai diterima</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
```

- [ ] **Step 9: Jalankan suite Amura**

Run: `cd apps/amura && php artisan test`
Expected: semua lulus (tidak ada test yang mengecek teks layout admin lama).

- [ ] **Step 10: Commit**

```bash
git add apps/amura
git commit -m "feat(amura): dashboard CS untuk cocokkan nomor redeem dan pengiriman hadiah

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 11: Finance menyetujui ke `diproses`

**Files:**
- Modify: `apps/amura/app/Http/Controllers/Admin/FinanceController.php`
- Modify (tulis ulang): `apps/amura/resources/views/admin/finance.blade.php`
- Modify (test): `apps/amura/tests/Feature/FinanceTest.php`

**Interfaces:**
- Consumes: `ClaimFlow::approveFinance()` (Task 3).

- [ ] **Step 1: Ubah test Finance ke status baru**

Di `apps/amura/tests/Feature/FinanceTest.php`:
- `test_finance_setujui_hadiah_besar`: ganti `$this->assertSame('selesai', $claim->status);` menjadi `$this->assertSame('diproses', $claim->status);`.
- `test_approve_dua_kali_ditolak`: ganti `$this->assertSame('selesai', $claim->status);` menjadi `$this->assertSame('diproses', $claim->status);`.
- `test_hadiah_besar_diproses_tetap_di_pending_finance`: ganti `$this->assertSame('selesai', $claim->refresh()->status);` menjadi `$this->assertSame('diproses', $claim->refresh()->status);`.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=FinanceTest`
Expected: FAIL (status masih `selesai`).

- [ ] **Step 3: Ubah FinanceController**

`apps/amura/app/Http/Controllers/Admin/FinanceController.php` (ganti seluruh isi):
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;

/** Persetujuan hadiah utama (desain 1t). Klaim masuk ke sini setelah CS mencocokkan nomor. */
final class FinanceController extends Controller
{
    public function index()
    {
        $brand = config('app.brand');

        $pending = Claim::with(['customer', 'gamePrize'])
            ->where('brand', $brand)
            ->where('status', 'perlu_finance')
            ->orderBy('updated_at')
            ->paginate(20, ['*'], 'pending_page');

        $done = Claim::with(['customer', 'gamePrize'])
            ->where('brand', $brand)
            ->whereNotNull('finance_by')
            ->latest('updated_at')
            ->paginate(20, ['*'], 'done_page');

        return view('admin.finance', ['pending' => $pending, 'done' => $done]);
    }

    public function approve(Request $request, string $code)
    {
        try {
            $claim = Claim::where('brand', config('app.brand'))->where('claim_code', $code)->firstOrFail();
            ClaimFlow::approveFinance($claim, $request->user());
        } catch (ModelNotFoundException|ClaimTransitionException) {
            return back()->withErrors(['status' => 'Klaim tidak ditemukan, sudah diproses, atau sudah lewat batas klaim.']);
        }

        return back()->with('ok', 'Hadiah utama disetujui dan masuk Pengiriman.');
    }
}
```

- [ ] **Step 4: Tulis ulang view Finance**

`apps/amura/resources/views/admin/finance.blade.php` (ganti seluruh isi):
```blade
@extends('layouts.admin')

@section('title', 'Finance')

@section('content')
    <h1>Persetujuan hadiah utama</h1>
    <p class="lead">Nomor pemenang sudah dicocokkan CS. Setujui untuk meneruskan ke Pengiriman.</p>

    <div class="card">
        @if ($pending->isEmpty())
            <p class="empty">Tidak ada hadiah utama yang menunggu persetujuan.</p>
        @else
            <table>
                <thead><tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Batas</th><th></th></tr></thead>
                <tbody>
                    @foreach ($pending as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: 'Nama belum tersedia' }}<div class="muted">{{ $claim->customer?->phone }}</div></td>
                            <td>{{ $claim->prize_name }}</td>
                            <td class="muted">{{ $claim->expires_at->format('d/m/Y') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.finance.approve', $claim->claim_code) }}">
                                    @csrf
                                    <button class="btn ok" type="submit">Setujui</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="pager">{{ $pending->links() }}</div>

    <h2>Sudah disetujui</h2>
    <div class="card">
        @if ($done->isEmpty())
            <p class="empty">Belum ada persetujuan.</p>
        @else
            <table>
                <thead><tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($done as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: '-' }}</td>
                            <td>{{ $claim->prize_name }}</td>
                            <td><span class="status {{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="pager">{{ $done->links() }}</div>
@endsection
```

- [ ] **Step 5: Jalankan suite Amura**

Run: `cd apps/amura && php artisan test`
Expected: semua lulus. `test_dashboard_brand_scoped` di FinanceTest lulus karena query memakai kolom `brand` yang diisi hook `Claim::creating`.

- [ ] **Step 6: Commit**

```bash
git add apps/amura
git commit -m "feat(amura): Finance menyetujui hadiah utama ke antrean pengiriman

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 12: Game Admin: katalog bulanan dan pengaturan poin

**Files:**
- Create: `apps/amura/app/Http/Controllers/Admin/RewardCatalogController.php`, `apps/amura/resources/views/admin/catalog.blade.php`
- Modify: `apps/amura/routes/web.php`
- Test: `apps/amura/tests/Feature/RewardCatalogAdminTest.php`

**Interfaces:**
- Consumes: `RewardCatalogService::forPeriod()`, `copyFrom()`, `publish()`, `previousPeriod()`, `active()` (Task 5), `LoyaltySettings::current()`, `store()` (Task 2).
- Produces: route `admin.catalog` (GET, `?period=YYYY-MM`), `admin.catalog.items` (POST), `admin.catalog.items.update` (POST `/catalog/items/{item}`), `admin.catalog.copy` (POST), `admin.catalog.publish` (POST), `admin.catalog.settings` (POST).

- [ ] **Step 1: Tulis test yang gagal**

`apps/amura/tests/Feature/RewardCatalogAdminTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\RewardCatalogService;
use Tests\TestCase;

final class RewardCatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'game_admin'): User
    {
        return User::create(['name' => 'Admin', 'email' => $role.'@cat.id', 'password' => 'rahasia', 'role' => $role, 'brand_scope' => config('app.brand')]);
    }

    public function test_halaman_katalog_bisa_dibuka_game_admin_saja(): void
    {
        $this->actingAs($this->admin())->get('/admin/catalog')->assertOk()->assertSee('Katalog poin');
        $this->actingAs($this->admin('cs'))->get('/admin/catalog')->assertForbidden();
    }

    public function test_tambah_ubah_item_lalu_terbitkan(): void
    {
        $period = now()->format('Y-m');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/catalog/items', [
            'period' => $period, 'name' => 'Pouch Exclusive', 'points_cost' => 500, 'stock' => 20, 'sort' => 1,
        ])->assertRedirect(route('admin.catalog', ['period' => $period]));

        $item = RewardCatalog::where('period', $period)->firstOrFail()->items()->firstOrFail();
        $this->actingAs($admin)->post("/admin/catalog/items/{$item->id}", [
            'name' => 'Pouch Exclusive', 'points_cost' => 550, 'stock' => '', 'sort' => 1, 'active' => '1',
        ])->assertRedirect();
        $this->assertSame(550, $item->fresh()->points_cost);
        $this->assertNull($item->fresh()->stock);

        $this->actingAs($admin)->post('/admin/catalog/publish', ['period' => $period])->assertRedirect();
        $this->assertNotNull(RewardCatalogService::active(config('app.brand')));
    }

    public function test_salin_dari_bulan_lalu(): void
    {
        $prev = now()->subMonth()->format('Y-m');
        $period = now()->format('Y-m');
        RewardCatalogService::forPeriod(config('app.brand'), $prev)->items()->create(['name' => 'Exclusive ToteBag', 'points_cost' => 700]);

        $this->actingAs($this->admin())->post('/admin/catalog/copy', ['period' => $period])->assertRedirect();

        $this->assertSame(['Exclusive ToteBag'], RewardCatalogService::forPeriod(config('app.brand'), $period)->items->pluck('name')->all());
    }

    public function test_simpan_pengaturan_poin(): void
    {
        $this->actingAs($this->admin())->post('/admin/catalog/settings', [
            'per_gift' => 250, 'min_redeem' => 600, 'validity_months' => 6,
        ])->assertRedirect();

        $this->assertSame(['per_gift' => 250, 'min_redeem' => 600, 'validity_months' => 6], LoyaltySettings::current());
    }

    public function test_validasi_harga_poin(): void
    {
        $this->actingAs($this->admin())->post('/admin/catalog/items', [
            'period' => now()->format('Y-m'), 'name' => 'Gratis', 'points_cost' => 0,
        ])->assertSessionHasErrors('points_cost');
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=RewardCatalogAdminTest`
Expected: FAIL (404 pada `/admin/catalog`).

- [ ] **Step 3: Tambah route**

Di `apps/amura/routes/web.php`, tambahkan `use App\Http\Controllers\Admin\RewardCatalogController;` dan di dalam grup `role:game_admin,super_admin` tambahkan:
```php
    Route::get('/catalog', [RewardCatalogController::class, 'index'])->name('admin.catalog');
    Route::post('/catalog/items', [RewardCatalogController::class, 'storeItem'])->name('admin.catalog.items');
    Route::post('/catalog/items/{item}', [RewardCatalogController::class, 'updateItem'])->name('admin.catalog.items.update');
    Route::post('/catalog/copy', [RewardCatalogController::class, 'copy'])->name('admin.catalog.copy');
    Route::post('/catalog/publish', [RewardCatalogController::class, 'publish'])->name('admin.catalog.publish');
    Route::post('/catalog/settings', [RewardCatalogController::class, 'settings'])->name('admin.catalog.settings');
```

- [ ] **Step 4: Tulis controller**

`apps/amura/app/Http/Controllers/Admin/RewardCatalogController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\RewardCatalogItem;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\CatalogUnavailableException;
use LoyaltyNew\Core\Prizes\RewardCatalogService;

/** Katalog tukar poin per bulan dan aturan poin (desain 1s). */
final class RewardCatalogController extends Controller
{
    private const PERIOD = ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'];

    public function index(Request $request)
    {
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('period'))
            ? $request->query('period')
            : now()->format('Y-m');
        $brand = (string) config('app.brand');

        return view('admin.catalog', [
            'period' => $period,
            'previous' => RewardCatalogService::previousPeriod($period),
            'catalog' => RewardCatalog::where('period', $period)->first(),
            'active' => RewardCatalogService::active($brand),
            'settings' => LoyaltySettings::current(),
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate(['period' => self::PERIOD] + $this->itemRules());

        RewardCatalogService::forPeriod((string) config('app.brand'), $data['period'])->items()->create([
            'name' => $data['name'],
            'points_cost' => $data['points_cost'],
            'stock' => $data['stock'] ?? null,
            'sort' => $data['sort'] ?? 0,
            'active' => true,
        ]);

        return redirect()->route('admin.catalog', ['period' => $data['period']])->with('ok', 'Item ditambahkan.');
    }

    public function updateItem(Request $request, string $item)
    {
        $model = RewardCatalogItem::whereKey((int) $item)
            ->whereHas('catalog', fn ($q) => $q->where('brand', config('app.brand')))
            ->firstOrFail();
        $data = $request->validate($this->itemRules() + ['active' => ['nullable', 'boolean']]);

        $model->update([
            'name' => $data['name'],
            'points_cost' => $data['points_cost'],
            'stock' => $data['stock'] ?? null,
            'sort' => $data['sort'] ?? 0,
            'active' => (bool) ($data['active'] ?? false),
        ]);

        return back()->with('ok', 'Item diperbarui.');
    }

    public function copy(Request $request)
    {
        $data = $request->validate(['period' => self::PERIOD]);
        $from = RewardCatalogService::previousPeriod($data['period']);

        try {
            $count = RewardCatalogService::copyFrom((string) config('app.brand'), $from, $data['period']);
        } catch (CatalogUnavailableException $e) {
            return back()->withErrors(['catalog' => $e->getMessage()]);
        }

        return redirect()->route('admin.catalog', ['period' => $data['period']])->with('ok', "{$count} item disalin dari {$from}.");
    }

    public function publish(Request $request)
    {
        $data = $request->validate(['period' => self::PERIOD]);
        RewardCatalogService::publish((string) config('app.brand'), $data['period']);

        return redirect()->route('admin.catalog', ['period' => $data['period']])->with('ok', "Katalog {$data['period']} diterbitkan.");
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'per_gift' => ['required', 'integer', 'min:1', 'max:100000'],
            'min_redeem' => ['required', 'integer', 'min:1', 'max:1000000'],
            'validity_months' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        LoyaltySettings::store($data);

        return back()->with('ok', 'Pengaturan poin disimpan.');
    }

    private function itemRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'points_cost' => ['required', 'integer', 'min:1', 'max:10000000'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

- [ ] **Step 5: Tulis view katalog admin**

`apps/amura/resources/views/admin/catalog.blade.php`:
```blade
@extends('layouts.admin')

@section('title', 'Katalog poin')

@push('head')
<style>
    .toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
    .row-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .row-form input[name=name] { min-width: 180px; }
    .row-form input[type=number] { width: 110px; }
    .grid-settings { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; padding: 16px; }
    .grid-settings label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; }
    .check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; min-height: 44px; }
</style>
@endpush

@section('content')
    <h1>Katalog poin</h1>
    <p class="lead">
        Pelanggan melihat katalog terbit terbaru yang bulannya sudah berjalan.
        Aktif sekarang: <b>{{ $active?->period ?? 'belum ada' }}</b>.
    </p>

    <div class="toolbar">
        <form method="get" action="{{ route('admin.catalog') }}" class="row-form">
            <label for="period" class="muted">Bulan</label>
            <input type="month" id="period" name="period" value="{{ $period }}">
            <button class="btn light" type="submit">Buka</button>
        </form>
        <form method="post" action="{{ route('admin.catalog.copy') }}">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <button class="btn light" type="submit">Salin dari {{ $previous }}</button>
        </form>
        <form method="post" action="{{ route('admin.catalog.publish') }}">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <button class="btn ok" type="submit" @disabled($catalog?->published_at)>{{ $catalog?->published_at ? 'Sudah terbit' : 'Terbitkan '.$period }}</button>
        </form>
    </div>

    <div class="card">
        @if (! $catalog || $catalog->items->isEmpty())
            <p class="empty">Katalog {{ $period }} belum berisi item. Tambahkan item atau salin dari bulan lalu.</p>
        @else
            <table>
                <thead><tr><th>Item</th><th>Dipakai</th></tr></thead>
                <tbody>
                    @foreach ($catalog->items as $item)
                        <tr>
                            <td>
                                <form method="post" action="{{ route('admin.catalog.items.update', $item->id) }}" class="row-form">
                                    @csrf
                                    <input type="text" name="name" value="{{ $item->name }}" aria-label="Nama item" required>
                                    <input type="number" name="points_cost" value="{{ $item->points_cost }}" min="1" aria-label="Harga poin" required>
                                    <input type="number" name="stock" value="{{ $item->stock }}" min="0" placeholder="Tanpa batas" aria-label="Stok">
                                    <input type="number" name="sort" value="{{ $item->sort }}" min="0" aria-label="Urutan">
                                    <label class="check"><input type="checkbox" name="active" value="1" @checked($item->active)> Aktif</label>
                                    <button class="btn light" type="submit">Simpan</button>
                                </form>
                            </td>
                            <td class="muted">{{ $item->stock_used }}@if ($item->stock !== null) / {{ $item->stock }}@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Tambah item</h2>
    <div class="card" style="padding:16px">
        <form method="post" action="{{ route('admin.catalog.items') }}" class="row-form">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="text" name="name" placeholder="Nama hadiah" aria-label="Nama hadiah" required>
            <input type="number" name="points_cost" placeholder="Poin" min="1" aria-label="Harga poin" required>
            <input type="number" name="stock" placeholder="Stok (kosong = tanpa batas)" min="0" aria-label="Stok">
            <input type="number" name="sort" placeholder="Urutan" min="0" aria-label="Urutan">
            <button class="btn" type="submit">Tambah</button>
        </form>
    </div>

    <h2>Aturan poin</h2>
    <div class="card">
        <form method="post" action="{{ route('admin.catalog.settings') }}" class="grid-settings">
            @csrf
            <label>Poin per kado poin <input type="number" name="per_gift" min="1" value="{{ $settings['per_gift'] }}" required></label>
            <label>Minimal tukar <input type="number" name="min_redeem" min="1" value="{{ $settings['min_redeem'] }}" required></label>
            <label>Masa berlaku (bulan) <input type="number" name="validity_months" min="1" max="60" value="{{ $settings['validity_months'] }}" required></label>
            <button class="btn" type="submit">Simpan aturan</button>
        </form>
    </div>
@endsection
```

- [ ] **Step 6: Jalankan suite Amura**

Run: `cd apps/amura && php artisan test`
Expected: semua lulus.

- [ ] **Step 7: Commit**

```bash
git add apps/amura
git commit -m "feat(amura): Game Admin mengelola katalog poin bulanan dan aturan poin

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 13: Smoke seeder untuk alur baru

**Files:**
- Modify: `packages/core/src/Seeding/SmokeSeeder.php`
- Modify (test): `apps/amura/tests/Feature/SmokeSeederTest.php`

**Interfaces:**
- Consumes: `PointWallet::grant()` (Task 2), `RewardCatalogService::forPeriod()`, `publish()` (Task 5), `ClaimEvent` (Task 1), `AwbMatcher::normalizePhone()`.
- Produces: `SmokeSeeder::POINT_CATALOG` (array per brand: `[nama, poin]`), klaim `SMOKE-{brand}-STD` berstatus `diajukan` (voucher), `SMOKE-{brand}-BIG` berstatus `perlu_finance`, saldo 1.200 poin untuk pelanggan `SMOKE-CLAIM-{BRAND}`.

- [ ] **Step 1: Ubah test seeder**

Di `apps/amura/tests/Feature/SmokeSeederTest.php`, tambahkan import `use LoyaltyNew\Core\Models\Customer;`, `use LoyaltyNew\Core\Points\PointWallet;`, `use LoyaltyNew\Core\Prizes\RewardCatalogService;`. Ganti `test_klaim_biasa_belum_redeem_dan_klaim_besar_perlu_finance` dengan:
```php
    public function test_klaim_contoh_untuk_antrian_cs_dan_finance(): void
    {
        $this->seedSmoke();

        $std = Claim::where('claim_code', 'SMOKE-amura-STD')->firstOrFail();
        $this->assertSame('diajukan', $std->status);
        $this->assertSame('voucher', $std->gamePrize->tier);
        $this->assertSame('amura', $std->brand);
        $this->assertNotNull($std->prize_name);
        $this->assertSame(2, $std->events()->count());

        $big = Claim::where('claim_code', 'SMOKE-amura-BIG')->firstOrFail();
        $this->assertSame('perlu_finance', $big->status);
        $this->assertSame('hadiah_besar', $big->gamePrize->tier);
    }

    public function test_katalog_bulan_ini_dan_saldo_demo(): void
    {
        $this->seedSmoke();

        $catalog = RewardCatalogService::active('amura');
        $this->assertNotNull($catalog);
        $this->assertSame(count(SmokeSeeder::POINT_CATALOG['amura']), $catalog->items()->count());

        $this->assertSame(1200, (new PointWallet($this->demoCustomer()))->balance());
    }

    /** Pelanggan SMOKE-CLAIM dicari lewat AWB supaya test tetap benar setelah brand ditukar untuk Reglow. */
    private function demoCustomer(): Customer
    {
        $customerId = GameSession::withoutGlobalScopes()->where('awb', 'SMOKE-CLAIM-AMURA')->value('customer_id');

        return Customer::withoutGlobalScopes()->findOrFail($customerId);
    }
```
Di `test_idempotent_dijalankan_dua_kali`, tambahkan sebelum penutup method:
```php
        $this->assertSame(1200, (new PointWallet($this->demoCustomer()))->balance());
        $this->assertSame(count(SmokeSeeder::POINT_CATALOG['amura']), RewardCatalogService::active('amura')->items()->count());
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter=SmokeSeederTest`
Expected: FAIL (`POINT_CATALOG` belum ada).

- [ ] **Step 3: Ubah SmokeSeeder**

Di `packages/core/src/Seeding/SmokeSeeder.php`:

Tambahkan import:
```php
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Models\ClaimEvent;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\RewardCatalogService;
```

Tambahkan konstanta setelah `PASSWORD`:
```php
    /** Katalog tukar poin bulan pertama dari workbook final 2026-09-24 (hanya jalur Poin). */
    public const POINT_CATALOG = [
        'amura' => [
            ['Pouch Exclusive', 500], ['Voucher Diskon Ongkir 15rb', 600], ['Exclusive ToteBag', 700],
            ['Voucher Free Ongkir', 1000], ['Voucher Diskon 25rb', 1000], ['Voucher Diskon 50rb', 2000],
            ['Darkspot Serum', 2000], ['Gold Serum', 2500], ['Voucher Diskon 100rb', 3500],
        ],
        'reglow' => [
            ['Pouch Exclusive', 500], ['Voucher Diskon Ongkir 15rb', 600], ['Exclusive ToteBag', 700],
            ['Voucher Free Ongkir', 1000], ['Voucher Diskon 25rb', 1000], ['Voucher Diskon 50rb', 2000],
            ['Night Cream', 2000], ['Sunscreen Glow', 2500], ['Rice PDRN Serum', 3000], ['Voucher Diskon 100rb', 3500],
        ],
    ];
```

Ganti `run()` menjadi:
```php
    public function run(): void
    {
        $this->seedUsers();
        $this->seedCatalogs();
        $this->seedQuotaCaps();
        $this->seedSessionsAndClaims();
        $this->seedPointCatalogs();
        $this->seedDemoPoints();
    }
```

Di `seedSessionsAndClaims()`, ganti panggilan pertama (`SMOKE-CLAIM-`) menjadi:
```php
            $this->makeFinishedSession(
                brand: $brand,
                phone: $brand === 'amura' ? '081111111111' : '082222222222',
                name: $brand === 'amura' ? 'Budi Santoso' : 'Sari Dewi',
                awb: 'SMOKE-CLAIM-'.strtoupper($brand),
                takenTier: 'voucher',
                claimed: true,
                claimStatus: 'diajukan',
                bigPrize: false,
            );
```
dan pada panggilan kedua (`SMOKE-BIG-`), ganti `phone:` menjadi `$brand === 'amura' ? '081111111112' : '082222222223'`.

Di `makeFinishedSession()`, ganti blok `DB::table('claims')->updateOrInsert(...)` dengan:
```php
        $prizeName = $bigPrize
            ? (PrizeCatalog::withoutGlobalScopes()->whereKey($prizeTaken->fresh()->prize_catalog_id)->value('name') ?? 'Hadiah Utama')
            : 'Voucher Gratis Ongkir';

        DB::table('claims')->updateOrInsert(
            ['claim_code' => 'SMOKE-'.$brand.'-'.($bigPrize ? 'BIG' : 'STD')],
            [
                'game_session_id' => $session->id,
                'game_prize_id' => $prizeTaken->id,
                'brand' => $brand,
                'customer_id' => $customer->id,
                'source' => 'gatcha',
                'prize_name' => $prizeName,
                'status' => $claimStatus,
                'redeem_requested_at' => now(),
                'handled_by' => null,
                'finance_by' => null,
                'claimed_at' => null,
                'expires_at' => now()->addDays($expiryDays),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $claimId = DB::table('claims')->where('claim_code', 'SMOKE-'.$brand.'-'.($bigPrize ? 'BIG' : 'STD'))->value('id');
        if (! ClaimEvent::where('claim_id', $claimId)->exists()) {
            ClaimEvent::create(['claim_id' => $claimId, 'from_status' => null, 'to_status' => 'belum_redeem', 'actor' => 'system']);
            ClaimEvent::create([
                'claim_id' => $claimId,
                'from_status' => $bigPrize ? 'diajukan' : 'belum_redeem',
                'to_status' => $claimStatus,
                'actor' => $bigPrize ? 'cs' : 'customer',
            ]);
        }
```

Tambahkan dua method di akhir kelas:
```php
    /** Katalog tukar poin bulan berjalan per brand, langsung terbit. Tidak menduplikasi item. */
    private function seedPointCatalogs(): void
    {
        foreach ($this->brands() as $brand) {
            $catalog = RewardCatalogService::forPeriod($brand, now()->format('Y-m'));
            foreach (self::POINT_CATALOG[$brand] as $i => [$name, $points]) {
                $catalog->items()->firstOrCreate(['name' => $name], ['points_cost' => $points, 'sort' => $i + 1, 'active' => true]);
            }
            RewardCatalogService::publish($brand, now()->format('Y-m'));
        }
    }

    /** Saldo 1.200 poin untuk pelanggan SMOKE-CLAIM supaya tukar poin bisa langsung dicoba. */
    private function seedDemoPoints(): void
    {
        foreach ($this->brands() as $brand) {
            $session = GameSession::withoutGlobalScopes()->where('brand', $brand)->where('awb', 'SMOKE-CLAIM-'.strtoupper($brand))->first();
            if (! $session) {
                continue;
            }

            $customer = Customer::withoutGlobalScopes()->findOrFail($session->customer_id);
            // Seeder lama menyimpan HP berformat 62..; masuk lewat form menyimpan 0.., jadi samakan.
            $customer->update(['phone' => AwbMatcher::normalizePhone($customer->phone)]);

            if (! DB::table('point_lots')->where('customer_id', $customer->id)->exists()) {
                (new PointWallet($customer))->grant(1200, 12);
            }
        }
    }
```

- [ ] **Step 4: Jalankan test seeder dan suite Amura**

Run: `cd apps/amura && php artisan test --filter=SmokeSeederTest && php artisan test`
Expected: semua lulus. Kalau `BugfixRegressionTest::test_seeder_sesi_selesai_bisa_lanjut_ronde_dan_kuota_tercatat` gagal karena HP berubah, tidak ada yang perlu diubah di test itu (test mencari sesi lewat AWB).

- [ ] **Step 5: Jalankan seeder di DB dev**

Run: `cd apps/amura && php artisan loyalty:smoke-seed`
Expected: tanpa error. Cek `php artisan tinker --execute='echo LoyaltyNew\Core\Prizes\RewardCatalogService::active("amura")?->items()->count();'` → `9`.

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Seeding/SmokeSeeder.php apps/amura/tests/Feature/SmokeSeederTest.php
git commit -m "feat(core): smoke seeder berisi katalog poin, saldo demo, dan antrian redeem

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 14: Sinkronkan backend bersama ke Reglow

**Files:**
- Create: `scripts/sync-reglow.sh`
- Modify: `apps/reglow/app/Http/Controllers/PlayController.php`, `apps/reglow/routes/web.php`, `apps/reglow/routes/console.php`, `apps/reglow/.env.example`
- Copied by script: migrasi, controller admin, view admin, test (brand ditukar)

**Interfaces:**
- Consumes: semua perubahan core Task 1–13 dan file admin Amura dari Task 10–12.

- [ ] **Step 1: Tulis script sinkronisasi**

`scripts/sync-reglow.sh`:
```bash
#!/usr/bin/env bash
# Salin backend bersama dari Amura ke Reglow. Test Reglow adalah salinan test
# Amura dengan nama brand ditukar. UI pelanggan Reglow tidak disalin (tahap berikutnya).
# Jalankan dari root repo: bash scripts/sync-reglow.sh
set -euo pipefail

A=apps/amura
R=apps/reglow

cp "$A/database/migrations/2026_09_29_000001_create_points_and_redeem.php" "$R/database/migrations/"

for c in CsClaimController ShippingController FinanceController RewardCatalogController; do
    cp "$A/app/Http/Controllers/Admin/$c.php" "$R/app/Http/Controllers/Admin/"
done

cp "$A/resources/views/layouts/admin.blade.php" "$R/resources/views/layouts/"
for v in claims claim shipping finance catalog; do
    cp "$A/resources/views/admin/$v.blade.php" "$R/resources/views/admin/"
done

swap() {
    sed -e 's/amura/__B1__/g; s/reglow/amura/g; s/__B1__/reglow/g' \
        -e 's/Amura/__B2__/g; s/Reglow/Amura/g; s/__B2__/Reglow/g' \
        -e 's/AMURA/__B3__/g; s/REGLOW/AMURA/g; s/__B3__/REGLOW/g' "$1" > "$2"
}

for t in CsClaimTest FinanceTest ExpireClaimsTest SpecVerificationTest SmokeSeederTest PlayFlowTest \
         BugfixRegressionTest RedeemSchemaTest PointWalletTest ClaimFlowTest RewardCatalogServiceTest \
         ShippingTest RewardCatalogAdminTest; do
    swap "$A/tests/Feature/$t.php" "$R/tests/Feature/$t.php"
done

echo "Selesai. Jalankan: cd $R && php artisan test"
```

Run: `chmod +x scripts/sync-reglow.sh && bash scripts/sync-reglow.sh`
Expected: `Selesai.`

- [ ] **Step 2: Ubah PlayController Reglow**

Di `apps/reglow/app/Http/Controllers/PlayController.php`, tambahkan `use LoyaltyNew\Core\Prizes\PrizeAwarder;` dan ganti method `keep()` dengan:
```php
    /** Simpan satu hadiah dari ronde, lepas dua lainnya, lalu beri poin atau terbitkan klaim. */
    private function keep(GameSession $session, Collection $round, GamePrize $chosen): array
    {
        $chosen->update(['taken' => true, 'opened' => true]);

        $others = $round->reject(fn (GamePrize $prize) => $prize->id === $chosen->id);
        $session->prizes()->whereIn('id', $others->pluck('id'))->update(['picked' => false]);

        $award = PrizeAwarder::award($session, $chosen);
        $claim = $award['type'] === 'claim' ? $award['claim'] : null;

        return [
            'ok' => true,
            'revealed' => $this->revealed($round),
            'chosen' => $chosen->slot,
            'prize_name' => $claim?->prize_name,
            'points' => $award['type'] === 'points' ? $award['points'] : null,
            'balance' => $award['type'] === 'points' ? $award['balance'] : null,
            'claim_code' => $claim?->claim_code,
            'claim_expires_at' => $claim?->expires_at->toDateString(),
            'chance_left' => max(0, $session->chance_total - $session->fresh()->chance_used),
            'chance_total' => $session->chance_total,
        ];
    }
```
Ganti juga method `revealed()` dengan versi yang sama seperti Amura (test PlayFlow Reglow hasil tukar brand mengecek `name` dan `points`), lalu tambahkan `use LoyaltyNew\Core\Points\LoyaltySettings;` dan pastikan `use LoyaltyNew\Core\Prizes\ClaimService;` tetap ada:
```php
    /** @return array<int, array{slot:int, tier:string, label:string, name:?string, points:?int}> */
    private function revealed(Collection $round): array
    {
        $perGift = LoyaltySettings::current()['per_gift'];

        return $round->map(fn (GamePrize $prize) => [
            'slot' => $prize->slot,
            'tier' => $prize->tier,
            'label' => $this->tierLabel($prize->tier),
            'name' => $prize->tier === 'poin' ? null : ClaimService::prizeName($prize),
            'points' => $prize->tier === 'poin' ? $perGift : null,
        ])->values()->all();
    }
```

- [ ] **Step 3: Samakan route admin dan jadwal Reglow**

Di `apps/reglow/routes/web.php`, tambahkan import `use App\Http\Controllers\Admin\RewardCatalogController;` dan `use App\Http\Controllers\Admin\ShippingController;`, lalu ganti grup `role:cs,super_admin` dan tambahkan route katalog persis seperti Amura:
```php
Route::middleware(['auth', 'role:cs,super_admin'])->prefix('admin')->group(function () {
    Route::get('/claims', [CsClaimController::class, 'index'])->name('admin.claims');
    Route::get('/claims/{code}', [CsClaimController::class, 'show'])->name('admin.claims.show');
    Route::post('/claims/{code}/confirm', [CsClaimController::class, 'confirm'])->name('admin.claims.confirm');
    Route::post('/claims/{code}/reject', [CsClaimController::class, 'reject'])->name('admin.claims.reject');
    Route::get('/shipping', [ShippingController::class, 'index'])->name('admin.shipping');
    Route::post('/shipping/{code}/ship', [ShippingController::class, 'ship'])->name('admin.shipping.ship');
    Route::post('/shipping/{code}/deliver', [ShippingController::class, 'deliver'])->name('admin.shipping.deliver');
});
```
dan di dalam grup `role:game_admin,super_admin` tambahkan enam route katalog yang sama dengan Task 12 Step 3.

Di `apps/reglow/routes/console.php`, tambahkan setelah jadwal `loyalty:expire-claims`:
```php

// Poin lewat masa berlaku 12 bulan (spec poin-redeem §4).
Schedule::command('loyalty:expire-points')->dailyAt('01:05');
```

Di `apps/reglow/.env.example`, tambahkan di akhir:
```
# Nomor WhatsApp resmi CS (boleh 08.. atau 62..). Wajib diisi sebelum rilis.
CS_WHATSAPP=
```

- [ ] **Step 4: Jalankan ketiga suite berurutan**

Run:
```bash
cd packages/core && vendor/bin/phpunit
cd ../../apps/amura && php artisan test
cd ../reglow && php artisan test
```
Expected: semua lulus. Test Amura yang disalin sudah netral brand (brand dari `config('app.brand')` atau literal yang ikut ditukar script), jadi hasil tukar brand langsung berlaku untuk Reglow.

- [ ] **Step 5: Commit**

```bash
git add scripts/sync-reglow.sh apps/reglow
git commit -m "chore(reglow): sinkronkan backend redeem, dashboard admin, dan test dari Amura

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

---

### Task 15: Uji jalan end-to-end, dokumentasi, dan catatan

**Files:**
- Modify: `docs/DEPLOY.md`

- [ ] **Step 1: Siapkan data dev**

Run:
```bash
cd apps/amura && php artisan migrate --force && php artisan loyalty:smoke-seed
grep -q '^CS_WHATSAPP=' .env || echo 'CS_WHATSAPP=081140002626' >> .env
php artisan serve --port=8001
```
(`081140002626` hanya untuk uji lokal. Nomor asli dari owner diisi saat rilis.)

- [ ] **Step 2: Klik semua alur di browser 390×844 dan catat hasilnya**

Setiap baris harus benar-benar diklik. Catat hasil dan error console:
1. `/play` → resi `SMOKE-CLAIM-AMURA`, HP `81111111111` → papan Gatcha (1 kesempatan) → mainkan sampai habis → "Lihat Hadiahku".
2. `/` → nama "Budi Santoso", saldo ≥ 1.200, banner hadiah belum di-redeem (kalau ada), tombol Katalog dan Gatcha.
3. `/katalog` → item "Kurang … poin" mati, "Voucher Free Ongkir" → Tukar → konfirmasi → "Ya, tukar" → voucher baru, saldo berkurang 1.000.
4. Voucher → Redeem sekarang → tombol WhatsApp (href `https://wa.me/6281140002626?text=…`), cadangan: Salin nomor, Salin pesan.
5. `/login` sebagai `cs@amura.local` / `smoke12345` → Klaim WhatsApp → Periksa → nomor salah → tombol Tolak aktif → Tolak → klaim kembali ke pelanggan dengan pesan penolakan.
6. Pelanggan Redeem lagi → CS masukkan nomor pemenang → Konfirmasi → Pengiriman → isi resi → Tandai diterima → timeline pelanggan lengkap.
7. `SMOKE-amura-BIG` di Finance (`finance@amura.local`) → Setujui → muncul di Pengiriman.
8. `game@amura.local` → Katalog poin → ubah harga, tambah item, salin, terbitkan, simpan aturan.
9. "Bukan kamu? Keluar" → kembali ke `/play`, `/` mengarah ke `/play`.
10. Ulangi 2, 3, 4 di lebar 360 px (tanpa scroll horizontal) dan dengan keyboard saja (Tab, Enter, fokus terlihat).

- [ ] **Step 3: Perbarui DEPLOY.md**

Tambahkan bagian di `docs/DEPLOY.md`:
```markdown
## Poin, katalog, dan redeem (2026-09-29)

- Isi `CS_WHATSAPP` di `.env` tiap app (nomor resmi CS brand itu, format 08.. atau 62..). Kosong: tombol WhatsApp di voucher disembunyikan.
- Scheduler wajib jalan (`php artisan schedule:run` tiap menit via cron). Job harian: `loyalty:expire-claims` (01:00) dan `loyalty:expire-points` (01:05).
- Game Admin menerbitkan katalog poin bulan pertama di `/admin/catalog` sebelum rilis. Kalau katalog bulan baru belum terbit, katalog bulan lalu tetap tampil.
- Minta tim Markom menambah `customer_name` di respons `GET /orders/{awb}`. Tanpa itu, voucher menampilkan "Nama belum tersedia".
- Setelah mengubah kode backend bersama di Amura, jalankan `bash scripts/sync-reglow.sh` lalu test ketiga suite.
```

- [ ] **Step 4: Jalankan ketiga suite sekali lagi**

Run: `cd packages/core && vendor/bin/phpunit && cd ../../apps/amura && php artisan test && cd ../reglow && php artisan test`
Expected: semua lulus.

- [ ] **Step 5: Commit**

```bash
git add docs/DEPLOY.md
git commit -m "docs: panduan deploy poin, katalog bulanan, dan redeem WhatsApp

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01FTFJwFAKkZ4tScqY36DFLf"
```

- [ ] **Step 6: Catat di Obsidian**

Tambahkan ringkasan hasil (commit, jumlah test tiap suite, hasil klik uji jalan, hal yang dibutuhkan dari owner) ke `~/Documents/Obsidian/claude-second-brain/05 Handoff Prompts/loyalty/2026-09-29-loyalty-new-gatcha-ui-amura.md` dan daily note hari itu. File harus milik `crm-sinergi` dengan izin 664.
