# Patch PHP: PHP2Shell (CVE-2026-109618, CVE-2026-109619)

Untuk: tim IT yang mengelola server `rewards.amura.id` dan `rewards.reglow.id`.
Dari: Fai (CRM).

**Prioritas: segera, sebelum atau bersamaan dengan deploy rilis 22 Oktober** ([PANDUAN-DEPLOY.md](PANDUAN-DEPLOY.md)).

---

## 1. Ringkasan

| | |
|---|---|
| Masalah | Dua celah di PHP itu sendiri (bukan di kode Laravel/app). Penyerang tanpa login bisa menjalankan kode di server lewat request HTTP biasa. CVSS v4 9.2 |
| Diumumkan | 21 Oktober 2026 |
| Yang kena | Semua PHP yang melayani web: PHP-FPM, Apache mod_php, php-cgi, FrankenPHP, LiteSpeed. Konfigurasi bawaan ikut rentan |
| Server kita | Kedua app jalan di **PHP-FPM 8.5** (`php8.5-fpm`) |
| Versi aman | **8.5.12**, 8.4.27, 8.3.36, 8.2.35, atau paket distro yang sudah memuat patch kedua CVE (contoh Ubuntu 26.04: `8.5.4-0ubuntu1.5`, nomor PHP tetap 8.5.4) |
| Perubahan kode app | Tidak ada. Yang diupdate hanya paket PHP di server |
| Downtime | Hanya saat restart PHP-FPM (beberapa detik) |
| Perkiraan waktu | 15-20 menit |

Detail celah:
- **CVE-2026-109618**: memori rusak saat PHP kehabisan memori di tengah request. Bisa juga membocorkan isi memori server.
- **CVE-2026-109619**: use-after-free. Data dari request sebelumnya terbaca oleh request berikutnya di worker yang sama.

---

## 2. Cek server

Di server, di folder repo (setelah `git pull`, script ini ikut terbawa):

```bash
cd /path/to/LoyaltyNew
bash scripts/check-php2shell.sh
```

Script mencari semua `php-fpm*` dan `php-cgi*`, lalu membandingkan versinya dengan versi aman. Kalau versi
belum naik, script membaca changelog paket (dpkg/rpm), karena distro sering menambal tanpa mengubah nomor versi PHP.

| Hasil | Artinya |
|---|---|
| `Hasil: AMAN` (exit 0) | Tidak perlu apa-apa. Lanjut ke langkah 5 (cek tanda dibobol) |
| `Hasil: RENTAN` (exit 1) | Lanjut ke langkah 3 |
| `Tidak ada php-fpm / php-cgi` (exit 2) | PHP ada di lokasi lain atau di Docker. Jalankan dengan path binary: `bash scripts/check-php2shell.sh /path/ke/php-fpm` |

Contoh hasil rentan:

```
== /usr/sbin/php-fpm8.5 (PHP 8.5.4)
  paket: php8.5-fpm 8.5.4-0ubuntu1.3
  RENTAN: perlu PHP >= 8.5.12 atau paket distro dengan patch CVE-2026-109618 CVE-2026-109619
  ini: fatal_error_backtraces => On => On
  ini: session.upload_progress.enabled => On => On
```

---

## 3. Update PHP

Pilih sesuai sumber paket PHP di server. Cek sumbernya dengan:

```bash
apt-cache policy php8.5-fpm     # Debian/Ubuntu: lihat baris repo di "Version table"
```

### 3.1 Debian/Ubuntu (repo resmi atau PPA ondrej/sury)

```bash
sudo apt update
apt-cache policy php8.5-fpm                    # "Candidate" harus lebih baru dari "Installed"
sudo apt install --only-upgrade 'php8.5-*'
```

Kalau `Candidate` sama dengan `Installed`, paket patch belum terbit di repo itu. Lakukan langkah 4 (mitigasi
sementara) sekarang, lalu ulangi 3.1 setiap beberapa jam sampai paket baru muncul.

Pilihan lain: pindah ke PPA `ondrej/php` (Ubuntu) atau `packages.sury.org` (Debian) yang biasanya cepat mengikuti
rilis PHP. Ini mengganti sumber paket PHP di server produksi, jadi putuskan di tim IT.

### 3.2 RHEL/Rocky/Alma (Remi)

```bash
sudo dnf upgrade 'php*'
```

### 3.3 Docker

Ganti tag image ke versi PHP yang aman (mis. `php:8.5.12-fpm`), build ulang, deploy ulang container.

### 3.4 Restart PHP-FPM

Pakai **restart**, bukan reload, supaya semua worker lama berhenti dan binary baru dipakai:

```bash
sudo systemctl restart php8.5-fpm      # sesuaikan nama service PHP-FPM di server
systemctl status php8.5-fpm --no-pager | head -5
```

Lalu ulangi langkah 2. Hasil harus `AMAN`.

---

## 4. Mitigasi sementara (hanya kalau patch belum tersedia)

Pengaturan ini **hanya menutup sebagian** jalur serangan. Tetap update PHP begitu paket tersedia.
Kedua pengaturan tidak dipakai oleh app (Laravel memakai session sendiri), jadi aman dimatikan.

```bash
echo 'session.upload_progress.enabled = 0
fatal_error_backtraces = 0' | sudo tee /etc/php/8.5/fpm/conf.d/99-php2shell.ini
sudo systemctl restart php8.5-fpm
php-fpm8.5 -i | grep -E '^(session\.upload_progress\.enabled|fatal_error_backtraces) =>'
```

Hasil yang benar: kedua baris `=> Off => Off`. Script langkah 2 tetap menulis `RENTAN` sampai PHP diupdate.

Kalau domain ada di belakang Cloudflare, pastikan Cloudflare Managed Ruleset (WAF) aktif untuk kedua domain.

Setelah PHP diupdate, file `99-php2shell.ini` boleh dihapus.

---

## 5. Cek tanda dibobol

Ada laporan serangan yang sudah berjalan. Cek sekali setelah patch, di kedua app:

```bash
cd /path/to/LoyaltyNew
# File PHP baru/berubah di folder yang tidak seharusnya berisi PHP
find apps/*/public apps/*/storage -name '*.php*' -newermt '2026-10-20' -not -path '*/storage/framework/*' -ls
# File app yang berubah dari isi repo
git status --short
# Worker PHP-FPM yang crash (segfault) sejak 20 Okt
sudo journalctl -u php8.5-fpm --since 2026-10-20 | grep -iE 'SIGSEGV|signal 11|exited on signal'
```

| Cek | Hasil yang benar |
|---|---|
| `find` | Kosong |
| `git status --short` | Kosong (atau hanya file yang memang diketahui) |
| `journalctl` | Kosong |

Kalau salah satu tidak kosong: jangan hapus apa-apa, simpan hasilnya, dan kabari Fai. Kemungkinan perlu ganti
`APP_KEY`, kata sandi database, dan kata sandi admin.

---

## 6. Kalau ada masalah setelah update

Kalau app error setelah update PHP (`tail -n 50 apps/*/storage/logs/laravel.log`):

```bash
for app in amura reglow; do
  (cd apps/$app && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache)
done
sudo systemctl restart php8.5-fpm
```

Jangan turunkan versi PHP ke versi rentan. Kalau masih error, pasang mitigasi langkah 4 di versi yang aman dan
kabari Fai.

---

## 7. Lapor balik ke Fai

Kirim hasil ini:

```bash
bash scripts/check-php2shell.sh
php-fpm8.5 -v | head -1
```

## Sumber

- Tenable FAQ: https://www.tenable.com/blog/php2shell-cve-2026-109618-cve-2026-109619-frequently-asked-questions-about-critical-php-remote
- Sansec: https://sansec.io/research/php2shell
- Rapid7: https://www.rapid7.com/blog/post/etr-cve-2026-109618-and-cve-2026-109619-critical-unauthenticated-remote-code-execution-in-php/
- Help Net Security: https://www.helpnetsecurity.com/2026/10/21/php2shell-php-vulnerabilities-patched-cve-2026-109618-cve-2026-109619/
