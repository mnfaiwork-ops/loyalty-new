# Laporan Perbaikan Bug — 2026-09-28

Temuan dari uji jalan repo (server dev + skenario HTTP + browser), lalu diperbaiki di
branch `claude/repo-testing-bug-check-df6tlv`. Semua bug di bawah sudah dibuktikan dengan
menjalankan aplikasi, bukan hanya dari membaca kode.

## Ringkasan

| # | Bug | Tingkat | Status |
|---|-----|---------|--------|
| 1 | AWB beda huruf besar/kecil membuat sesi kedua (kesempatan main dobel) | Kritis | Diperbaiki |
| 2 | Kuota hadiah besar habis saat sesi dibuat, bukan saat dimenangkan | Kritis | Diperbaiki |
| 3 | Sesi kedaluwarsa masih bisa dimainkan dan menerbitkan klaim | Kritis | Diperbaiki |
| 4 | CS bisa mengubah klaim `hangus`/`selesai` (dan klaim lewat batas) | Kritis | Diperbaiki |
| 5 | Refresh di tengah ronde membuat papan macet | Sedang | Diperbaiki |
| 6 | Setelah kunci 15 menit habis, 1x salah langsung terkunci lagi | Sedang | Diperbaiki |
| 7 | Bobot tier 0/0/0 membuat `/play/start` error 500 | Sedang | Diperbaiki |
| + | Smoke seeder: sesi seed tertahan di ronde setengah jalan, kuota tidak tercatat | Kecil | Diperbaiki |
| + | Brand di `AppServiceProvider` ditulis manual | Kecil | Diperbaiki |
| + | `pick` tanpa kunci transaksi (race) | Kecil | Diperbaiki |
| + | Panel admin tidak pernah menampilkan pesan error | Kecil | Diperbaiki |

Hasil test setelah perbaikan:

| Suite | Sebelum | Sesudah |
|---|---|---|
| `packages/core` | 38 lulus | 38 lulus |
| `apps/amura` | 127 lulus | 148 lulus |
| `apps/reglow` | 118 lulus | 139 lulus |

Test regresi baru ada di `tests/Feature/BugfixRegressionTest.php` (isi sama di kedua app,
brand diambil dari `config('app.brand')`). Saat dijalankan terhadap kode **lama**, 19 dari 21
test gagal. Dua sisanya menjaga logika kuota yang baru (reveal dua kali, take tanpa reveal).

---

## 1. AWB beda huruf besar/kecil → sesi ganda

**Gejala.** `CASE-AWB-777` dan `case-awb-777` dengan HP yang sama mendapat dua token sesi.
Satu paket bisa dimainkan 4 kali, bukan 2.

**Penyebab.** Gate mencocokkan AWB tanpa peduli huruf (`strcasecmp`), tapi AWB disimpan dan
dicari persis seperti yang diketik. Unique index `(customer_id, awb)` di Postgres peka huruf.

**Perbaikan.**
- `AwbMatcher::normalizeAwb()` baru: `trim` + huruf besar.
- `SessionService::open()` menormalkan AWB sebelum gate, pencarian, dan penyimpanan.
- `AttemptLimiter` memakai normalisasi AWB dan HP yang sama dengan `AwbMatcher`.

**File.** `packages/core/src/Gate/AwbMatcher.php`, `packages/core/src/Gacha/SessionService.php`,
`packages/core/src/Gacha/AttemptLimiter.php`

## 2. Kuota hadiah besar terpakai untuk hadiah yang tidak pernah ditemukan

**Gejala.** Tiap sesi baru langsung memotong kuota lalu menaruh hadiah besar di satu kotak acak.
Pelanggan hanya membuka 6 dari 24 kotak, jadi sekitar 75% hadiah itu tidak pernah terbuka
dan kuotanya tidak kembali. Saat diuji, 3 sesi pertama sudah menghabiskan Emas (1/1) dan
Philips (2/2) tanpa satu pun terbuka.

**Perbaikan.** Hadiah tetap diundi di server saat sesi dibuat. Yang pindah hanya pencatatan
kuotanya:
- Saat sesi dibuat, hadiah besar ditaruh di slot acak **hanya kalau** kuota masih ada.
  Kuota belum dipotong.
- Saat kado dibuka (`reveal`, atau `take` langsung tanpa reveal), `SessionService::openRound()`
  memotong kuota secara atomik. Kalau kuota sudah diambil sesi lain, kado itu diundi ulang
  ke tier biasa **sebelum** isinya dikirim ke browser. Pelanggan tidak pernah melihat hadiah
  besar yang lalu dibatalkan.
- Hadiah besar yang terbuka tapi tidak dipilih saat `take` dikembalikan kuotanya.
- Reveal dua kali tidak memotong kuota dua kali, karena hanya kado yang belum terbuka yang diproses.
- Kelas baru `Prizes/BigPrizeQuota` (`available` / `reserve` / `release`).

**File.** `packages/core/src/Prizes/BigPrizeQuota.php` (baru),
`packages/core/src/Gacha/SessionService.php`, `app/Http/Controllers/PlayController.php`

**Test lama yang diubah.** `SpecVerificationTest::test_04_cap_hadiah_besar` (kedua app) dan
`SessionServiceTest::test_hadiah_besar_dibatasi_cap` sebelumnya menegaskan kuota terpotong saat
sesi dibuat. Sekarang test itu menegaskan aturan spec §9 #4 lewat alur nyata: pembuka pertama
mendapat Emas, pembuka kedua mendapat tier biasa, `used_units` tetap 1, dan sesi baru setelah
kuota habis tidak mendapat hadiah besar.

## 3. Sesi kedaluwarsa masih bisa dimainkan

**Gejala.** Setelah `expires_at` lewat, `board`/`pick`/`take` tetap jalan dan kode klaim terbit.
Kalau pelanggan masuk ulang, `open()` gagal membuat sesi baru karena unique index, lalu
mengembalikan sesi lama yang sudah kedaluwarsa.

**Perbaikan.**
- `open()` mencari sesi paket ini tanpa filter kedaluwarsa. Kalau sudah lewat, lempar
  `SessionExpiredException` ("Masa main untuk paket ini sudah berakhir.").
- `PlayController::start()` menangkapnya **tanpa** menambah hitungan lockout, karena input
  pelanggan benar.
- `board` mengarahkan ke form dengan pesan tersebut. `pick`/`reveal`/`take` membalas 422.

**File.** `packages/core/src/Gacha/SessionExpiredException.php` (baru),
`packages/core/src/Gacha/SessionService.php`, `app/Http/Controllers/PlayController.php`

**Test lama yang diubah.** `SessionServiceTest::test_open_fallback_unik_saat_sesi_kedaluwarsa`
sebelumnya menegaskan sesi kedaluwarsa dikembalikan. Test itu diganti
`test_open_sesi_kedaluwarsa_ditolak_tanpa_sesi_baru`.

## 4. CS bisa menghidupkan lagi klaim final atau kedaluwarsa

**Gejala.** Klaim `hangus` bisa diubah CS menjadi `selesai`, sehingga batas klaim 14/30 hari
bisa dilewati.

**Perbaikan.**
- `CsClaimController::resolve`: klaim di luar `ClaimService::OPEN_STATUSES` (`selesai`, `hangus`)
  ditolak. Klaim yang sudah lewat `expires_at` hanya boleh ditandai `hangus`.
- `FinanceController::approve`: klaim `perlu_finance` yang sudah lewat batas tidak bisa disetujui.
  Celahnya sama, hanya lewat jalur Finance.
- `layouts/admin.blade.php` sekarang menampilkan pesan error. Sebelumnya pesan penolakan dari CS
  maupun Finance tidak pernah terlihat.

**File.** `app/Http/Controllers/Admin/CsClaimController.php`,
`app/Http/Controllers/Admin/FinanceController.php`, `resources/views/layouts/admin.blade.php`

## 5. Refresh di tengah ronde membuat papan macet

**Gejala.** Refresh setelah membuka 3 kado tapi sebelum memilih 1: ketiga kotak nonaktif,
pilihan hadiah hilang, dan memilih kotak baru ditolak ("Sudah memilih slot ronde ini").
Kesempatan belum terpakai, tapi pelanggan tidak bisa melanjutkan.

**Perbaikan.**
- `board` mengirim `pendingSlots` (ronde yang sudah dipilih) dan `pendingRevealed` (isi kado,
  **hanya** kalau ketiganya sudah dibuka, jadi isi kado tetap tidak bocor sebelum dibuka).
- JS papan: kalau ronde sudah dibuka, pilihan hadiah langsung tampil lagi. Kalau baru dipilih,
  tombol "Buka 3 Kado" aktif dan melanjutkan tanpa `pick` ulang.
- Kalau `pick` atau `reveal` gagal (mis. jaringan), tombol aktif lagi supaya bisa dicoba ulang.

Sudah diuji di Chromium (viewport 390 px): refresh setelah reveal → pilihan hadiah tampil →
ambil hadiah berhasil. Refresh setelah pick → tombol Buka aktif → buka berhasil.

**File.** `resources/views/play/board.blade.php` (kedua brand), `app/Http/Controllers/PlayController.php`

## 6. Lockout mengunci lagi setelah 1x salah

**Gejala.** Setelah kunci 15 menit habis, hitungan gagal tidak direset (`fails=6`), jadi satu
salah ketik langsung mengunci 15 menit lagi. Kegagalan lama juga tidak pernah kedaluwarsa:
4 salah ketik bulan lalu ditambah 1 hari ini sudah mengunci.

**Perbaikan.** `AttemptLimiter::bump()` mereset hitungan ke 0 kalau kunci sudah habis atau
kegagalan terakhir lebih lama dari jendela kunci (15 menit). Perhitungan sisa waktu kunci
memakai `now()` (Carbon), bukan `time()`, jadi bisa diuji dengan `travel()`.

**File.** `packages/core/src/Gacha/AttemptLimiter.php`

## 7. Bobot tier 0/0/0 → error 500 untuk semua pelanggan

**Gejala.** Game Admin bisa menyimpan bobot 0/0/0. Setelah itu `DrawEngine` melempar
`InvalidArgumentException` yang tidak ditangkap, dan `/play/start` mengembalikan HTTP 500.

**Perbaikan.**
- `UpdateTierConfigRequest` menolak total bobot 0 ("Total bobot tier harus lebih dari 0.").
- `TierConfig::current()` menganggap total 0 tidak valid dan memakai bobot default, sebagai
  pengaman kalau data seperti itu sudah terlanjur tersimpan.

**File.** `app/Http/Requests/UpdateTierConfigRequest.php`, `packages/core/src/Prizes/TierConfig.php`

## Perbaikan tambahan

- **Smoke seeder.** Slot 2 dan 3 di sesi seed tetap `picked=true`, jadi sesi itu terlihat
  "di tengah ronde" dan tidak bisa dimainkan lagi. Sekarang sama dengan alur `take` nyata.
  Hadiah besar seed dihubungkan ke katalog dan memotong kuota. Menjalankan seeder ulang tidak
  lagi mereset `used_units` ke 0.
- **`AppServiceProvider`** memakai `config('app.brand')`, tidak lagi `'amura'`/`'reglow'` manual.
- **`pick`** sekarang di dalam transaksi dengan `lockForUpdate` pada sesi, sama seperti `take`.
  Race dua `pick` bersamaan belum bisa dimunculkan di `artisan serve` (satu proses), jadi ini
  pencegahan.
- **`take`** menjalankan semua cek sebelum menulis apa pun. Sebelumnya slot sempat ditandai
  terbuka lalu balasan 422 dikirim tanpa rollback.

## Hal yang perlu diputuskan atau diketahui

1. **Ronde yang dibuka lalu ditinggal.** Kalau pelanggan membuka hadiah besar lalu tidak
   pernah memilih, kuota tertahan di sesi itu. Setelah perbaikan #5 pelanggan bisa kembali dan
   melanjutkan, tapi kalau tidak kembali sampai sesi kedaluwarsa, kuotanya tidak otomatis
   dikembalikan. Pilihannya: kembalikan lewat job terjadwal saat sesi kedaluwarsa, atau terima
   apa adanya.
2. **Klaim hadiah besar yang hangus** tidak mengembalikan kuota. Ini keputusan bisnis: apakah
   Emas yang tidak diklaim boleh muncul lagi untuk pelanggan lain?
3. **Data AWB lama.** Sesi yang sudah tersimpan dengan AWB huruf kecil tidak otomatis
   dinormalkan. Karena masih fase 1 (belum produksi), belum ada migrasi data. Kalau sudah ada
   data nyata, perlu migrasi `upper(trim(awb))` yang menangani bentrok unique index.
4. **Lingkungan uji.** Container uji memakai PHP 8.4, sedangkan `loyaltynew/core` mensyaratkan
   PHP ^8.5. Dependensi dipasang dengan `composer install --ignore-platform-req=php`. Tidak ada
   file composer yang diubah.
5. **Pint.** Repo belum bersih-Pint sejak sebelum perubahan ini (misalnya `AttemptLimiter`,
   `SessionService`, `AppServiceProvider`). File yang tidak terkait perbaikan tidak diformat ulang.

## Cara verifikasi ulang

```bash
(cd packages/core && vendor/bin/phpunit)
(cd apps/amura  && php artisan test)
(cd apps/reglow && php artisan test)
(cd apps/amura  && php artisan test --filter=BugfixRegressionTest)
```
