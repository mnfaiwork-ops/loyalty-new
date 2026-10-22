# Panduan Deploy Rilis Kejutan Kado (22 Oktober 2026)

Untuk: tim IT yang men-deploy `rewards.amura.id` dan `rewards.reglow.id`.
Dari: Fai (CRM). Pertanyaan soal isi program ke Fai; soal kode lihat `docs/HANDOVER.md`.

Program Kejutan Kado mulai menerima pesanan **27 Oktober 2026**. Rilis ini perlu sudah jalan di produksi
dan setting hadiahnya sudah di-import **sebelum tanggal itu**.

---

## 1. Ringkasan

| | |
|---|---|
| Versi yang sudah di produksi (perkiraan) | `a7b9580` (PR #4, 16 Okt 2026). Cek di server, lihat langkah 2.1 |
| Versi rilis ini | `master` setelah PR #11 di-merge |
| Isi rilis | PR #5 sampai #11, semuanya dikerjakan 21-22 Okt 2026 |
| Migration baru | 4, semuanya menambah tabel/kolom (tidak mengubah atau menghapus data) |
| Env baru di `.env` | Tidak ada |
| Dependency baru (composer/npm) | Tidak ada |
| Data pelanggan | Tidak disentuh |
| Perkiraan waktu | 30-45 menit untuk kedua app, termasuk import setting dan cek |
| Downtime | Tidak perlu. Migration hanya menambah kolom yang boleh kosong |

Yang berubah untuk pengguna:

| PR | Fitur | Siapa yang melihat |
|---|---|---|
| #5 | Landing page cara main `/cara-main` (publik), isi otomatis dari setting game | Pelanggan |
| #6 | Backup setting `/admin/setting` (export/import setting game, hanya Super Admin) + Daftar harian BA `/admin/daftar-harian` (unduh CSV) | Super Admin, CS |
| #7 | Hadiah utama (Philips, Emas) hanya keluar mulai pesanan kedua di program | Pelanggan |
| #8 | Logo resmi Amura dan Reglow di semua halaman | Semua |
| #9 | Kontrol isi kado untuk pelanggan baru vs lama di Panel Game | Game Admin |
| #10 | Kuota bersama hadiah utama (lima jenis Philips berbagi satu kuota) | Game Admin, pelanggan |
| #11 | Tombol "Bagikan ke Story" (Instagram/WhatsApp) setelah ambil hadiah + foto hadiah bisa diunggah di Panel Game | Pelanggan, Game Admin |

---

## 2. Sebelum mulai

### 2.0 Patch PHP dulu (keamanan, wajib)

PHP-FPM di server perlu diupdate karena celah PHP2Shell (CVE-2026-109618, CVE-2026-109619). Ikuti
[PATCH-PHP2SHELL.md](PATCH-PHP2SHELL.md), lalu pastikan ini menampilkan `Hasil: AMAN`:

```bash
bash scripts/check-php2shell.sh
```

Kalau paket patch belum tersedia, pasang mitigasi sementara (langkah 4 di file itu) lalu lanjut deploy.

### 2.1 Cek versi yang sedang jalan

Di server, di folder repo:

```bash
cd /path/to/LoyaltyNew
git log -1 --oneline
git status --short
```

- Kalau hasilnya `a7b9580` atau commit sebelum itu: lanjut, panduan ini berlaku.
- Kalau `git status` menunjukkan file yang diubah langsung di server: simpan dulu (`git stash` atau salin), lalu kabari Fai.

### 2.2 Backup database

Satu dump per database yang dipakai app (nama DB lihat `DB_DATABASE` di `.env` tiap app):

```bash
pg_dump -Fc -U <user> -h <host> <db_amura>  > ~/backup/loyalty-amura-$(date +%Y%m%d-%H%M).dump
pg_dump -Fc -U <user> -h <host> <db_reglow> > ~/backup/loyalty-reglow-$(date +%Y%m%d-%H%M).dump
```

Kalau kedua app masih memakai satu database (`loyalty_new`), cukup satu dump.

### 2.3 Siapkan

- Akun **Super Admin** di kedua app (untuk import setting di langkah 4). Kalau belum ada, Super Admin lain bisa
  membuatnya di `/admin/users`.
- File setting hadiah sudah ada di repo, ikut terbawa saat `git pull`:
  - `docs/deploy/2026-10-22/setting-amura.json`
  - `docs/deploy/2026-10-22/setting-reglow.json`

---

## 3. Deploy kode (ulangi untuk Amura dan Reglow)

Ganti `/path/to/LoyaltyNew` dengan lokasi repo di server. Langkah 3.1 sekali saja; 3.2 sampai 3.5 untuk tiap app.

### 3.1 Ambil kode

```bash
cd /path/to/LoyaltyNew
git fetch origin
git checkout master
git pull --ff-only origin master
git log -1 --oneline        # harus commit merge PR #11 atau sesudahnya
```

### 3.2 Dependency (tidak ada yang baru, tetap jalankan supaya autoload segar)

```bash
for app in amura reglow; do
  (cd apps/$app && composer install --no-dev --optimize-autoloader --no-interaction)
done
```

### 3.3 Migration

```bash
for app in amura reglow; do
  (cd apps/$app && php artisan migrate --force && php artisan migrate:status | grep -i pending)
done
```

`grep pending` harus tidak menampilkan apa-apa. Migration rilis ini (idempoten, aman dijalankan dua kali
kalau kedua app berbagi database):

| Migration | Isi |
|---|---|
| `2026_10_21_000001_create_setting_imports` | Tabel baru `setting_imports`: log siapa/kapan import setting |
| `2026_10_21_000002_add_customer_segment` | Kolom `segment` (boleh kosong) di `prize_catalog` dan `game_sessions` |
| `2026_10_21_000003_add_prize_quota_name` | Kolom `quota_name` (boleh kosong) di `prize_catalog` |
| `2026_10_22_000001_add_prize_image` | Kolom `image` (boleh kosong) di `prize_catalog` |

### 3.4 Storage, izin folder, dan cache

```bash
for app in amura reglow; do
  (cd apps/$app \
    && php artisan storage:link 2>/dev/null; \
    php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache)
done
```

- `storage:link` sudah ada dari fitur pop up. Kalau muncul pesan "already exists", itu normal.
- Pastikan user web server (mis. `www-data`) bisa menulis ke dua folder ini di tiap app:
  - `storage/app/private/setting-backups/` untuk backup otomatis sebelum import setting
  - `storage/app/public/hadiah/` untuk foto hadiah yang diunggah Game Admin

  ```bash
  for app in amura reglow; do
    sudo -u www-data mkdir -p apps/$app/storage/app/private/setting-backups apps/$app/storage/app/public/hadiah
  done
  ```

### 3.5 Muat ulang PHP

```bash
sudo systemctl reload php8.5-fpm      # sesuaikan nama service PHP-FPM di server
```

### 3.6 Batas upload (cek sekali)

- Foto hadiah maksimal 2 MB, file setting maksimal 512 KB.
- `upload_max_filesize` dan `post_max_size` di `php.ini` minimal `4M`.
- Kalau memakai Nginx, `client_max_body_size` minimal `4m`.

---

## 4. Import setting hadiah (wajib, sekali per brand)

Setting hadiah, bobot, kuota, katalog tukar poin, dan periode program untuk 27 Okt - 31 Des 2026 dibawa
lewat file JSON. Import lewat panel admin, bukan lewat database.

Ulangi untuk Amura (`rewards.amura.id`) dan Reglow (`rewards.reglow.id`):

1. Login sebagai **Super Admin**, buka `/admin/setting`.
2. Klik **Export** dulu dan simpan file hasilnya sebagai cadangan setting lama.
3. Di bagian Import, pilih file yang sesuai brand:
   - Amura: `docs/deploy/2026-10-22/setting-amura.json`
   - Reglow: `docs/deploy/2026-10-22/setting-reglow.json`

   File untuk brand lain akan ditolak.
4. Baca **pratinjau perubahan**, lalu klik **Terapkan**.
5. Pastikan muncul pesan berhasil. App juga menyimpan setting sebelum import di daftar backup di halaman yang sama.

Isi file setelah di-import, per brand:

| Bagian | Isi |
|---|---|
| Periode program (Kendali Gatcha) | Order 27 Okt 2026 - 31 Des 2026, peluang hadiah utama 20% per papan |
| Poin | Minimal tukar 800 poin, berlaku 12 bulan |
| Hadiah aktif | 18: poin, 2 voucher ongkir, 2 voucher diskon produk, 5 produk, totebag (Amura) / pouch (Reglow), 5 jenis Philips, Emas Antam 1 gram |
| Stok bulanan | Voucher dan merch dibatasi per bulan; produk 50 per jenis per bulan. Stok habis = kotak berisi poin |
| Kuota hadiah utama | Philips: 2 unit untuk 1 Nov - 31 Des (1 per bulan, jenis diacak). Emas Antam: 1 unit, mulai keluar 25 Des |
| Pelanggan baru vs lama | Bobot dan isi kado berbeda. Aturan ini internal, tidak tampil ke pelanggan |
| Katalog tukar poin | Okt, Nov, Des 2026, termasuk Philips Hairdryer, Philips Air Fryer, Emas Antam |

**Perhatian:**
- Import menonaktifkan hadiah lama yang tidak ada di file. Hadiah lama tidak dihapus, jadi klaim lama tetap aman.
  Pemakaian kuota dan stok yang sudah berjalan juga tidak direset.
- **Kalau deploy dilakukan setelah 31 Oktober 2026**, katalog Oktober di file akan ditolak ("sudah lewat").
  Hapus blok katalog `2026-10` dulu:
  ```bash
  for b in amura reglow; do
    jq '.reward_catalogs |= map(select(.period != "2026-10"))' docs/deploy/2026-10-22/setting-$b.json > /tmp/setting-$b.json
  done
  ```
  lalu import file dari `/tmp`.
- Foto hadiah yang diunggah tidak ikut file setting. Foto bawaan untuk produk sudah ada di kode
  (`public/img/hadiah/`), jadi tidak ada yang perlu diunggah untuk rilis ini.

---

## 5. Cek setelah deploy

Lakukan di kedua domain. Untuk alur main, pakai satu nomor resi yang paketnya sudah diterima (atau minta CS).

| # | Cek | Hasil yang benar |
|---|---|---|
| 1 | `php artisan migrate:status` di kedua app | Tidak ada yang Pending |
| 2 | Buka `/cara-main` di HP | Logo brand resmi tampil, ada daftar "Yang bisa keluar dari kado" (5 jenis Philips + Emas di akhir) dan "Katalog tukar poin" |
| 3 | Buka `/play` | Logo brand resmi di atas form resi |
| 4 | Login admin, buka `/admin/game` | Ada dua form bobot (Pelanggan baru, Pelanggan lama), lima jenis Philips berlabel "Kuota bersama: Philips", isian "Untuk" dan "Foto story" per hadiah |
| 5 | Buka `/admin/setting` (Super Admin) | Tombol Export berfungsi, ada daftar backup dari import tadi |
| 6 | Buka `/admin/daftar-harian` (CS / Super Admin) | Tiga daftar tampil, unduh CSV berfungsi |
| 7 | Main satu resi sampai ambil hadiah | Di bawah kartu hadiah ada tombol **Bagikan ke Story** |
| 8 | Tekan Bagikan ke Story di HP | Menu share HP terbuka (Instagram, WhatsApp, dll). Gambar berisi logo, nama hadiah, dan `rewards.<brand>.id/cara-main` |
| 9 | Di browser yang tidak bisa share gambar (mis. Firefox desktop, atau alamat `http://`) tekan tombol yang sama | Muncul jendela "Bagikan hadiahmu" dengan Simpan gambar dan Kirim ke WhatsApp |
| 10 | `tail -n 50 storage/logs/laravel.log` di kedua app | Tidak ada error baru |

Catatan cek 8: menu share HP hanya muncul di halaman HTTPS (domain produksi sudah HTTPS).

---

## 6. Kalau ada masalah (rollback)

Migration rilis ini hanya menambah tabel dan kolom yang boleh kosong, jadi kode lama tetap bisa jalan di atas
database yang sudah dimigrasi. **Database tidak perlu di-rollback.**

Kembalikan kode:

```bash
cd /path/to/LoyaltyNew
git checkout a7b9580          # atau commit yang tercatat di langkah 2.1
for app in amura reglow; do
  (cd apps/$app && composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache)
done
sudo systemctl reload php8.5-fpm
```

Kembalikan setting hadiah: di `/admin/setting` (kode rilis ini), import file Export yang disimpan di langkah 4.2,
atau unduh backup otomatis dari daftar di halaman itu lalu import.

Restore database penuh (`pg_restore`) hanya kalau data rusak. Kabari Fai sebelum melakukannya,
karena klaim dan poin pelanggan sesudah backup akan hilang.

---

## 7. Detail teknis per fitur

### Landing page `/cara-main` (PR #5, #8, #10)
- Rute publik `GET /cara-main` (`GuideController`). Angka, periode, hadiah, dan katalog dibaca dari setting,
  jadi tidak perlu diedit saat Game Admin mengubah setting.
- Gambar langkah main di `public/img/cara-main/<brand>/`, logo di `public/img/brand/`.

### Backup setting dan Daftar harian BA (PR #6)
- `/admin/setting` (Super Admin): export/import JSON berformat `loyalty-setting` versi 1.
  - Import selalu lewat pratinjau.
  - Setting sebelum import disimpan di `storage/app/private/setting-backups/<brand>/`.
  - Setiap import dicatat di tabel `setting_imports`.
- `/admin/daftar-harian` (CS, Super Admin): CSV belum main, hadiah hampir hangus (4 hari), saldo poin cukup.

### Pelanggan baru vs lama (PR #7, #9)
- Pelanggan baru = nomor HP yang belum pernah punya sesi Gatcha di brand itu. Dicatat di `game_sessions.segment` saat sesi dibuat.
- Bobot tier per jenis pelanggan disimpan di tabel `settings`:
  - pelanggan lama di `tier_config:<brand>`
  - pelanggan baru di `tier_config:<brand>:baru`
- `prize_catalog.segment` = untuk siapa hadiah itu (`semua`, `baru`, `lama`). Kosong = bawaan: hadiah utama untuk lama, lainnya untuk semua.

### Kuota bersama (PR #10)
- `prize_catalog.quota_name` menunjuk baris `quota_caps` yang dipakai bersama. Lima jenis Philips memakai kuota "Philips".
- Potong kuota tetap atomik di satu baris, jadi dua pelanggan tidak bisa sama-sama mengambil unit terakhir.

### Bagikan ke Story (PR #11)
- `public/js/share-story.js` menggambar story 1080x1920 di HP pelanggan (canvas).
  - Gambar dibagikan lewat Web Share API (`navigator.share` dengan file PNG).
  - Tidak ada beban server dan tidak ada layanan pihak ketiga.
- Gambar tidak memuat nama, nomor HP, atau kode klaim. Link memakai `/cara-main?ref=story`.
- Foto hadiah:
  - Unggahan Game Admin di Panel Game disimpan di `storage/app/public/hadiah/<brand>/` (kolom `prize_catalog.image`).
  - Kalau belum ada unggahan, dipakai foto bawaan `public/img/hadiah/<brand>/<slug-nama>.webp`.
  - Tanpa foto, gambar memakai ilustrasi kado.

### Test
Status di commit rilis: core 45, Amura 355, Reglow 355, semua lulus. Untuk menjalankan ulang (pakai DB test, bukan produksi):

```bash
(cd packages/core && vendor/bin/phpunit)
(cd apps/amura && php artisan test)
(cd apps/reglow && php artisan test)
```

---

## 8. Kontak

- Isi program, hadiah, dan persetujuan: Fai (CRM).
- Kode: repo `mnfaiwork-ops/loyalty-new`, PR #5 sampai #11. Ringkasan arsitektur di `docs/HANDOVER.md`.
