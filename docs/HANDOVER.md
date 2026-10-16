# Serah terima Loyalty New ke tim IT

Program loyalty berbasis game Gatcha untuk dua brand, Amura dan Reglow. Pelanggan memasukkan
nomor resi dan nomor HP setelah paket diterima, membuat PIN 4 angka, lalu membuka kado. Hadiah
diklaim lewat WhatsApp CS. Poin bisa ditukar di katalog bulanan.

Dokumen ini untuk orang yang akan memasang, merawat, dan mengubah aplikasi. Langkah deploy
rinci ada di [`DEPLOY.md`](DEPLOY.md).

## 1. Struktur repo

| Folder | Isi |
|---|---|
| `packages/core` | Logika bersama: gate paket diterima, undian, kuota, klaim, poin, PIN. Namespace `LoyaltyNew\Core`. |
| `apps/amura` | App Laravel 12 untuk Amura. Sumber utama semua kode app. |
| `apps/reglow` | Salinan `apps/amura` untuk Reglow. Jangan diedit langsung, lihat bagian 6. |
| `scripts/` | `provision-brand-dbs.sh` (buat DB), `use-brand-db.sh` (arahkan app ke DB), `sync-reglow.sh` (salin Amura ke Reglow). |
| `docs/` | `DEPLOY.md`, dokumen API Soscom (`docs/api/`), spec dan plan awal (`docs/superpowers/`). |
| `design/` | Mock desain, sumber acuan visual. Jangan diubah. |

Kedua app memakai `packages/core` lewat Composer path repository (`../../packages/core`).
Brand ditentukan oleh `APP_BRAND` di `.env`. Data dua brand terpisah total: DB sendiri dan
`BrandScope` di model.

## 2. Kebutuhan server

- PHP 8.5, Composer 2.
- PostgreSQL 18. Satu DB per brand.
- Tidak perlu Node atau build front end. CSS dan JS ada di `public/css` dan `public/js`.
- HTTPS untuk dua domain atau subdomain (satu per brand), lewat reverse proxy (nginx atau sejenisnya).
- Cron tiap menit untuk `schedule:run`, satu per app.
- Cache, session, dan queue memakai driver `database`. Tabel `cache_locks` dipakai untuk mengunci
  percobaan PIN per nomor. Redis tidak wajib.

## 3. Variabel `.env`

Contoh lengkap ada di `apps/<brand>/.env.example`. Yang wajib diisi di produksi:

| Variabel | Nilai produksi | Keterangan |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | |
| `APP_KEY` | dibuat di server | `php artisan key:generate`, beda per app |
| `APP_URL` | `https://<domain brand>` | |
| `APP_BRAND` | `amura` atau `reglow` | |
| `DB_*` | DB per brand | lihat `DEPLOY.md` B1 |
| `MARKOM_GATE` | `markom` | `stub` ditolak saat produksi (app gagal boot) |
| `MARKOM_DRIVER` | `soscom_api` | |
| `SOSCOM_API_URL` | `https://wms-api.sinergisuperapp.com` | WMS tidak punya staging |
| `SOSCOM_API_KEY` | key brand app ini | beda Amura dan Reglow, dari WMS |
| `SESSION_SECURE_COOKIE` | `true` | wajib di HTTPS |
| `SESSION_ENCRYPT` | `true` | |
| `TRUSTED_PROXIES` | IP reverse proxy | jangan `*` kalau app bisa diakses langsung |
| `ADMIN_PASSWORD` | password kuat | seeder menolak produksi tanpa ini |
| `CS_WHATSAPP` | nomor CS brand | kosong = tombol WhatsApp disembunyikan |
| `MARKOM_WEBHOOK_SECRET` | rahasia acak | hanya kalau webhook `/api/webhooks/markom` dipakai |

Rahasia diserahkan lewat password manager, tidak lewat chat atau git. File `.env` di server
diberi izin `640`.

## 4. Pemasangan pertama (ringkas)

Urutan lengkap ada di `DEPLOY.md` bagian "Urutan aman produksi". Per app:

```bash
cd apps/amura
composer install --no-dev --optimize-autoloader
cp .env.example .env    # lalu isi nilai produksi
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # hanya AdminUserSeeder (admin@<brand>)
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`db:seed` dijalankan sebelum `config:cache`, karena `AdminUserSeeder` membaca `ADMIN_PASSWORD`
langsung dari `.env`. Jangan jalankan `php artisan loyalty:smoke-seed` di produksi. Perintah itu membuat akun uji
dan data dummy.

Cron:

```cron
* * * * * cd /path/apps/amura  && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path/apps/reglow && php artisan schedule:run >> /dev/null 2>&1
```

## 5. Peran admin

Login staf di `/login`. Akun hanya berlaku di brand-nya sendiri.

| Peran | Halaman | Tugas |
|---|---|---|
| `super_admin` | `/admin/users` + semua halaman | buat akun staf, ganti peran |
| `cs` | `/admin/claims`, `/admin/shipping`, `/admin/customers` | konfirmasi klaim WhatsApp, kirim hadiah, reset PIN pelanggan |
| `game_admin` | `/admin/game`, `/admin/catalog` | hadiah Gatcha, kuota dan tanggal hadiah utama, periode program, katalog poin bulanan |
| `finance` | `/admin/finance` | setujui klaim hadiah utama |

Halaman pelanggan: `/play` (masukkan resi), `/masuk` (nomor HP + PIN), `/` (Beranda),
`/hadiah`, `/katalog`.

## 6. Mengubah kode

1. Ubah kode di `apps/amura` atau `packages/core`, tidak pernah langsung di `apps/reglow`.
2. Jalankan `bash scripts/sync-reglow.sh` dari root repo. Skrip ini menyalin `app`, migration,
   view, CSS/JS, route, dan beberapa config ke Reglow, serta menyalin tes dengan nama brand ditukar.
   Yang tidak disalin karena memang per brand: `config/app.php`, `database/seeders`, `.env*`.
3. Jalankan ketiga suite tes (berurutan kalau kedua app masih berbagi DB tes):

   ```bash
   (cd packages/core && vendor/bin/phpunit)
   (cd apps/amura && php artisan test)
   (cd apps/reglow && php artisan test)
   ```

4. Buka PR ke `master`.

Kondisi 16 Okt 2026: core 45, Amura 300, Reglow 300, semua lulus.

## 7. Perintah artisan

| Perintah | Jadwal | Fungsi |
|---|---|---|
| `loyalty:expire-claims` | harian 01:00 | klaim lewat batas jadi hangus |
| `loyalty:expire-points` | harian 01:05 | poin lewat masa berlaku hangus |
| `loyalty:katalog-awal --period=YYYY-MM` | manual | pasang dan terbitkan katalog poin bulan pertama |
| `loyalty:smoke-seed` | manual, dev saja | data dummy dan akun uji |

Semua perintah berlaku untuk brand app tempat perintah dijalankan.

## 8. Integrasi luar

- **Soscom Open API (WMS):** cek resi + HP dan status paket. Kontrak di `docs/api/soscom-open-api.md`
  dan `DEPLOY.md` B2. Kontak: Mas Fiqri.
- **WhatsApp CS:** hanya tautan `wa.me` ke `CS_WHATSAPP`. Belum ada API WhatsApp, OTP, atau
  notifikasi otomatis.
- **Webhook Markom** (`POST /api/webhooks/markom`): opsional, ditandatangani HMAC dengan
  `MARKOM_WEBHOOK_SECRET`. Kalau rahasia kosong, semua request ditolak.

## 9. Risiko yang sudah diketahui

- **Akun bisa diambil lewat label paket.** Resi dan HP tercetak di label. Orang pertama yang
  memasukkannya membuat PIN. Penutupnya OTP WhatsApp, belum dibuat. Pemulihan sekarang: CS reset PIN.
- **Kontrol orang dalam CS.** CS mengetik sendiri nomor pengirim saat konfirmasi klaim, dan reset
  PIN memberi akses ke akun pelanggan. Jejaknya ada di `claim_events` dan `customer_pin_resets`.
  Perlu dicek berkala.
- **PIN 4 angka.** Diperkuat dengan kunci 5 kali salah per nomor per jaringan (15 menit), 20 kali
  salah per nomor per hari (24 jam), dan batas per IP.
- **Gate diuji langsung ke produksi.** WMS tidak punya staging. Pakai resi sampel.
- **Belum dikerjakan:** `claim_code` bisa bentrok tanpa retry, email staf unik secara global
  (bukan per brand), super admin bisa mengunci akunnya sendiri.

## 10. Kontak

- Produk dan keputusan bisnis: Fai (CRM).
- Soscom API / WMS: Mas Fiqri.
