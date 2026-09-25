# Loyalty New — Amura & Reglow — Design Spec

**Tanggal:** 2026-09-25
**Status:** draft — menunggu review owner
**Bahan sumber:** mock Claude Design (`design/`), handoff 23 Sep & 24 Sep, log scaffold 25 Sep

---

## 1. Tujuan & Ukuran Sukses

Program loyalty berbasis **game Gatcha** untuk dua brand (Amura & Reglow). Hadiah fisik/poin/voucher
dijadikan **alasan menerima paket** (bukan alat menawar COD), untuk **menurunkan retur COD** dan
**menaikkan repeat order**.

**Tiga ukuran sukses** (dari workbook biaya):
1. **Retur turun** (target ≥27% supaya biaya program kembali dari ongkir saja; penuh digabung GMV terselamatkan)
2. **Repeat naik** (repeat customer nilainya 3–4× first-order)
3. **Segmen Loyal naik**

**Target undangan:** first-order **COD Luar Jawa** — Amura 12.265/thn, Reglow 9.818/thn.
**Budget:** Rp55 jt per brand (total Rp110 jt), periode Okt 2026 – Sep 2027, **plafon terpisah per brand**.

---

## 2. Keputusan yang Sudah Dikunci

### Mekanik Gatcha
- Customer input **AWB + nomor HP**, divalidasi cocok dengan data order.
- **24 kado** (grid 4×6) ditampilkan → **pilih 3** → **buka 3** → **ambil 1 terbaik** jadi hadiah final.
- **2 chance per customer**, sama untuk new & existing. Yang beda: **isi tier hadiah** (New lebih sering dapat tier besar), bukan jumlah chance.
- **Semua yang main dapat sesuatu** (win rate 100%); yang dibatasi hanya hadiah besar.
- **Hadiah diundi di server** saat sesi dibuat. Bobot tier **disimpan per sesi**, tidak pernah dikirim ke browser sebelum kado dibuka.

### Timing & Gate
- Link dikirim **manual oleh repeat team** (fase awal), saat paket sedang jalan.
- Game **aktif hanya setelah paket diterima**.
- Poin/hadiah di-credit **hanya setelah order diterima** (bukan retur).

### Batas Waktu (FINAL, 2026-09-25)
| Batas | Nilai |
|---|---|
| Kesempatan main berlaku | **30 hari** sejak paket diterima |
| Klaim hadiah utama (Emas/Philips) | **30 hari** |
| Klaim hadiah biasa (poin/voucher/merch) | **14 hari** |
| Ingat HP untuk login berikutnya | 30 hari (UX, bukan batas klaim) |
| Kunci akses 15 menit (salah 5×) | **DITUNDA** ke fase berikutnya |

### Brand
- **Terpisah total.** Pintu masuk sendiri per brand. Tidak ada tombol "Ganti brand", tidak ada banner lintas brand.
- **2 deploy independen, 2 database terpisah**, tapi **kode inti dishare** via package bersama.

### Klaim
- Lewat **WhatsApp CS**: CS mencari & mencocokkan kode klaim di dashboard.
- Hadiah besar (Emas/Philips) lewat **pemeriksaan Finance**.
- Hadiah biasa (poin/voucher/merch): standar.

### Aturan Pengamanan
- **AWB harus cocok nomor HP** (cegah pakai AWB curian).
- **2 chance dikunci di database**, bukan di tampilan (kalau di tampilan, refresh bisa main lagi).
- **Bobot tier disimpan per sesi** saat dibuat.

---

## 3. Arsitektur

### Struktur Repo (monorepo, 1 git)

```
~/ClaudeCode/LoyaltyNew/
├── AGENTS.md                     entry file + pointer antislop
├── .db-credentials               kredensial Postgres (JANGAN commit)
├── .gitignore
├── design/                       mock Claude Design (sumber kebenaran visual, tidak diubah)
├── packages/core/                ← package Composer bersama (logika inti)
│   ├── composer.json
│   └── src/
│       ├── Gacha/                grid 24, pilih 3, undian berbobot
│       ├── Gate/                 antarmuka OrderGate + StubOrderGate
│       ├── Prizes/               tier, kuota, cap hadiah besar
│       └── Models/               GameSession, GamePrize, PrizeCatalog, Claim
├── apps/amura/                   ← Laravel 12, DB loyalty_new_amura
└── apps/reglow/                  ← Laravel 12, DB loyalty_new_reglow
```

**Kenapa monorepo + packages/core:**
- Deploy & DB tetap terpisah penuh (dua app benar-benar independen saat runtime).
- Logika yang identik (undian, gate, kuota) **tidak terduplikasi** → satu fix bug menutup dua brand.
- `apps/*/composer.json` mengikat `packages/core` lewat **path repository**.

### Gate Paket Diterima (titik paling rawan)

Gate adalah **interface**, bukan implementasi langsung. Alasannya: sumber status paket nanti adalah
**API ke Markom per-pack**, dan integrasi itu **ditunda** ("kerjakan setelah semua selesai").

```php
namespace LoyaltyNew\Core\Gate;

interface OrderGate
{
    public function isDelivered(string $awb, string $phone): GateResult;
}

final class GateResult
{
    public function __construct(
        public readonly bool $delivered,
        public readonly ?string $reason = null,
    ) {}
}
```

Implementasi fase 1: **`StubOrderGate`** (mis. selalu lolos atau flag admin), supaya alur bisa
dites utuh end-to-end. Gate nyata: **`MarkomApiOrderGate`** (sudah dibuat) — memakai sumber
`OrderStatusSource` yang bisa dikonfigurasi:
- `MarkomHttpOrderStatusSource` — HTTP ke API Markom (produksi).
- `FairtrackSqliteOrderStatusSource` — baca langsung DB FairTrack Lead, read-only (smoke/transisi).

Pilih lewat env: `MARKOM_GATE=stub|markom` dan `MARKOM_DRIVER=markom_api|fairtrack_sqlite`.
Kelulusan tetap `status_fulfillment='complete'` + AWB cocok HP (`AwbMatcher`).

> [!important] Koreksi wajib untuk gate nyata (dari data live FairTrack Lead)
> Gate **tidak boleh** hanya membaca `status_order`. Data live membuktikan dua kolom sering berbeda:
> - **133 order** `status_order='sent'` + `status_fulfillment='complete'` (sudah sampai, status_order belum update)
> - **491 order** `sent` + `problem` (gagal antar, belum retur)
>
> **Gate benar = `status_fulfillment = 'complete'`.** Padanan aman juga menerima
> `status_order='completed'` yang konsisten, tapi penentu utamanya `status_fulfillment`.

### Alur Data Inti

```
Customer (HP) → input AWB + no. HP
   → [OrderGate] cek paket diterima          ← StubOrderGate sekarang, MarkomApiOrderGate nanti
   → buat GameSession (token sesi, 2 chance, bobot per sesi)
   → tampilkan 24 kado
   → pilih 3 → buka 3 → ambil 1
   → hadiah tersimpan (status: menunggu klaim)
   → klaim via WhatsApp CS: CS cocokkan kode
   → hadiah kecil: selesai · hadiah besar: pemeriksaan Finance → selesai
```

---

## 4. Model Data

Setiap brand punya DB sendiri dengan skema identik.

### `customers`
| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint PK | |
| phone | string, indexed | identitas utama (dinormalisasi) |
| name | string nullable | dari data order |
| brand | string | `amura` / `reglow` |
| last_seen_at | timestamp | untuk "HP diingat 30 hari" |
| timestamps | | |

> Tanpa akun/password. Customer publik masuk lewat AWB+HP.

### `game_sessions`
| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint PK | |
| customer_id | FK customers | |
| brand | string | |
| session_token | string unique | token sesi (cookie/URL bertanda tangan) |
| chance_total | smallint default 2 | **dikunci di DB** |
| chance_used | smallint default 0 | |
| tier_config | jsonb | **bobot tier disimpan per sesi** |
| awb | string | order yang jadi dasar |
| gate_source | string | `stub` / `markom` (jejak implementasi gate) |
| expires_at | timestamp | `created_at + 30 hari` |
| timestamps | | |

### `game_prizes`
Satu sesi menghasilkan **24 baris** (24 kado), masing-masing punya hasil undian di server.
| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint PK | |
| game_session_id | FK | |
| slot | smallint | 1..24 |
| tier | string | `poin` / `voucher` / `merch` / `hadiah_besar` |
| prize_catalog_id | FK nullable | |
| picked | boolean | customer pilih kado ini |
| opened | boolean | termasuk 3 yang dibuka |
| taken | boolean | hadiah final (1 terbaik) |
| timestamps | | |

### `prize_catalog`
| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint PK | |
| brand | string | |
| name | string | "Emas Antam", "Voucher Ongkir 20rb", dll |
| tier | string | `poin`/`voucher`/`merch`/`hadiah_besar` |
| point_value | int nullable | untuk tier poin |
| face_value | int nullable | rupiah |
| quota_total | int nullable | mis. hadiah MVP 50 pcs/SKU |
| quota_used | int default 0 | |
| is_capped | boolean | hadiah besar: cap tetap per program |
| active | boolean | |
| timestamps | | |

### `claims`
| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint PK | |
| game_session_id | FK | |
| game_prize_id | FK | hadiah yang diambil |
| claim_code | string unique | kode yang CS cocokkan |
| status | string | `menunggu` / `diproses` / `perlu_finance` / `selesai` / `hangus` |
| handled_by | FK users nullable | CS |
| finance_by | FK users nullable | Finance |
| claimed_at | timestamp nullable | |
| expires_at | timestamp | 14 atau 30 hari sesuai tier |
| timestamps | | |

### `users` (internal, Laravel auth bawaan)
| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| password | string | |
| role | string | `cs` / `game_admin` / `finance` / `super_admin` |
| brand_scope | string | brand yang boleh diakses |
| timestamps | | |

### `quota_caps` (hadiah besar, biaya tetap)
| Kolom | Tipe | Catatan |
|---|---|---|
| id | bigint PK | |
| brand | string | |
| prize_name | string | Emas / Philips |
| cap_units | int | 1 (Emas), 2 (Philips) |
| used_units | int default 0 | |
| program_start | date | Okt 2026 |
| program_end | date | Sep 2027 |

---

## 5. Konfigurasi Undian (Final)

- **Tier:** `T1 poin 65%` · `T2 voucher 32%` · `T3 merch 3%`.
- **Hadiah besar = biaya tetap dengan cap, bukan tarif per pemenang:**
  Emas **1 unit** + Philips **2 unit** per program per brand (Rp9,2 jt/brand).
- Hadiah besar **tidak dikalikan jumlah pemenang** — ini penyebab utama over-budget draft lama
  (Emas 7 unit / 0,1% dihitung biaya variabel → 217% budget).
- **Kuota MVP:** 50 pcs per SKU per brand per tahun.

**Angka pendukung (dari workbook, jangan dihitung ulang dari nol):**

| | Amura | Reglow |
|---|---|---|
| Chance total (2× yang main) | 9.812 | 7.854 |
| Grand total biaya | Rp49,7 jt (90,3%) | Rp41,6 jt (75,6%) |

---

## 6. Peran & Hak Akses

| Peran | Akses |
|---|---|
| **Customer** (publik, tanpa akun) | Input AWB+HP, main Gatcha, lihat hadiah, klaim WA |
| **CS** | Dashboard klaim: cari & cocokkan kode, proses hadiah biasa |
| **Game Admin** | Katalog hadiah, bobot tier, kuota |
| **Finance** | Persetujuan hadiah besar + rekap |
| **Super Admin** | Pengguna, peran, akses brand |

Bahasa antarmuka: **Indonesia** · Mobile first (390 px).

---

## 7. Yang DITUNDA (bukan bagian fase 1)

| Item | Alasan |
|---|---|
| **Integrasi API Markom per-pack** (gate nyata) | Dikerjakan setelah semua selesai. Fase 1 pakai `StubOrderGate` |
| **Kunci akses 15 menit** (salah 5×) | Dikonfirmasi user: tunda, fokus alur utama |
| **Push notification** | Belum di desain |

---

## 8. Design Direction (untuk antislop, R-37)

Arah visual **sudah ada** dari mock Claude Design, jadi R-37 terpenuhi.

- **Palet dasar:** gading (dasar), tinta cokelat tua.
- **Warna brand:** Amura hijau-teal `#1E6B63` · Reglow terakota `#A8461F`.
- **Emas** `#F0D08A` / `#8A6318`: hanya untuk hadiah besar.
- **Font:** Plus Jakarta Sans + DM Serif Display.
- **Semangat visual:** teater Gatcha (panggung, kado jatuh, sinar berputar, ledakan emas saat
  hadiah utama). Motif identitas: **kado + emas**.

Dari mock: kado jatuh 0,45 s/easeOutBack, pilih = lompat + pop, buka 3 kado ke tengah,
hadiah utama = kilat emas + getar + konfeti. Detail gerak di `design/gatcha-anim.jsx`.

---

## 9. Verification

| # | Cek | Ekspektasi | Status |
|---|---|---|---|
| 1 | Gate baca `status_fulfillment='complete'` | 133 order `sent`+`complete` lolos; 491 `sent`+`problem` ditolak | ✅ Terverifikasi — `MarkomApiOrderGate` + test (unit core, `Http::fake`, integrasi via `SessionService`, + uji data FairTrack asli) |
| 2 | 2 chance dikunci DB | Refresh/token ulang tidak menambah chance | ✅ |
| 3 | Bobot per sesi | Sebaran hasil 1000 sesi mendekati T1 65 / T2 32 / T3 3 | ✅ |
| 4 | Cap hadiah besar | Emas ke-2 dalam 1 program ditolak | ✅ |
| 5 | AWB+HP harus cocok | AWB valid + HP salah → ditolak | ✅ |
| 6 | Batas klaim | Hadiah biasa >14 hari → hangus; hadiah utama >30 hari → hangus | ✅ Terverifikasi — `ClaimService::expireOverdue` + `loyalty:expire-claims` (terjadwal harian, brand-scoped, idempotent) |
| 7 | Isolasi brand | App Amura tidak bisa baca data Reglow | ✅ |
| 8 | `tsc`/`php artisan test` | hijau | ✅ |

---

## 10. Referensi

- Handoff: `05 Handoff Prompts/loyalty/2026-09-24-loyalty-new-design-handoff.md`
- Log scaffold: `05 Handoff Prompts/loyalty/2026-09-25-loyalty-new-scaffold-progress.md`
- Keputusan bisnis: `05 Handoff Prompts/loyalty/2026-09-23-loyalty-management-handoff.md`
- Mock: `design/Loyalty Amura Reglow.dc.html` · `design/gatcha-anim.jsx`
