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
php artisan storage:link             # gambar pop up promo di /storage
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
| `game_admin` | `/admin/game`, `/admin/catalog`, `/admin/tampilan` | hadiah Gatcha, kuota dan tanggal hadiah utama, periode program, katalog poin bulanan, teks halaman resi dan pop up promo |
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

Kondisi 16 Okt 2026: core 45, Amura 313, Reglow 313, semua lulus.

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

## 9a. Tambahan 21 Okt 2026

- **Landing page `/cara-main`** (publik). Angka, periode, hadiah, dan katalog dibaca dari setting game, jadi tidak perlu diedit saat Game Admin mengubah setting. Gambar di `public/img/cara-main/<brand>/`.
- **Backup setting `/admin/setting`** (hanya Super Admin): export/import setting game per brand sebagai JSON, lewat pratinjau dulu. Setiap import menyimpan setting sebelumnya di `storage/app/private/setting-backups/<brand>/` dan mencatat siapa/kapan di tabel `setting_imports`. Folder itu ikut di-backup server. Tidak menyentuh data pelanggan, saldo, klaim, dan pemakaian kuota/stok.
- **Daftar harian BA `/admin/daftar-harian`** (CS + Super Admin): CSV belum main, hadiah hampir hangus (4 hari), saldo cukup ditukar.
- Deploy: jalankan `php artisan migrate --force` (tabel baru `setting_imports`).
- **Logo resmi** brand di `public/img/brand/`, diatur di `config/play.php` (`logo`, `logo_dark`, `logo_scale`).
- **Kontrol hadiah per jenis pelanggan** (Panel Game): bobot tier terpisah untuk pelanggan baru dan lama, dan tiap hadiah punya isian "Untuk" (semua/baru/lama). Pelanggan baru = nomor HP yang belum pernah main di brand itu. Kolom `segment` kosong = bawaan (hadiah utama: pelanggan lama, lainnya: semua). Aturan ini tidak tampil ke pelanggan.
- Deploy: `php artisan migrate --force` juga menambah kolom `segment` di `prize_catalog` dan `game_sessions`.
- **Kuota bersama hadiah utama**: `prize_catalog.quota_name` menunjuk baris `quota_caps` yang dipakai bersama, mis. lima jenis Philips ke kuota "Philips" (2 unit Nov-Des = 1 per bulan). Jenis yang keluar diacak. Migration `2026_10_21_000003_add_prize_quota_name`.
- **Share ke story**: setelah ambil hadiah, tombol "Bagikan ke Story" membuat gambar 1080x1920 di HP pelanggan (`public/js/share-story.js`) lalu membuka menu share HP (Instagram/WhatsApp). Cadangan: simpan gambar + kirim teks ke WhatsApp. Foto hadiah: unggahan Game Admin (`prize_catalog.image`, disk public `hadiah/<brand>/`) atau bawaan `public/img/hadiah/<brand>/<slug nama>.webp`. Foto unggahan tidak ikut Backup setting. Migration `2026_10_22_000001_add_prize_image`; butuh `php artisan storage:link` (sudah dipakai pop up).

## 10. Kontak

- Produk dan keputusan bisnis: Fai (CRM).
- Soscom API / WMS: Mas Fiqri.
