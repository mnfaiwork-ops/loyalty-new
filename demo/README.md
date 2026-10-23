# Server demo Loyalty New

Versi demo app Amura dan Reglow untuk tim Markom: merekam layar, membuat panduan, dan mencoba
alur pelanggan tanpa menyentuh produksi. Kodenya sama dengan produksi. Bedanya tiga hal:

- Cek resi tidak memanggil WMS. Resi dan nomor HP dibaca dari daftar dummy di folder ini
  (`orders-amura.csv`, `orders-reglow.csv`). Aturan cek tetap sama: resi dan HP harus cocok,
  paket harus sudah diterima, tanggal order harus masuk periode program. Pesan error yang muncul
  juga sama dengan produksi.
- Database sendiri di Render, kosong dari data pelanggan asli.
- Alamatnya `*.onrender.com`, tidak diindeks mesin pencari.

## Resi dan nomor HP dummy

Tiap brand punya 50 pasangan resi + HP, ditambah 3 resi untuk menunjukkan pesan gagal.

| Resi | HP | Isi |
|---|---|---|
| `DEMOAMR001`-`030` / `DEMORGL001`-`030` | satu nomor per resi | pelanggan baru, satu paket |
| `DEMOAMR031`-`050` / `DEMORGL031`-`050` | dua resi per nomor | pelanggan dengan dua paket |
| `DEMOAMR901` / `DEMORGL901` | | gagal: paket belum diterima |
| `DEMOAMR902` / `DEMORGL902` | | gagal: paket diretur |
| `DEMOAMR903` / `DEMORGL903` | | gagal: order sebelum program mulai |

Pasangan lengkapnya ada di CSV, kolom `catatan` menjelaskan tiap baris. Nomor HP Amura
`0899 9000 xxxx`, Reglow `0899 9100 xxxx`. Di form, HP boleh diketik `899...` (form sudah
menampilkan +62) atau `0899...`.

Yang perlu diingat saat merekam:

- Satu resi hanya bisa dimainkan sekali, sama seperti produksi. Setelah 50 resi terpakai,
  kosongkan data demo (lihat "Mengosongkan data demo").
- Hadiah utama hanya bisa keluar di pesanan kedua. Untuk resi `031`-`050`, mainkan dulu
  resi bernomor ganjil (pesanan ke-1), baru resi genap dengan HP yang sama (pesanan ke-2).
- Hadiah utama juga ikut kuota dan tanggal rilis dari setting produksi (Philips mulai
  1 Nov 2026, Emas mulai 25 Des 2026), dan peluangnya tidak 100%. Kalau perlu merekam
  hadiah utama sebelum tanggal itu, ubah tanggal rilis di Panel Game server demo. Perubahan di
  server demo tidak memengaruhi produksi.
- Nama, nominal order, dan tanggal order tiap resi sudah diisi, jadi poin dari nominal order
  dan nama pelanggan tampil seperti di produksi.

## Akun admin

- Amura: `admin@amura.local`, Reglow: `admin@reglow.local`, peran Super Admin.
- Password: Render Dashboard, buka service `loyalty-demo-amura` atau `loyalty-demo-reglow`,
  menu Environment, nilai `ADMIN_PASSWORD`.
- Akun CS, Game Admin, atau Finance untuk demo bisa dibuat dari `/admin/users`.

## Memasang di Render (sekali saja)

1. Pastikan file `render.yaml` dan folder `demo/` sudah ada di branch `master` di GitHub.
2. Masuk ke https://dashboard.render.com, hubungkan akun GitHub yang punya akses ke repo ini.
3. Pilih New, lalu Blueprint. Pilih repo ini. Render membaca `render.yaml` dan menampilkan
   satu database (`loyalty-demo-db`) dan dua web service (`loyalty-demo-amura`,
   `loyalty-demo-reglow`).
4. Isi `CS_WHATSAPP` untuk kedua service. Pakai nomor WhatsApp orang tim Markom, bukan nomor CS
   asli, supaya klaim hadiah dari rekaman tidak masuk ke CS.
5. Klik Apply. Build pertama sekitar 5-10 menit. Alamatnya muncul di halaman tiap service,
   biasanya `https://loyalty-demo-amura.onrender.com` dan `https://loyalty-demo-reglow.onrender.com`.
6. Cek: buka `/play`, masukkan `DEMOAMR001` dan `0899 9000 0001`. Harus lanjut ke halaman buat PIN.

Setiap kali `master` berubah, Render otomatis men-deploy ulang server demo, jadi isinya ikut
produksi.

## Mengosongkan data demo

Render Dashboard, menu Env Groups, buka `loyalty-demo`, ganti nilai `DEMO_RESET_KEY` dengan angka
lain (misalnya `1` jadi `2`), lalu simpan. Kedua service deploy ulang. Setelah selesai, semua
pelanggan, sesi main, klaim, dan akun staf tambahan di demo terhapus, dan setting hadiah
di-import ulang dari `setting-<brand>.json`. Restart biasa atau server yang bangun dari tidur
tidak menghapus data.

## Menyamakan setting hadiah dengan produksi

`setting-amura.json` dan `setting-reglow.json` di folder ini adalah file setting rilis
22 Okt 2026. Kalau setting produksi berubah, pilih salah satu:

- Cepat: export dari `/admin/setting` di produksi, lalu import file itu di `/admin/setting`
  server demo.
- Permanen: ganti file di folder ini dengan hasil export produksi, merge ke `master`, lalu
  kosongkan data demo seperti di atas.

Foto hadiah yang diunggah Game Admin tidak ikut file setting. Foto bawaan dari repo tetap tampil.

## Batasan paket gratis Render

- Service tidur setelah sekitar 15 menit tanpa pengunjung. Buka linknya sekali dan tunggu
  sekitar 1 menit sebelum merekam.
- Database gratis berlaku sekitar 30 hari. Setelah itu upgrade ke paket berbayar di halaman
  database, atau buat ulang lewat Blueprint (data demo hilang).
- Paket berbayar menghilangkan dua batasan ini. Ubah `plan` di `render.yaml` atau di dashboard.

## Untuk developer

- Driver gate `MARKOM_DRIVER=demo` (`packages/core/src/Gate/DemoOrderStatusSource.php`)
  dan perintah `php artisan loyalty:demo-setup` ditolak kalau `APP_ENV=production`.
  `loyalty:demo-setup` juga hanya jalan kalau `MARKOM_DRIVER=demo`, karena perintah ini bisa
  mengosongkan database.
- Kedua app berbagi satu database Postgres, dipisah per schema (`DB_SEARCH_PATH`, bawaan nama
  brand di container demo).
- Coba image di lokal: `docker build -f demo/Dockerfile -t loyalty-demo .` dari root repo, lalu
  jalankan dengan env yang sama seperti di `render.yaml` ditambah `DB_URL` ke Postgres lokal.
- Untuk menambah resi dummy, tambah baris di `orders-<brand>.csv` dengan kolom yang sama.
