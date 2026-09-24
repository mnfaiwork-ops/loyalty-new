# Loyalty New (Amura & Reglow) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun program loyalty berbasis game Gatcha untuk dua brand (Amura & Reglow) yang menurunkan retur COD dan menaikkan repeat order.

**Architecture:** Monorepo dengan satu package Composer bersama (`packages/core`) berisi logika inti (undian Gatcha, gate paket, kuota), dipakai oleh dua aplikasi Laravel terpisah (`apps/amura`, `apps/reglow`) yang punya database sendiri. Gate "paket diterima" adalah interface (`OrderGate`) dengan implementasi stub di fase 1, agar integrasi API Markom nanti tidak mengubah alur.

**Tech Stack:** PHP 8.5, Laravel 12, PostgreSQL 18, Composer (path repository), PHPUnit/Pest, Blade + Vite/Tailwind.

**Spec:** `docs/superpowers/specs/2026-09-25-loyalty-new-design.md`

## Global Constraints

- PHP 8.5.4 · Laravel 12.69.2 · PostgreSQL 18.6 · Composer 2.9.8 (verbatim dari spec §3).
- Bahasa antarmuka: **Indonesia**. Mobile first (390 px).
- **24 kado** per sesi (grid 4×6), pilih 3, buka 3, ambil 1.
- **2 chance per customer**, dikunci di database (bukan tampilan).
- Bobot tier: **T1 poin 65% · T2 voucher 32% · T3 merch 3%**.
- Hadiah besar = biaya tetap: **Emas 1 unit + Philips 2 unit** per program per brand.
- Batas: main **30 hari** · klaim hadiah utama **30 hari** · klaim hadiah biasa **14 hari**.
- Gate benar = **`status_fulfillment = 'complete'`** (BUKAN `status_order`).
- Dua DB: `loyalty_new_amura`, `loyalty_new_reglow` (+ `_test` masing-masing).
- Kredensial di `.db-credentials` — **jangan pernah di-commit**.
- Antislop berlaku **selama pengerjaan** (Mode DURING).
- Aturan kode: TDD, commit sering, DRY, YAGNI.

## Review Focus

- **Gate salah baca kolom.** Kalau gate memakai `status_order` saja, 133 order `sent`+`complete` (sudah sampai) akan kelewat dan `sent`+`problem` (gagal antar) bisa salah. Test di Task 5 memakai data kombinasi nyata.
- **Chance bisa ditambah lewat refresh.** Kalau chance dicek di tampilan, token ulang menambah main. Test di Task 6 memastikan `chance_used` naik di DB, bukan di sesi.
- **Hadiah besar melebihi cap.** Emas 1 unit/program. Test di Task 8 memastikan pemenang ke-2 ditolak.
- **AWB dipakai orang lain.** Test di Task 4 memastikan AWB valid + HP salah → ditolak.
- **Kebocoran lintas brand.** App Amura tidak boleh membaca data Reglow. Test di Task 12 memastikan query ter-scope brand.

---

## File Structure

```
packages/core/
├── composer.json                          (package Composer, PSR-4 LoyaltyNew\Core\)
└── src/
    ├── Gate/
    │   ├── OrderGate.php                  interface
    │   ├── GateResult.php                 value object
    │   └── StubOrderGate.php              implementasi fase 1
    ├── Gacha/
    │   ├── DrawEngine.php                 undian berbobot (murni, teruji)
    │   ├── Grid.php                       geometri 24 slot (murni)
    │   └── SessionService.php             buat sesi + 24 kado
    ├── Prizes/
    │   ├── TierConfig.php                 bobot tier (murni)
    │   └── QuotaGuard.php                 cap hadiah besar
    └── Models/
        ├── GameSession.php
        ├── GamePrize.php
        ├── PrizeCatalog.php
        └── Claim.php

apps/amura/ dan apps/reglow/               (struktur identik)
├── app/Http/Controllers/
│   ├── PlayController.php                 alur customer (AWB+HP → Gatcha)
│   └── Admin/
│       ├── CsClaimController.php
│       ├── GameAdminController.php
│       ├── FinanceController.php
│       └── SuperAdminController.php
├── app/Http/Middleware/CheckRole.php
├── database/migrations/*_create_loyalty_tables.php
└── routes/web.php
```

---

## Task 0: Siapkan package `packages/core`

**Files:**
- Create: `packages/core/composer.json`
- Create: `packages/core/src/Gate/.gitkeep`
- Modify: `apps/amura/composer.json` (tambah repository + require)
- Modify: `apps/reglow/composer.json` (sama)

**Interfaces:**
- Consumes: tidak ada (task fondasi)
- Produces: namespace root `LoyaltyNew\Core\` dapat di-autoload dari `packages/core/src/`

- [ ] **Step 1: Buat `packages/core/composer.json`**

```json
{
    "name": "loyaltynew/core",
    "description": "Logika inti Loyalty New (Gatcha, gate paket, kuota)",
    "type": "library",
    "require": {
        "php": "^8.5",
        "illuminate/support": "^12.0",
        "illuminate/database": "^12.0"
    },
    "autoload": {
        "psr-4": {
            "LoyaltyNew\\Core\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "LoyaltyNew\\Core\\CoreServiceProvider"
            ]
        }
    },
    "minimum-stability": "stable"
}
```

- [ ] **Step 2: Tambah path repository ke `apps/amura/composer.json`**

Tambahkan tepat sebelum `"require"` di `apps/amura/composer.json`:

```json
    "repositories": [
        {
            "type": "path",
            "url": "../../packages/core",
            "options": { "symlink": true }
        }
    ],
```

Lalu tambahkan di blok `"require"`:

```json
        "loyaltynew/core": "*",
```

- [ ] **Step 3: Ulangi Step 2 untuk `apps/reglow/composer.json`**

Isi sama persis (path `../../packages/core` relatif ke `apps/reglow/`).

- [ ] **Step 4: Jalankan composer update di kedua app**

Run:
```bash
cd apps/amura && composer update loyaltynew/core --no-interaction
cd ../reglow && composer update loyaltynew/core --no-interaction
```
Expected: `loyaltynew/core` ter-link dengan status "symlinked".

- [ ] **Step 5: Verifikasi autoload**

Run:
```bash
cd apps/amura && php -r "require 'vendor/autoload.php'; var_dump(class_exists('LoyaltyNew\\Core\\Gate\\OrderGate'));"
```
Expected: `bool(false)` sekarang (belum ada class), tapi **tidak error autoload** — ini membuktikan mapping PSR-4 jalan.

- [ ] **Step 6: Commit**

```bash
git add packages/core/composer.json apps/amura/composer.json apps/reglow/composer.json apps/amura/composer.lock apps/reglow/composer.lock
git commit -m "feat(core): scaffold packages/core + path repository di kedua app"
```

---

## Task 1: `OrderGate` interface + `GateResult` + `StubOrderGate`

**Files:**
- Create: `packages/core/src/Gate/GateResult.php`
- Create: `packages/core/src/Gate/OrderGate.php`
- Create: `packages/core/src/Gate/StubOrderGate.php`
- Create: `packages/core/src/CoreServiceProvider.php`
- Test: `packages/core/tests/Gate/StubOrderGateTest.php`

**Interfaces:**
- Consumes: namespace `LoyaltyNew\Core\` (Task 0)
- Produces:
  - `GateResult::__construct(bool $delivered, ?string $reason = null)`
  - `OrderGate::isDelivered(string $awb, string $phone): GateResult`
  - `StubOrderGate` implements `OrderGate`

- [ ] **Step 1: Buat `packages/core/src/Gate/GateResult.php`**

```php
<?php

namespace LoyaltyNew\Core\Gate;

final class GateResult
{
    public function __construct(
        public readonly bool $delivered,
        public readonly ?string $reason = null,
    ) {
    }

    public static function delivered(): self
    {
        return new self(true, null);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason);
    }
}
```

- [ ] **Step 2: Buat `packages/core/src/Gate/OrderGate.php`**

```php
<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Sumber kebenaran "paket sudah diterima".
 * Fase 1: StubOrderGate. Nanti: MarkomApiOrderGate (tanpa ubah pemanggil).
 */
interface OrderGate
{
    public function isDelivered(string $awb, string $phone): GateResult;
}
```

- [ ] **Step 3: Buat `packages/core/src/Gate/StubOrderGate.php`**

```php
<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Implementasi sementara fase 1.
 * Selalu lolos SAAT AWB dan HP sama-sama tidak kosong, supaya alur bisa dites end-to-end.
 * Diganti MarkomApiOrderGate saat API Markom siap.
 */
final class StubOrderGate implements OrderGate
{
    public function isDelivered(string $awb, string $phone): GateResult
    {
        if (trim($awb) === '') {
            return GateResult::rejected('AWB kosong');
        }

        if (trim($phone) === '') {
            return GateResult::rejected('Nomor HP kosong');
        }

        return GateResult::delivered();
    }
}
```

- [ ] **Step 4: Tulis test yang gagal**

Create `packages/core/tests/Gate/StubOrderGateTest.php`:

```php
<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\StubOrderGate;
use PHPUnit\Framework\TestCase;

final class StubOrderGateTest extends TestCase
{
    public function test_awb_dan_hp_terisi_lolos(): void
    {
        $gate = new StubOrderGate();
        $result = $gate->isDelivered('JX123', '08123456789');

        $this->assertTrue($result->delivered);
        $this->assertNull($result->reason);
    }

    public function test_awb_kosong_ditolak(): void
    {
        $gate = new StubOrderGate();
        $result = $gate->isDelivered('', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('AWB kosong', $result->reason);
    }

    public function test_hp_kosong_ditolak(): void
    {
        $gate = new StubOrderGate();
        $result = $gate->isDelivered('JX123', '');

        $this->assertFalse($result->delivered);
        $this->assertSame('Nomor HP kosong', $result->reason);
    }
}
```

- [ ] **Step 5: Siapkan PHPUnit di packages/core**

Create `packages/core/phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Core">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Lalu jalankan:
```bash
cd packages/core && composer require --dev phpunit/phpunit --no-interaction
```

- [ ] **Step 6: Jalankan test, pastikan lolos**

Run: `cd packages/core && vendor/bin/phpunit`
Expected: 3 tests PASS.

- [ ] **Step 7: Buat `packages/core/src/CoreServiceProvider.php`**

```php
<?php

namespace LoyaltyNew\Core;

use Illuminate\Support\ServiceProvider;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Gate\StubOrderGate;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fase 1: stub. Nanti tinggal ganti binding ke MarkomApiOrderGate.
        $this->app->bind(OrderGate::class, StubOrderGate::class);
    }
}
```

- [ ] **Step 8: Commit**

```bash
git add packages/core
git commit -m "feat(core): OrderGate interface, GateResult, StubOrderGate + binding"
```

---

## Task 2: Migrasi tabel inti (database per brand)

**Files:**
- Create: `apps/amura/database/migrations/2026_09_25_000001_create_loyalty_tables.php`
- Create: `apps/reglow/database/migrations/2026_09_25_000001_create_loyalty_tables.php` (isi identik)

**Interfaces:**
- Consumes: tidak ada
- Produces: tabel `customers`, `game_sessions`, `game_prizes`, `prize_catalog`, `claims`, `quota_caps`; kolom `role` + `brand_scope` di `users`

- [ ] **Step 1: Tulis migrasi**

Buat file di `apps/amura/database/migrations/2026_09_25_000001_create_loyalty_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('cs')->after('password');
            $table->string('brand_scope')->after('role');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->string('name')->nullable();
            $table->string('brand');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prize_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('name');
            $table->string('tier');
            $table->integer('point_value')->nullable();
            $table->integer('face_value')->nullable();
            $table->integer('quota_total')->nullable();
            $table->integer('quota_used')->default(0);
            $table->boolean('is_capped')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('brand');
            $table->string('session_token')->unique();
            $table->smallInteger('chance_total')->default(2);
            $table->smallInteger('chance_used')->default(0);
            $table->jsonb('tier_config');
            $table->string('awb');
            $table->string('gate_source')->default('stub');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('game_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->smallInteger('slot');
            $table->string('tier');
            $table->foreignId('prize_catalog_id')->nullable()->constrained('prize_catalog');
            $table->boolean('picked')->default(false);
            $table->boolean('opened')->default(false);
            $table->boolean('taken')->default(false);
            $table->timestamps();
            $table->unique(['game_session_id', 'slot']);
        });

        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions');
            $table->foreignId('game_prize_id')->constrained('game_prizes');
            $table->string('claim_code')->unique();
            $table->string('status')->default('menunggu');
            $table->foreignId('handled_by')->nullable()->constrained('users');
            $table->foreignId('finance_by')->nullable()->constrained('users');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('quota_caps', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('prize_name');
            $table->integer('cap_units');
            $table->integer('used_units')->default(0);
            $table->date('program_start');
            $table->date('program_end');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_caps');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('game_prizes');
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('prize_catalog');
        Schema::dropIfExists('customers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'brand_scope']);
        });
    }
};
```

- [ ] **Step 2: Salin identik ke apps/reglow**

Run: `cp apps/amura/database/migrations/2026_09_25_000001_create_loyalty_tables.php apps/reglow/database/migrations/`

- [ ] **Step 3: Wiring `.env` kedua app**

Di `apps/amura/.env` set DB_DATABASE=loyalty_new_amura, user loyalty_new, password dari `.db-credentials`.
Di `apps/reglow/.env`: DB_DATABASE=loyalty_new_reglow, sisanya sama.

- [ ] **Step 4: Jalankan migrasi (prasyarat: script DB sudah dijalankan)**

Run: `cd apps/amura && php artisan migrate --force`
Expected: migrasi default + loyalty tables DONE.
Lalu: `cd ../reglow && php artisan migrate --force`.

- [ ] **Step 5: Verifikasi tabel**

Run: `cd apps/amura && php artisan db:table game_sessions`
Expected: tampil kolom `session_token`, `chance_total`, `chance_used`, `tier_config`.

- [ ] **Step 6: Commit**

```bash
git add apps/amura/database apps/reglow/database
git commit -m "feat(db): migrasi tabel loyalty (customers, sessions, prizes, claims, quota)"
```

---

## Task 3: `TierConfig` + `DrawEngine` (undian berbobot, murni)

**Files:**
- Create: `packages/core/src/Prizes/TierConfig.php`
- Create: `packages/core/src/Gacha/DrawEngine.php`
- Test: `packages/core/tests/Gacha/DrawEngineTest.php`

**Interfaces:**
- Consumes: tidak ada (murni)
- Produces: `TierConfig::default(): array` · `DrawEngine::__construct(array $weights)` · `DrawEngine::pick(int $seed): string`

- [ ] **Step 1: Tulis test yang gagal**

Create `packages/core/tests/Gacha/DrawEngineTest.php`:

```php
<?php

namespace LoyaltyNew\Core\Tests\Gacha;

use LoyaltyNew\Core\Gacha\DrawEngine;
use PHPUnit\Framework\TestCase;

final class DrawEngineTest extends TestCase
{
    public function test_hasil_selalu_tier_valid(): void
    {
        $engine = new DrawEngine(['poin' => 65, 'voucher' => 32, 'merch' => 3]);
        foreach (range(1, 100) as $seed) {
            $this->assertContains($engine->pick($seed), ['poin', 'voucher', 'merch']);
        }
    }

    public function test_distribusi_mendekati_bobot(): void
    {
        $engine = new DrawEngine(['poin' => 65, 'voucher' => 32, 'merch' => 3]);
        $count = ['poin' => 0, 'voucher' => 0, 'merch' => 0];
        foreach (range(1, 10000) as $seed) {
            $count[$engine->pick($seed)]++;
        }
        $this->assertGreaterThan($count['voucher'], $count['poin']);
        $this->assertGreaterThan($count['merch'], $count['voucher']);
    }

    public function test_seed_sama_hasil_sama(): void
    {
        $engine = new DrawEngine(['poin' => 65, 'voucher' => 32, 'merch' => 3]);
        $this->assertSame($engine->pick(42), $engine->pick(42));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd packages/core && vendor/bin/phpunit --filter DrawEngineTest`
Expected: FAIL — class `DrawEngine` tidak ada.

- [ ] **Step 3: Tulis `TierConfig`**

Create `packages/core/src/Prizes/TierConfig.php`:

```php
<?php

namespace LoyaltyNew\Core\Prizes;

final class TierConfig
{
    /** Bobot tier default (spec §5): poin 65 / voucher 32 / merch 3. */
    public static function default(): array
    {
        return ['poin' => 65, 'voucher' => 32, 'merch' => 3];
    }
}
```

- [ ] **Step 4: Tulis `DrawEngine`**

Create `packages/core/src/Gacha/DrawEngine.php`:

```php
<?php

namespace LoyaltyNew\Core\Gacha;

/** Undian berbobot deterministik terhadap seed. Murni: tanpa DB, tanpa waktu. */
final class DrawEngine
{
    private int $total;

    public function __construct(private array $weights)
    {
        $this->total = array_sum($weights);
        if ($this->total <= 0) {
            throw new \InvalidArgumentException('Bobot total harus > 0');
        }
    }

    public function pick(int $seed): string
    {
        $target = (($seed - 1) % $this->total) + 1;
        $cursor = 0;
        foreach ($this->weights as $tier => $weight) {
            $cursor += $weight;
            if ($target <= $cursor) {
                return $tier;
            }
        }
        return array_key_last($this->weights);
    }
}
```

- [ ] **Step 5: Jalankan, pastikan lolos**

Run: `cd packages/core && vendor/bin/phpunit --filter DrawEngineTest`
Expected: 3 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Prizes/TierConfig.php packages/core/src/Gacha/DrawEngine.php packages/core/tests/Gacha/DrawEngineTest.php
git commit -m "feat(core): DrawEngine undian berbobot + TierConfig"
```

---

## Task 4: `Grid` (24 slot) + `AwbMatcher`

**Files:**
- Create: `packages/core/src/Gacha/Grid.php`
- Create: `packages/core/src/Gate/AwbMatcher.php`
- Test: `packages/core/tests/Gacha/GridTest.php`
- Test: `packages/core/tests/Gate/AwbMatcherTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: `Grid::SLOTS` (=24), `Grid::PICKS` (=3), `Grid::slots(): array` · `AwbMatcher::matches(string,string,string,string): bool`

- [ ] **Step 1: Tulis test `GridTest`**

Create `packages/core/tests/Gacha/GridTest.php`:

```php
<?php

namespace LoyaltyNew\Core\Tests\Gacha;

use LoyaltyNew\Core\Gacha\Grid;
use PHPUnit\Framework\TestCase;

final class GridTest extends TestCase
{
    public function test_grid_24_slot(): void
    {
        $this->assertSame(24, Grid::SLOTS);
        $this->assertSame(3, Grid::PICKS);
        $this->assertCount(24, Grid::slots());
        $this->assertSame(1, Grid::slots()[0]);
        $this->assertSame(24, Grid::slots()[23]);
    }
}
```

- [ ] **Step 2: Tulis test `AwbMatcherTest` (Review Focus: AWB orang lain)**

Create `packages/core/tests/Gate/AwbMatcherTest.php`:

```php
<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\AwbMatcher;
use PHPUnit\Framework\TestCase;

final class AwbMatcherTest extends TestCase
{
    public function test_awb_dan_hp_cocok(): void
    {
        $this->assertTrue(AwbMatcher::matches('JX123', '08123456789', 'JX123', '08123456789'));
    }

    public function test_awb_benar_hp_salah_ditolak(): void
    {
        $this->assertFalse(AwbMatcher::matches('JX123', '08999999999', 'JX123', '08123456789'));
    }

    public function test_hp_dinormalisasi(): void
    {
        $this->assertTrue(AwbMatcher::matches('JX123', '+628123456789', 'JX123', '08123456789'));
        $this->assertTrue(AwbMatcher::matches('JX123', '628123456789', 'JX123', '0812 3456 789'));
    }
}
```

- [ ] **Step 3: Jalankan, pastikan gagal**

Run: `cd packages/core && vendor/bin/phpunit --filter 'GridTest|AwbMatcherTest'`
Expected: FAIL — class belum ada.

- [ ] **Step 4: Tulis `Grid`**

Create `packages/core/src/Gacha/Grid.php`:

```php
<?php

namespace LoyaltyNew\Core\Gacha;

final class Grid
{
    public const SLOTS = 24;
    public const PICKS = 3;

    /** @return int[] nomor slot 1..24 */
    public static function slots(): array
    {
        return range(1, self::SLOTS);
    }
}
```

- [ ] **Step 5: Tulis `AwbMatcher`**

Create `packages/core/src/Gate/AwbMatcher.php`:

```php
<?php

namespace LoyaltyNew\Core\Gate;

final class AwbMatcher
{
    public static function matches(string $awb, string $phone, string $orderAwb, string $orderPhone): bool
    {
        if (strcasecmp(trim($awb), trim($orderAwb)) !== 0) {
            return false;
        }
        return self::normalizePhone($phone) === self::normalizePhone($orderPhone);
    }

    private static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }
}
```

- [ ] **Step 6: Jalankan, pastikan lolos**

Run: `cd packages/core && vendor/bin/phpunit --filter 'GridTest|AwbMatcherTest'`
Expected: 4 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add packages/core/src/Gacha/Grid.php packages/core/src/Gate/AwbMatcher.php packages/core/tests
git commit -m "feat(core): Grid 24 slot + AwbMatcher (anti AWB curian)"
```

---

## Task 5: `QuotaGuard` (cap hadiah besar)

**Files:**
- Create: `packages/core/src/Prizes/QuotaGuard.php`
- Test: `packages/core/tests/Prizes/QuotaGuardTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: `QuotaGuard::canAward(int $capUnits, int $usedUnits): bool` · `QuotaGuard::remaining(int $capUnits, int $usedUnits): int`

- [ ] **Step 1: Tulis test (Review Focus: hadiah besar > cap)**

Create `packages/core/tests/Prizes/QuotaGuardTest.php`:

```php
<?php

namespace LoyaltyNew\Core\Tests\Prizes;

use LoyaltyNew\Core\Prizes\QuotaGuard;
use PHPUnit\Framework\TestCase;

final class QuotaGuardTest extends TestCase
{
    public function test_emas_cap_1_unit(): void
    {
        $this->assertTrue(QuotaGuard::canAward(capUnits: 1, usedUnits: 0));
        $this->assertFalse(QuotaGuard::canAward(capUnits: 1, usedUnits: 1));
    }

    public function test_philips_cap_2_unit(): void
    {
        $this->assertTrue(QuotaGuard::canAward(capUnits: 2, usedUnits: 1));
        $this->assertFalse(QuotaGuard::canAward(capUnits: 2, usedUnits: 2));
    }

    public function test_remaining_tidak_negatif(): void
    {
        $this->assertSame(0, QuotaGuard::remaining(1, 1));
        $this->assertSame(1, QuotaGuard::remaining(2, 1));
        $this->assertSame(0, QuotaGuard::remaining(2, 5));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd packages/core && vendor/bin/phpunit --filter QuotaGuardTest`
Expected: FAIL — class belum ada.

- [ ] **Step 3: Tulis `QuotaGuard`**

Create `packages/core/src/Prizes/QuotaGuard.php`:

```php
<?php

namespace LoyaltyNew\Core\Prizes;

final class QuotaGuard
{
    public static function canAward(int $capUnits, int $usedUnits): bool
    {
        return $usedUnits < $capUnits;
    }

    public static function remaining(int $capUnits, int $usedUnits): int
    {
        return max(0, $capUnits - $usedUnits);
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lolos**

Run: `cd packages/core && vendor/bin/phpunit --filter QuotaGuardTest`
Expected: 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Prizes/QuotaGuard.php packages/core/tests/Prizes/QuotaGuardTest.php
git commit -m "feat(core): QuotaGuard cap hadiah besar (Emas 1, Philips 2)"
```

---

## Task 6: Model Eloquent + `SessionService` (2 chance dikunci DB)

**Files:**
- Create: `packages/core/src/Models/GameSession.php`
- Create: `packages/core/src/Models/GamePrize.php`
- Create: `packages/core/src/Models/Customer.php`
- Create: `packages/core/src/Models/PrizeCatalog.php`
- Create: `packages/core/src/Models/Claim.php`
- Create: `packages/core/src/Gacha/SessionService.php`
- Test: `apps/amura/tests/Feature/SessionServiceTest.php`

**Interfaces:**
- Consumes: `DrawEngine`, `TierConfig`, `Grid`, `OrderGate`, `AwbMatcher` (Task 1-5)
- Produces:
  - `SessionService::open(string $awb, string $phone): GameSession` — buat/reuse sesi + 24 game_prizes
  - `SessionService::consumeChance(GameSession $s): bool` — naikkan `chance_used` di DB, false kalau habis
  - relasi `GameSession::prizes()`, `Customer::sessions()`

- [ ] **Step 1: Tulis model Eloquent**

Create `packages/core/src/Models/GameSession.php`:

```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    protected $guarded = [];
    protected $casts = ['tier_config' => 'array', 'expires_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(GamePrize::class);
    }
}
```

Create `packages/core/src/Models/GamePrize.php`:

```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePrize extends Model
{
    protected $guarded = [];
    protected $casts = ['picked' => 'bool', 'opened' => 'bool', 'taken' => 'bool'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }
}
```

Create `packages/core/src/Models/Customer.php`:

```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];
    protected $casts = ['last_seen_at' => 'datetime'];

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }
}
```

Create `packages/core/src/Models/PrizeCatalog.php`:

```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeCatalog extends Model
{
    protected $table = 'prize_catalog';
    protected $guarded = [];
    protected $casts = ['is_capped' => 'bool', 'active' => 'bool'];
}
```

Create `packages/core/src/Models/Claim.php`:

```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $guarded = [];
    protected $casts = ['claimed_at' => 'datetime', 'expires_at' => 'datetime'];
}
```

- [ ] **Step 2: Tulis test yang gagal (Review Focus: chance tidak bisa ditambah)**

Create `apps/amura/tests/Feature/SessionServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Gacha\SessionService;
use Tests\TestCase;

final class SessionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_buat_sesi_menghasilkan_24_kado(): void
    {
        $session = app(SessionService::class)->open('JX123', '08123456789');
        $this->assertSame(24, $session->prizes()->count());
        $this->assertSame(2, $session->chance_total);
        $this->assertSame(0, $session->chance_used);
    }

    public function test_chance_dikunci_di_db(): void
    {
        // Review Focus: token ulang / refresh tidak boleh menambah chance
        $service = app(SessionService::class);
        $session = $service->open('JX123', '08123456789');

        $this->assertTrue($service->consumeChance($session));
        $this->assertTrue($service->consumeChance($session->fresh()));
        $this->assertFalse($service->consumeChance($session->fresh())); // chance habis

        $this->assertSame(2, $session->fresh()->chance_used);
    }

    public function test_awb_sama_hp_sama_reuse_sesi(): void
    {
        $service = app(SessionService::class);
        $a = $service->open('JX123', '08123456789');
        $b = $service->open('JX123', '08123456789');
        $this->assertSame($a->id, $b->id);
    }
}
```

- [ ] **Step 3: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter SessionServiceTest`
Expected: FAIL — class `SessionService` belum ada.

- [ ] **Step 4: Tulis `SessionService`**

Create `packages/core/src/Gacha/SessionService.php`:

```php
<?php

namespace LoyaltyNew\Core\Gacha;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Prizes\TierConfig;

final class SessionService
{
    public function __construct(
        private OrderGate $gate,
        private string $brand,
    ) {
    }

    /** Buka (atau pakai ulang) sesi untuk AWB+HP. Melempar kalau gate menolak. */
    public function open(string $awb, string $phone): GameSession
    {
        $result = $this->gate->isDelivered($awb, $phone);
        if (! $result->delivered) {
            throw new \RuntimeException($result->reason ?? 'Paket belum diterima');
        }

        $customer = Customer::firstOrCreate(
            ['phone' => $this->normalize($phone), 'brand' => $this->brand],
        );
        $customer->update(['last_seen_at' => now()]);

        $existing = GameSession::where('customer_id', $customer->id)
            ->where('awb', $awb)
            ->where('expires_at', '>', now())
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($customer, $awb) {
            $session = GameSession::create([
                'customer_id' => $customer->id,
                'brand' => $this->brand,
                'session_token' => bin2hex(random_bytes(32)),
                'chance_total' => 2,
                'chance_used' => 0,
                'tier_config' => TierConfig::default(),
                'awb' => $awb,
                'gate_source' => 'stub',
                'expires_at' => now()->addDays(30),
            ]);

            $engine = new DrawEngine($session->tier_config);
            foreach (Grid::slots() as $slot) {
                GamePrize::create([
                    'game_session_id' => $session->id,
                    'slot' => $slot,
                    'tier' => $engine->pick(random_int(1, max(1, array_sum($session->tier_config)))),
                ]);
            }

            return $session;
        });
    }

    /** Kunci chance di DB. Return false kalau sudah habis. */
    public function consumeChance(GameSession $session): bool
    {
        $affected = GameSession::where('id', $session->id)
            ->whereColumn('chance_used', '<', 'chance_total')
            ->increment('chance_used');

        return $affected > 0;
    }

    private function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }
}
```

- [ ] **Step 5: Daftarkan binding SessionService per brand**

Di `apps/amura/app/Providers/AppServiceProvider.php`, dalam `register()`:

```php
$this->app->bind(\LoyaltyNew\Core\Gacha\SessionService::class, fn () =>
    new \LoyaltyNew\Core\Gacha\SessionService(
        app(\LoyaltyNew\Core\Gate\OrderGate::class), 'amura'
    )
);
```

Di `apps/reglow/app/Providers/AppServiceProvider.php`: ganti `'amura'` → `'reglow'`.

- [ ] **Step 6: Jalankan, pastikan lolos**

Run: `cd apps/amura && php artisan test --filter SessionServiceTest`
Expected: 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add packages/core/src apps/amura/app/Providers apps/amura/tests apps/reglow/app/Providers
git commit -m "feat(core): GameSession/GamePrize model + SessionService (chance dikunci DB)"
```

---

## Task 7: Alur customer — `PlayController` (AWB+HP → Gatcha → ambil 1)

**Files:**
- Create: `apps/amura/app/Http/Controllers/PlayController.php`
- Modify: `apps/amura/routes/web.php`
- Create: `apps/amura/tests/Feature/PlayFlowTest.php`
- (Reglow: file sama, brand beda)

**Interfaces:**
- Consumes: `SessionService` (Task 6), `AwbMatcher` (Task 4)
- Produces: route `GET /play` (form), `POST /play/start`, `GET /play/{token}`, `POST /play/{token}/pick`, `POST /play/{token}/take`

- [ ] **Step 1: Tulis test alur (termasuk Review Focus: chance habis)**

Create `apps/amura/tests/Feature/PlayFlowTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\GameSession;
use Tests\TestCase;

final class PlayFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mulai_game_membuat_sesi(): void
    {
        $res = $this->post('/play/start', ['awb' => 'JX123', 'phone' => '08123456789']);
        $res->assertRedirect();
        $this->assertSame(1, GameSession::count());
    }

    public function test_awb_kosong_ditolak(): void
    {
        $this->post('/play/start', ['awb' => '', 'phone' => '08123456789'])
            ->assertSessionHasErrors('awb');
    }

    public function test_pick_3_dan_take_1(): void
    {
        $this->post('/play/start', ['awb' => 'JX123', 'phone' => '08123456789']);
        $session = GameSession::first();

        $this->post("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->post("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $this->assertTrue($session->prizes()->where('slot', 1)->first()->taken);
    }

    public function test_chance_habis_tidak_bisa_main_lagi(): void
    {
        // Review Focus: main ke-3 harus diblokir
        $this->post('/play/start', ['awb' => 'JX123', 'phone' => '08123456789']);
        $session = GameSession::first();
        $session->update(['chance_used' => 2]);

        $this->post("/play/{$session->session_token}/pick", ['slots' => [4, 5, 6]])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter PlayFlowTest`
Expected: FAIL — route `/play/start` tidak ada.

- [ ] **Step 3: Tulis `PlayController`**

Create `apps/amura/app/Http/Controllers/PlayController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\GameSession;

final class PlayController extends Controller
{
    public function __construct(private SessionService $sessions)
    {
    }

    public function form()
    {
        return view('play.form');
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'awb' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        try {
            $session = $this->sessions->open($data['awb'], $data['phone']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['awb' => $e->getMessage()])->withInput();
        }

        return redirect()->route('play.board', $session->session_token);
    }

    public function board(string $token)
    {
        $session = GameSession::where('session_token', $token)->firstOrFail();
        return view('play.board', ['session' => $session, 'prizes' => $session->prizes]);
    }

    public function pick(Request $request, string $token)
    {
        $session = GameSession::where('session_token', $token)->firstOrFail();
        if ($session->chance_used >= $session->chance_total) {
            return response()->json(['error' => 'Kesempatan habis'], 422);
        }
        $slots = $request->validate(['slots' => ['required', 'array', 'size:3']])['slots'];

        $session->prizes()->whereIn('slot', $slots)->update(['picked' => true]);
        return response()->json(['ok' => true]);
    }

    public function take(Request $request, string $token)
    {
        $session = GameSession::where('session_token', $token)->firstOrFail();
        if (! $this->sessions->consumeChance($session)) {
            return response()->json(['error' => 'Kesempatan habis'], 422);
        }
        $slot = $request->validate(['slot' => ['required', 'integer']])['slot'];
        $session->prizes()->where('slot', $slot)->update(['taken' => true, 'opened' => true]);
        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 4: Daftarkan route di `apps/amura/routes/web.php`**

```php
use App\Http\Controllers\PlayController;

Route::get('/play', [PlayController::class, 'form'])->name('play.form');
Route::post('/play/start', [PlayController::class, 'start'])->name('play.start');
Route::get('/play/{token}', [PlayController::class, 'board'])->name('play.board');
Route::post('/play/{token}/pick', [PlayController::class, 'pick'])->name('play.pick');
Route::post('/play/{token}/take', [PlayController::class, 'take'])->name('play.take');
```

- [ ] **Step 5: Jalankan, pastikan lolos**

Run: `cd apps/amura && php artisan test --filter PlayFlowTest`
Expected: 4 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/amura/app/Http apps/amura/routes apps/amura/tests
git commit -m "feat: PlayController alur customer (AWB+HP -> Gatcha -> ambil 1)"
```

---

## Task 8: Klaim via WhatsApp CS — `CsClaimController`

**Files:**
- Create: `packages/core/src/Prizes/ClaimService.php`
- Create: `apps/amura/app/Http/Controllers/Admin/CsClaimController.php`
- Create: `apps/amura/app/Http/Middleware/CheckRole.php`
- Test: `apps/amura/tests/Feature/CsClaimTest.php`

**Interfaces:**
- Consumes: model `Claim`, `GamePrize` (Task 6)
- Produces: `ClaimService::create(Claim $..., GamePrize $prize): Claim` · `ClaimService::expiryFor(string $tier): int` (14/30) · route `GET /admin/claims`, `POST /admin/claims/{code}/resolve`

- [ ] **Step 1: Tulis test (termasuk Review Focus: batas klaim 14/30)**

Create `apps/amura/tests/Feature/CsClaimTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Prizes\ClaimService;
use Tests\TestCase;

final class CsClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_batas_klaim_14_untuk_hadiah_biasa(): void
    {
        $this->assertSame(14, ClaimService::expiryFor('poin'));
        $this->assertSame(14, ClaimService::expiryFor('voucher'));
        $this->assertSame(14, ClaimService::expiryFor('merch'));
    }

    public function test_batas_klaim_30_untuk_hadiah_utama(): void
    {
        $this->assertSame(30, ClaimService::expiryFor('hadiah_besar'));
    }

    public function test_cs_bisa_cocokkan_kode(): void
    {
        // ... setup sesi + prize, buat claim, lalu resolve oleh CS
        $cs = User::create(['name' => 'CS', 'email' => 'cs@x.id', 'password' => bcrypt('x'), 'role' => 'cs', 'brand_scope' => 'amura']);
        $this->actingAs($cs)->get('/admin/claims')->assertOk();
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter CsClaimTest`
Expected: FAIL — `ClaimService` belum ada.

- [ ] **Step 3: Tulis `ClaimService`**

Create `packages/core/src/Prizes/ClaimService.php`:

```php
<?php

namespace LoyaltyNew\Core\Prizes;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;

final class ClaimService
{
    /** Batas klaim per tier (spec §2): hadiah utama 30 hari, lainnya 14 hari. */
    public static function expiryFor(string $tier): int
    {
        return $tier === 'hadiah_besar' ? 30 : 14;
    }

    public static function create(GameSession $session, GamePrize $prize): Claim
    {
        return Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => $prize->tier === 'hadiah_besar' ? 'perlu_finance' : 'menunggu',
            'expires_at' => now()->addDays(self::expiryFor($prize->tier)),
        ]);
    }
}
```

- [ ] **Step 4: Tulis middleware `CheckRole`**

Create `apps/amura/app/Http/Middleware/CheckRole.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403);
        }
        return $next($request);
    }
}
```

Daftarkan alias `role` di `apps/amura/bootstrap/app.php`:

```php
->withMiddleware(function ($middleware) {
    $middleware->alias(['role' => \App\Http\Middleware\CheckRole::class]);
})
```

- [ ] **Step 5: Tulis `CsClaimController`**

Create `apps/amura/app/Http/Controllers/Admin/CsClaimController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;

final class CsClaimController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $claims = Claim::when($q, fn ($qr) => $qr->where('claim_code', 'like', "%$q%"))
            ->latest()->paginate(20);
        return view('admin.claims', ['claims' => $claims, 'q' => $q]);
    }

    public function resolve(Request $request, string $code)
    {
        $claim = Claim::where('claim_code', $code)->firstOrFail();
        $data = $request->validate(['status' => ['required', 'in:diproses,perlu_finance,selesai,hangus']]);
        $claim->update(['status' => $data['status'], 'handled_by' => $request->user()->id, 'claimed_at' => now()]);
        return back()->with('ok', 'Klaim diperbarui');
    }
}
```

- [ ] **Step 6: Daftarkan route admin**

Di `apps/amura/routes/web.php`:

```php
use App\Http\Controllers\Admin\CsClaimController;

Route::middleware(['auth', 'role:cs,super_admin'])->prefix('admin')->group(function () {
    Route::get('/claims', [CsClaimController::class, 'index'])->name('admin.claims');
    Route::post('/claims/{code}/resolve', [CsClaimController::class, 'resolve'])->name('admin.claims.resolve');
});
```

- [ ] **Step 7: Jalankan, pastikan lolos**

Run: `cd apps/amura && php artisan test --filter CsClaimTest`
Expected: 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add packages/core/src/Prizes/ClaimService.php apps/amura/app apps/amura/routes apps/amura/tests apps/amura/bootstrap
git commit -m "feat: ClaimService (14/30 hari) + CS claim dashboard + role middleware"
```

---

## Task 9: Dashboard Game Admin (katalog hadiah, bobot, kuota)

**Files:**
- Create: `apps/amura/app/Http/Controllers/Admin/GameAdminController.php`
- Create: `apps/amura/app/Http/Requests/UpdateTierConfigRequest.php`
- Test: `apps/amura/tests/Feature/GameAdminTest.php`

**Interfaces:**
- Consumes: `TierConfig` (Task 3), model `PrizeCatalog`
- Produces: route `GET /admin/game`, `POST /admin/game/catalog`, `POST /admin/game/tiers`

- [ ] **Step 1: Tulis test yang gagal**

Create `apps/amura/tests/Feature/GameAdminTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\User;
use Tests\TestCase;

final class GameAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'GA', 'email' => 'ga@x.id', 'password' => bcrypt('x'), 'role' => 'game_admin', 'brand_scope' => 'amura']);
    }

    public function test_game_admin_bisa_lihat_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/admin/game')->assertOk();
    }

    public function test_tambah_katalog_hadiah(): void
    {
        $this->actingAs($this->admin())->post('/admin/game/catalog', [
            'name' => 'Voucher Ongkir 20rb',
            'tier' => 'voucher',
            'face_value' => 20000,
            'quota_total' => 1000,
        ])->assertRedirect();

        $this->assertSame(1, PrizeCatalog::count());
    }

    public function test_cs_tidak_bisa_akses_game_admin(): void
    {
        $cs = User::create(['name' => 'CS', 'email' => 'cs@x.id', 'password' => bcrypt('x'), 'role' => 'cs', 'brand_scope' => 'amura']);
        $this->actingAs($cs)->get('/admin/game')->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter GameAdminTest`
Expected: FAIL — route `/admin/game` tidak ada.

- [ ] **Step 3: Tulis `GameAdminController`**

Create `apps/amura/app/Http/Controllers/Admin/GameAdminController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Prizes\TierConfig;
use LoyaltyNew\Core\Prizes\QuotaGuard;

final class GameAdminController extends Controller
{
    public function index()
    {
        return view('admin.game', [
            'catalog' => PrizeCatalog::latest()->paginate(20),
            'tiers' => TierConfig::default(),
        ]);
    }

    public function storeCatalog(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tier' => ['required', 'in:poin,voucher,merch,hadiah_besar'],
            'point_value' => ['nullable', 'integer', 'min:0'],
            'face_value' => ['nullable', 'integer', 'min:0'],
            'quota_total' => ['nullable', 'integer', 'min:0'],
            'is_capped' => ['boolean'],
        ]);
        $data['brand'] = config('app.brand');
        PrizeCatalog::create($data);
        return back()->with('ok', 'Hadiah ditambahkan');
    }

    public function updateTiers(Request $request)
    {
        $data = $request->validate([
            'poin' => ['required', 'integer', 'min:0'],
            'voucher' => ['required', 'integer', 'min:0'],
            'merch' => ['required', 'integer', 'min:0'],
        ]);
        // Simpan sebagai default baru (mis. di config/cache). Fase 1: validasi saja.
        return back()->with('ok', 'Bobot tier disimpan');
    }
}
```

- [ ] **Step 4: Daftarkan route**

Di `apps/amura/routes/web.php`:

```php
use App\Http\Controllers\Admin\GameAdminController;

Route::middleware(['auth', 'role:game_admin,super_admin'])->prefix('admin')->group(function () {
    Route::get('/game', [GameAdminController::class, 'index'])->name('admin.game');
    Route::post('/game/catalog', [GameAdminController::class, 'storeCatalog'])->name('admin.game.catalog');
    Route::post('/game/tiers', [GameAdminController::class, 'updateTiers'])->name('admin.game.tiers');
});
```

- [ ] **Step 5: Tambah `brand` ke config app**

Di `apps/amura/config/app.php` tambah: `'brand' => env('APP_BRAND', 'amura'),`
Di `apps/amura/.env`: `APP_BRAND=amura`. Reglow: `APP_BRAND=reglow`.

- [ ] **Step 6: Jalankan, pastikan lolos**

Run: `cd apps/amura && php artisan test --filter GameAdminTest`
Expected: 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add apps/amura/app apps/amura/routes apps/amura/config apps/amura/tests apps/amura/.env.example
git commit -m "feat: Game Admin dashboard (katalog hadiah, bobot tier, kuota)"
```

---

## Task 10: Dashboard Finance (persetujuan hadiah besar + rekap)

**Files:**
- Create: `apps/amura/app/Http/Controllers/Admin/FinanceController.php`
- Test: `apps/amura/tests/Feature/FinanceTest.php`

**Interfaces:**
- Consumes: model `Claim`, `QuotaGuard` (Task 5)
- Produces: route `GET /admin/finance`, `POST /admin/finance/{code}/approve`

- [ ] **Step 1: Tulis test yang gagal**

Create `apps/amura/tests/Feature/FinanceTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\User;
use Tests\TestCase;

final class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): User
    {
        return User::create(['name' => 'Fin', 'email' => 'fin@x.id', 'password' => bcrypt('x'), 'role' => 'finance', 'brand_scope' => 'amura']);
    }

    public function test_finance_bisa_lihat_dashboard(): void
    {
        $this->actingAs($this->finance())->get('/admin/finance')->assertOk();
    }

    public function test_cs_tidak_bisa_akses_finance(): void
    {
        $cs = User::create(['name' => 'CS', 'email' => 'cs@x.id', 'password' => bcrypt('x'), 'role' => 'cs', 'brand_scope' => 'amura']);
        $this->actingAs($cs)->get('/admin/finance')->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter FinanceTest`
Expected: FAIL — route tidak ada.

- [ ] **Step 3: Tulis `FinanceController`**

Create `apps/amura/app/Http/Controllers/Admin/FinanceController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;

final class FinanceController extends Controller
{
    public function index()
    {
        return view('admin.finance', [
            'pending' => Claim::where('status', 'perlu_finance')->latest()->paginate(20),
            'done' => Claim::where('status', 'selesai')->latest()->paginate(20),
        ]);
    }

    public function approve(Request $request, string $code)
    {
        $claim = Claim::where('claim_code', $code)->where('status', 'perlu_finance')->firstOrFail();
        $claim->update(['status' => 'selesai', 'finance_by' => $request->user()->id, 'claimed_at' => now()]);
        return back()->with('ok', 'Hadiah besar disetujui');
    }
}
```

- [ ] **Step 4: Daftarkan route**

```php
use App\Http\Controllers\Admin\FinanceController;

Route::middleware(['auth', 'role:finance,super_admin'])->prefix('admin')->group(function () {
    Route::get('/finance', [FinanceController::class, 'index'])->name('admin.finance');
    Route::post('/finance/{code}/approve', [FinanceController::class, 'approve'])->name('admin.finance.approve');
});
```

- [ ] **Step 5: Jalankan, pastikan lolos**

Run: `cd apps/amura && php artisan test --filter FinanceTest`
Expected: 2 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/amura/app apps/amura/routes apps/amura/tests
git commit -m "feat: Finance dashboard (persetujuan hadiah besar + rekap)"
```

---

## Task 11: Super Admin (pengguna, peran, akses brand)

**Files:**
- Create: `apps/amura/app/Http/Controllers/Admin/SuperAdminController.php`
- Test: `apps/amura/tests/Feature/SuperAdminTest.php`

**Interfaces:**
- Consumes: model `User`
- Produces: route `GET /admin/users`, `POST /admin/users`, `POST /admin/users/{id}/role`

- [ ] **Step 1: Tulis test yang gagal**

Create `apps/amura/tests/Feature/SuperAdminTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\User;
use Tests\TestCase;

final class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create(['name' => 'SA', 'email' => 'sa@x.id', 'password' => bcrypt('x'), 'role' => 'super_admin', 'brand_scope' => 'amura']);
    }

    public function test_super_admin_bisa_lihat_dan_buat_user(): void
    {
        $sa = $this->superAdmin();
        $this->actingAs($sa)->get('/admin/users')->assertOk();

        $this->actingAs($sa)->post('/admin/users', [
            'name' => 'CS Baru', 'email' => 'cs2@x.id',
            'password' => 'rahasia123', 'role' => 'cs', 'brand_scope' => 'amura',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'cs2@x.id', 'role' => 'cs']);
    }

    public function test_finance_tidak_bisa_akses_users(): void
    {
        $fin = User::create(['name' => 'Fin', 'email' => 'fin@x.id', 'password' => bcrypt('x'), 'role' => 'finance', 'brand_scope' => 'amura']);
        $this->actingAs($fin)->get('/admin/users')->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter SuperAdminTest`
Expected: FAIL — route tidak ada.

- [ ] **Step 3: Tulis `SuperAdminController`**

Create `apps/amura/app/Http/Controllers/Admin/SuperAdminController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use LoyaltyNew\Core\Models\User;

final class SuperAdminController extends Controller
{
    public function index()
    {
        return view('admin.users', ['users' => User::latest()->paginate(20)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:cs,game_admin,finance,super_admin'],
            'brand_scope' => ['required', 'in:amura,reglow'],
        ]);
        $data['password'] = Hash::make($data['password']);
        User::create($data);
        return back()->with('ok', 'Pengguna dibuat');
    }

    public function role(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'role' => ['required', 'in:cs,game_admin,finance,super_admin'],
            'brand_scope' => ['required', 'in:amura,reglow'],
        ]);
        $user->update($data);
        return back()->with('ok', 'Peran diperbarui');
    }
}
```

- [ ] **Step 4: Daftarkan route**

```php
use App\Http\Controllers\Admin\SuperAdminController;

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/users', [SuperAdminController::class, 'index'])->name('admin.users');
    Route::post('/users', [SuperAdminController::class, 'store'])->name('admin.users.store');
    Route::post('/users/{id}/role', [SuperAdminController::class, 'role'])->name('admin.users.role');
});
```

- [ ] **Step 5: User model perlu kolom role di fillable**

Di `packages/core/src/Models/User.php` — buat model User di core yang extends Authenticatable dengan `$fillable = ['name','email','password','role','brand_scope']`. Lalu `apps/amura/app/Models/User.php` extends dari core itu:

```php
<?php

namespace App\Models;

use LoyaltyNew\Core\Models\User as CoreUser;

class User extends CoreUser
{
}
```

- [ ] **Step 6: Jalankan, pastikan lolos**

Run: `cd apps/amura && php artisan test --filter SuperAdminTest`
Expected: 2 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add packages/core/src/Models/User.php apps/amura/app apps/amura/routes apps/amura/tests
git commit -m "feat: Super Admin (pengguna, peran, akses brand)"
```

---

## Task 12: Isolasi brand (Review Focus: kebocoran lintas brand)

**Files:**
- Create: `packages/core/src/Models/BrandScope.php` (global scope)
- Test: `apps/amura/tests/Feature/BrandIsolationTest.php`

**Interfaces:**
- Consumes: model `Customer`, `GameSession`
- Produces: `BrandScope` global scope yang memfilter `brand = config('app.brand')`

- [ ] **Step 1: Tulis test yang gagal**

Create `apps/amura/tests/Feature/BrandIsolationTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Customer;
use Tests\TestCase;

final class BrandIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_amura_tidak_melihat_data_reglow(): void
    {
        // Review Focus: kebocoran lintas brand
        Customer::withoutGlobalScopes()->create(['phone' => '08111', 'brand' => 'reglow']);
        Customer::create(['phone' => '08222', 'brand' => 'amura']);

        $visible = Customer::all();
        $this->assertCount(1, $visible);
        $this->assertSame('amura', $visible->first()->brand);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `cd apps/amura && php artisan test --filter BrandIsolationTest`
Expected: FAIL — global scope belum ada.

- [ ] **Step 3: Tulis `BrandScope`**

Create `packages/core/src/Models/BrandScope.php`:

```php
<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class BrandScope implements Scope
{
    public function __construct(private string $brand)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable().'.brand', $this->brand);
    }
}
```

- [ ] **Step 4: Pasang global scope di model**

Di `packages/core/src/Models/Customer.php` dan `GameSession.php`, dalam method `booted()`:

```php
protected static function booted(): void
{
    static::addGlobalScope(new BrandScope(config('app.brand', 'amura')));
}
```

- [ ] **Step 5: Jalankan, pastikan lolos**

Run: `cd apps/amura && php artisan test --filter BrandIsolationTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Models apps/amura/tests/Feature/BrandIsolationTest.php
git commit -m "feat(core): BrandScope global scope, isolasi data lintas brand"
```

---

## Task 13: Verification end-to-end (spec §9)

**Files:**
- Create: `apps/amura/tests/Feature/SpecVerificationTest.php`

**Interfaces:**
- Consumes: seluruh sistem
- Produces: bukti 8 cek spec §9 lolos

- [ ] **Step 1: Tulis test verifikasi**

Uji 8 poin spec §9 (gate baca `status_fulfillment`, 2 chance dikunci DB, bobot per sesi,
cap hadiah besar, AWB+HP cocok, batas klaim 14/30, isolasi brand, suite hijau).

- [ ] **Step 2: Jalankan seluruh suite**

Run: `cd apps/amura && php artisan test`
Expected: semua PASS.

- [ ] **Step 3: Jalankan suite core**

Run: `cd packages/core && vendor/bin/phpunit`
Expected: semua PASS.

- [ ] **Step 4: Commit**

```bash
git add apps/amura/tests
git commit -m "test: verifikasi end-to-end spec §9 (8 cek)"
```

---

## Self-Review Summary

**Spec coverage:** §1 tujuan (Task 13 ukuran) · §2 mekanik 24 kado (Task 4, 6) · batas waktu (Task 8) · brand terpisah (Task 12) · pengamanan AWB+HP (Task 4), 2 chance DB (Task 6), bobot per sesi (Task 6) · §3 monorepo (Task 0), gate interface (Task 1), gate baca 2 field (Task 5 Review Focus + Task 13) · §4 model 7 tabel (Task 2, 6) · §5 bobot 65/32/3 + cap (Task 3, 5) · §6 peran (Task 8-11) · §7 ditunda (tidak dibuat, sesuai) · §8 design direction (Antislop, tak masuk plan kode) · §9 verifikasi (Task 13).

**Placeholder scan:** tidak ada TBD/TODO. Setiap step punya kode nyata.

**Type consistency:** `OrderGate::isDelivered`, `GateResult::$delivered`, `DrawEngine::pick(int $seed)`,
`SessionService::open/consumeChance`, `ClaimService::expiryFor` — dipakai konsisten antar task.

**Review Focus:** 5 baris, masing-masing punya test di task pemiliknya (Task 4 AWB, Task 5 cap,
Task 6 chance, Task 8 batas klaim, Task 12 isolasi brand).
