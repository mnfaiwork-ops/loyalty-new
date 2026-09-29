# Loyalty New: Saldo Poin, Hadiahku & Redeem via WhatsApp (Design Spec)

**Tanggal:** 2026-09-29
**Status:** draft, menunggu review owner (Fai)
**Lanjutan dari:** `docs/superpowers/specs/2026-09-25-loyalty-new-design.md`, PR #2 (UI Gatcha Amura)
**Bahan sumber:** kanvas `design/Loyalty Amura Reglow.dc.html` (layar 1b, 1f, 1m, 1o, 1s, 1t, 2d, 3c, 3d, 3e, 3g, 3i), workbook biaya `0922 September/03 Script/build_workbook.py` (keputusan 2026-09-24), sesi desain 2026-09-29

---

## 1. Tujuan

Sampai PR #2, pelanggan hanya bisa **main** Gatcha. Setelah itu tidak ada tempat untuk:
- melihat **saldo poin** dan menukarnya dengan hadiah,
- melihat **hadiah yang sudah didapat** dan statusnya,
- **me-redeem** hadiah lewat CS dengan cara yang bisa diverifikasi.

Spec ini menambah tiga hal: dompet poin, katalog tukar poin yang berganti tiap bulan, dan alur redeem via WhatsApp yang tersambung ke dashboard CS/Finance.

**Berhasil kalau:**
1. Pelanggan yang pernah main bisa kembali (tanpa isi resi lagi selama 30 hari) dan melihat saldo, hadiah, serta status tiap hadiah.
2. CS bisa memastikan hadiah hanya dikirim ke pemenang asli: nomor pengirim WA harus sama dengan nomor pemenang.
3. Tidak ada poin yang masuk dua kali, terpotong dua kali, atau hilang tanpa jejak.

---

## 2. Keputusan (sesi 2026-09-29)

| # | Topik | Keputusan |
|---|---|---|
| 1 | Sumber poin | **Hanya dari Gatcha** (kado tier poin). Tidak ada poin dari order. |
| 2 | Nilai poin | Dari workbook final: **1 kado poin = 200 poin**, **minimal tukar 500 poin**, poin **berlaku 12 bulan**. |
| 3 | Poin lama | Tidak ada. Ini program loyalty pertama, jadi tidak ada migrasi dari `loyaltypointamura`. |
| 4 | Kado poin | **Langsung masuk saldo**, tanpa kode klaim dan tanpa CS. |
| 5 | Hadiah tukar poin | Masuk ke daftar **belum di-redeem** yang sama dengan hadiah Gatcha, lalu lewat alur redeem yang sama. |
| 6 | Katalog | **Berganti tiap bulan**. Kalau katalog bulan baru belum terbit, **katalog lama tetap tampil**. |
| 7 | Redeem | Pelanggan menekan Redeem, klaim tersinkron ke CS, pelanggan mendapat link WA resmi. Voucher menampilkan **nama pelanggan + nomor pemenang**. CS mencocokkan nomor pengirim WA. Nomor beda: klaim ditolak dan kembali ke daftar belum di-redeem. |
| 8 | Nama pelanggan | Dari **data order Markom** (field `customer_name`). |
| 9 | Masuk | **Resi + HP** pertama kali, lalu perangkat **diingat 30 hari**. Tidak bisa masuk hanya dengan nomor HP. |
| 10 | Hadiah utama | Tetap lewat **persetujuan Finance**, setelah CS mencocokkan nomor. |
| 11 | Batas redeem | Hadiah biasa **14 hari**, hadiah utama **30 hari**, dihitung **sejak hadiah didapat**. Lewat batas: hangus. |
| 12 | Tukar poin hangus | Hadiah hasil tukar poin yang hangus **mengembalikan poinnya** ke lot asal, dengan masa berlaku aslinya. |
| 13 | Isi kado Voucher/Merch | Saat kado dibuka, server mengundi **satu item katalog Gatcha aktif dari tier itu** (acak rata). Nama item disimpan di klaim. |
| 14 | Ongkir hadiah | Ditanggung pemenang (workbook). Tidak dihitung sistem. |

---

## 3. Alur Status Klaim

```
menang Gatcha (voucher/merch/hadiah utama)  /  tukar poin di katalog
        │
        ▼
  belum_redeem ──(expires_at lewat)──► hangus   (sumber katalog: poin kembali, stok kembali)
        │ pelanggan tekan Redeem
        ▼
    diajukan ──(expires_at lewat)──► hangus
        │ CS cocokkan nomor pengirim WA
        ├── nomor beda ──► belum_redeem   (rejected_reason diisi)
        ▼
  hadiah biasa ──► diproses
  hadiah utama ──► perlu_finance ──(Finance setuju)──► diproses
        │
        ▼
    dikirim (CS isi resi hadiah) ──► selesai (CS tandai diterima)
```

| Dari | Ke | Oleh | Syarat |
|---|---|---|---|
| (baru) | `belum_redeem` | sistem | menang Gatcha non-poin, atau tukar poin berhasil |
| `belum_redeem` | `diajukan` | pelanggan | klaim milik pelanggan ini, `expires_at` belum lewat |
| `diajukan` | `diproses` | CS | nomor pengirim cocok, hadiah biasa |
| `diajukan` | `perlu_finance` | CS | nomor pengirim cocok, hadiah utama |
| `diajukan` | `belum_redeem` | CS | nomor pengirim tidak cocok |
| `perlu_finance` | `diproses` | Finance | `expires_at` belum lewat |
| `diproses` | `dikirim` | CS | resi hadiah diisi |
| `dikirim` | `selesai` | CS | ditandai diterima |
| `belum_redeem`, `diajukan`, `perlu_finance` | `hangus` | sistem | `expires_at` lewat (Finance tidak bisa menyetujui klaim lewat batas, jadi `perlu_finance` juga harus bisa hangus) |

- Status `selesai` dan `hangus` final. Klaim yang sudah `diproses` ke atas tidak bisa hangus.
- `expires_at` **tidak** diperpanjang saat klaim ditolak dan kembali ke `belum_redeem`.
- Setiap transisi ditulis ke `claim_events`. Tabel itu dipakai untuk audit sekaligus timeline di layar pelanggan.
- Status lama `menunggu` diganti `belum_redeem`. Migrasi mengubah baris yang ada.
- Hadiah utama tidak lagi langsung `perlu_finance` saat menang. Finance baru melihatnya setelah CS mencocokkan nomor.

---

## 4. Dompet Poin

**Model: lot + pergerakan.**

- Setiap kado poin yang diambil membuat satu **lot**: 200 poin, `expires_at` = +12 bulan. Unique per `game_prize_id`, jadi poin tidak bisa masuk dua kali.
- **Saldo** = jumlah `remaining` dari lot yang belum hangus.
- **Tukar**: syarat saldo ≥ **max(500, harga item)**. Lot dipotong mulai dari yang **paling cepat hangus** (FIFO menurut `expires_at`). Satu baris `point_movements` (`spend`, negatif) ditulis per lot yang dipotong, dengan `claim_id`.
- **Kembali** (klaim katalog hangus): setiap baris `spend` milik klaim itu dibalik ke lot yang sama (`refund`, positif). Lot yang sudah lewat masa berlakunya tetap dianggap hangus. Poin yang kembali ke lot kedaluwarsa langsung dicatat `expire`.
- **Hangus 12 bulan**: job harian mengosongkan `remaining` lot yang lewat `expires_at` dan menulis `expire`.
- Semua operasi dompet memakai transaksi DB dan mengunci lot milik pelanggan (`lockForUpdate`), sehingga dua tukar bersamaan tidak lolos bersama.

Kelas inti: `packages/core/src/Points/PointWallet.php` dengan `balance()`, `credit(GamePrize)`, `spend(Claim, int)`, `refund(Claim)`, dan `expireDue()`. Kelas ini tidak memakai facade Laravel di logika murninya, supaya bisa dites dengan PHPUnit biasa, sama seperti kelas core lain.

---

## 5. Katalog Bulanan (tukar poin)

- `reward_catalogs`: satu baris per brand per bulan (`period` = `YYYY-MM`), dengan `published_at`.
- **Katalog aktif** = katalog terbit dengan `period` ≤ bulan berjalan, yang terbaru. Kalau bulan baru belum terbit, katalog bulan lalu tetap aktif.
- Item berisi nama, harga poin, gambar opsional, stok opsional (kosong = tanpa batas), `stock_used`, dan status aktif.
- Tukar mengunci baris item, lalu mengecek stok, memotong poin, menaikkan `stock_used`, dan membuat klaim `belum_redeem` (sumber `katalog`, `expires_at` +14 hari). Semua dalam satu transaksi.
- Klaim katalog yang hangus menurunkan `stock_used` dan mengembalikan poin.
- Game Admin: pilih bulan, tambah/ubah item, **Salin dari bulan lalu**, **Terbitkan**. Katalog yang sudah terbit tetap bisa diubah (misalnya stok), tapi harga item yang sudah pernah ditukar tidak berubah untuk klaim lama, karena klaim menyimpan `points_spent` dan `prize_name` sendiri.

**Isi katalog bulan pertama** (dari workbook, hanya item jalur Poin; item jalur Gatcha saja seperti Philips dan Emas tidak ikut):

| Amura | Poin | Reglow | Poin |
|---|---|---|---|
| Pouch Exclusive | 500 | Pouch Exclusive | 500 |
| Voucher Diskon Ongkir 15rb | 600 | Voucher Diskon Ongkir 15rb | 600 |
| Exclusive ToteBag | 700 | Exclusive ToteBag | 700 |
| Voucher Free Ongkir | 1.000 | Voucher Free Ongkir | 1.000 |
| Voucher Diskon 25rb | 1.000 | Voucher Diskon 25rb | 1.000 |
| Voucher Diskon 50rb | 2.000 | Voucher Diskon 50rb | 2.000 |
| Darkspot Serum | 2.000 | Night Cream | 2.000 |
| Gold Serum | 2.500 | Sunscreen Glow | 2.500 |
| Voucher Diskon 100rb | 3.500 | Rice PDRN Serum (HPP belum diisi) | 3.000 |
| | | Voucher Diskon 100rb | 3.500 |

Seeder mengisi daftar ini sebagai katalog bulan berjalan untuk dev dan smoke test. Di produksi, Game Admin yang menerbitkan.

---

## 6. Isi Kado Gatcha Voucher/Merch

`SessionService::openRound()` sudah menangani kuota hadiah utama saat kado dibuka. Di titik yang sama, kado tier `voucher`/`merch` yang belum punya `prize_catalog_id` diberi satu item acak dari `prize_catalog` aktif brand itu dengan tier yang sama. Kalau tidak ada item aktif, klaim memakai label tier ("Voucher"/"Merch").

Isi kado tetap tidak dikirim ke browser sebelum dibuka (`test_board_tidak_bocorkan_tier` tetap berlaku). Respons `reveal`/`take` menyertakan `name` untuk semua tier non-poin.

---

## 7. Layar Pelanggan

Semua layar mobile-first 390 px, gaya sama dengan PR #2 (dasar gading, panggung gelap hanya untuk Gatcha).

| Route | Layar | Isi | Desain |
|---|---|---|---|
| `GET /play`, `POST /play/start` | Masuk | Resi + HP seperti sekarang. Kalau cocok: nama pelanggan diperbarui dari data order, cookie perangkat dipasang, lalu diarahkan ke papan Gatcha (kalau masih ada kesempatan) atau ke Beranda. | 2a, 2e |
| `GET /` | Beranda | "Halo, {nama}", kartu **Poin {brand}** + poin terdekat yang akan hangus, banner "N hadiah belum di-redeem · X hari lagi", 3 hadiah terbaru, tombol **Katalog** dan **Gatcha ({sisa})**, tautan "Cara main", "Bukan kamu? Keluar". Tanpa cookie: diarahkan ke `/play`. | 3c, 1b |
| `GET /hadiah?tab=` | Hadiahku | Tab *Belum di-redeem* · *Sedang diproses* (status `diajukan`, `perlu_finance`, `diproses`, `dikirim`) · *Selesai & hangus*. Tiap item: nama, sumber, kode, status, sisa hari. Kosong: pesan dan tombol ke Gatcha/Katalog. | 3c, 3g |
| `GET /hadiah/{code}` | Voucher | Kartu voucher: **nama pelanggan, nomor pemenang, hadiah, kode, batas redeem**. Status `belum_redeem`: tombol **Redeem**. Status `diajukan`: tombol **Buka WhatsApp CS** (pesan terisi) + cara cadangan (salin nomor, salin pesan). Timeline dari `claim_events`. Ditolak: catatan "Nomor pengirim tidak cocok. Kirim dari nomor pemenang." | 2d, 3d, 3e |
| `POST /hadiah/{code}/redeem` | | `belum_redeem` → `diajukan`, lalu kembali ke voucher dengan tombol WA. | |
| `GET /katalog` | Katalog | Katalog aktif, saldo, harga poin tiap item. Tombol **Tukar** mati kalau poin kurang (dengan pesan kurang berapa) atau stok habis. | 1f, 1m, 1o |
| `POST /katalog/{item}/tukar` | | Konfirmasi di halaman ("Poin dipotong X. Hadiah masuk Hadiahku, redeem sebelum {tanggal}."), lalu tukar dan arahkan ke voucher. | |
| `POST /keluar` | | Hapus cookie perangkat, kembali ke `/play`. | |

**Hasil Gatcha** (`board`): kado poin menampilkan "+200 poin masuk saldo" dengan animasi koin ke kapsul saldo (momen *Poin* di `gatcha-anim.jsx`). Kado lain menampilkan "Masuk Hadiahku, redeem sebelum {tanggal}" dengan tombol ke voucher.

**Pesan WA** (`https://wa.me/{nomor}?text=`):
```
Halo CS {brand}, saya mau redeem hadiah.
Hadiah: {prize_name}
Kode: {claim_code}
Nama: {nama}
Nomor pemenang: {nomor}
```

**Cookie perangkat:** `loyalty_customer`, dienkripsi Laravel, berisi `customer_id` + `brand`, berlaku 30 hari. Middleware `customer` menolak cookie dari brand lain. Setiap route klaim memastikan `claims.customer_id` = pelanggan di cookie. Kalau tidak cocok: 404.

---

## 8. Dashboard Internal

**CS: Klaim WhatsApp** (`/admin/claims`, desain 3i)
- Antrian `diajukan`, yang terlama di atas. Kolom: kode, nama, nomor pemenang, hadiah, sumber, waktu diajukan, batas.
- Kolom cari kode.
- Detail klaim: input **nomor pengirim WA**. Nomor dinormalkan dengan `AwbMatcher::normalizePhone`, lalu dibandingkan.
  - Cocok: **Konfirmasi** → `diproses` atau `perlu_finance`.
  - Beda: **Tolak: nomor tidak cocok** → `belum_redeem`, `rejected_reason` diisi.
  - Catatan opsional, masuk ke `claim_events`.
- Tombol Konfirmasi hanya aktif setelah nomor yang cocok dimasukkan. Server tetap mengecek ulang.

**CS: Pengiriman** (`/admin/shipping`)
- Daftar `diproses`: isi resi hadiah → `dikirim`.
- Daftar `dikirim`: **Tandai diterima** → `selesai`.

**Finance** (`/admin/finance`, desain 1t): antrian `perlu_finance` (setelah CS). **Setujui** → `diproses`. Aturan lama tetap berlaku: klaim lewat batas tidak bisa disetujui.

**Game Admin: Katalog bulanan** (`/admin/catalog`, desain 1s): pilih bulan, daftar item, tambah/ubah, salin dari bulan lalu, terbitkan. Pengaturan poin per kado, minimal tukar, dan masa berlaku poin disimpan di tabel `settings` yang sudah ada.

Peran mengikuti middleware `role` yang ada: CS (`cs`, `super_admin`), Finance (`finance`, `super_admin`), Game Admin (`game_admin`, `super_admin`).

---

## 9. Model Data

Semua tabel memiliki kolom `brand` (atau lewat relasi ke tabel ber-brand) dan dibatasi `BrandScope`.

**`customers`** (ada): `name` diisi dari data order setiap kali resi dicek.

**`claims`** (diubah)

| Kolom | Catatan |
|---|---|
| `brand`, `customer_id` | baru. Klaim katalog tidak punya sesi, jadi brand dan pelanggan disimpan langsung. Diisi ulang untuk baris lama dari relasi sesi. |
| `game_session_id`, `game_prize_id` | jadi nullable (klaim katalog) |
| `source` | baru: `gatcha` / `katalog` |
| `prize_name` | baru: nama hadiah saat didapat |
| `reward_catalog_item_id`, `points_spent` | baru, untuk klaim katalog |
| `status` | `belum_redeem`, `diajukan`, `diproses`, `perlu_finance`, `dikirim`, `selesai`, `hangus` |
| `redeem_requested_at`, `shipping_awb`, `rejected_reason` | baru |

**`claim_events`** (baru): `claim_id`, `from_status`, `to_status`, `actor` (`customer`/`cs`/`finance`/`system`), `user_id` nullable, `note`, `created_at`.

**`point_lots`** (baru): `brand`, `customer_id`, `game_prize_id` (unique, boleh kosong untuk saldo demo dari seeder), `points`, `remaining`, `expires_at`, timestamps.

**`point_movements`** (baru): `brand`, `customer_id`, `point_lot_id`, `claim_id` nullable, `type` (`earn`/`spend`/`refund`/`expire`), `points` (bertanda), `created_at`.

**`reward_catalogs`** (baru): `brand`, `period` (`YYYY-MM`, unique per brand), `published_at` nullable.

**`reward_catalog_items`** (baru): `reward_catalog_id`, `name`, `points_cost`, `image_path` nullable, `stock` nullable, `stock_used`, `active`, `sort`.

**Gate:** `OrderStatus` ditambah `customerName` (nullable). `FairtrackSqliteOrderStatusSource` membaca `customer_name`. Kontrak HTTP Markom ditambah field `customer_name` (opsional). `GateResult` membawa nama ke `SessionService::open()`.

---

## 10. Konfigurasi

| Kunci | Nilai awal | Tempat |
|---|---|---|
| Poin per kado | 200 | `settings` (Game Admin) |
| Minimal tukar | 500 | `settings` |
| Masa berlaku poin | 12 bulan | `settings` |
| Batas redeem biasa / utama | 14 / 30 hari | sudah ada (`ClaimService::expiryFor`) |
| Batas redeem hadiah katalog | 14 hari | `ClaimService` |
| Nomor WA CS | kosong | `.env` `CS_WHATSAPP` per app |
| Umur cookie perangkat | 30 hari | `config/play.php` |

Kalau `CS_WHATSAPP` kosong, tombol WA disembunyikan dan voucher menampilkan "Nomor CS belum diatur". Nomor ini wajib diisi sebelum rilis.

---

## 11. Error & Kondisi Batas

- **Tukar ganda / redeem ganda:** transaksi + `lockForUpdate` pada lot pelanggan, item katalog, dan klaim. Tombol di browser dimatikan saat dikirim, tapi server yang menjamin.
- **Poin kurang / stok habis saat menekan Tukar:** ditolak dengan pesan jelas. Saldo dan stok tidak berubah.
- **Klaim bukan milik pelanggan / beda brand:** 404.
- **Cookie kedaluwarsa atau pelanggan tidak ditemukan:** diarahkan ke `/play` dengan pesan "Masuk lagi dengan resi dan nomor HP".
- **Klaim lewat batas saat Redeem ditekan:** ditolak, status menjadi `hangus` di job berikutnya. Layar menampilkan status hangus (3g).
- **Job harian** (`routes/console.php`, 01:00):
  - `loyalty:expire-claims` (sudah ada) diperluas: `belum_redeem`/`diajukan` lewat batas menjadi `hangus`. Hadiah utama mengembalikan kuota. Klaim katalog mengembalikan poin dan stok.
  - `loyalty:expire-points` (baru): lot lewat 12 bulan dikosongkan.
- **Gate tanpa nama** (mode stub, atau Markom belum kirim `customer_name`): voucher menampilkan "Nama belum tersedia". CS mencocokkan dengan nomor saja.

---

## 12. Testing

- **Feature (Amura, butuh DB):** `PointWallet` untuk FIFO per `expires_at`, minimal tukar, saldo tidak negatif, refund ke lot asal, refund ke lot kedaluwarsa, hangus 12 bulan, dan kredit ganda ditolak. Pengundian item voucher/merch: hanya item aktif, tier sama, dan brand sama.
- **Feature (Amura):** setiap baris tabel transisi di §3, termasuk tolak lalu kembali tanpa perpanjangan batas, redeem ganda, tukar ganda, stok habis, isolasi brand, cookie brand lain, dan klaim milik pelanggan lain. Juga katalog aktif saat bulan baru belum terbit, serta job expire (klaim katalog mengembalikan poin dan stok).
- **Regresi:** 151 test yang ada tetap lulus. Test yang menegaskan kado poin membuat klaim diubah ke aturan baru.
- **Uji jalan Playwright 390×844:** masuk, Gatcha (poin masuk saldo), Beranda, Katalog → tukar, Hadiahku → voucher → Redeem → link WA, CS tolak lalu konfirmasi, Finance setujui, pengiriman, dan selesai. Ditambah keyboard, 360 px, dan console tanpa error.

---

## 13. Cakupan

**Termasuk:** semua di atas untuk app **Amura**. Logika ada di `packages/core`, sehingga Reglow cukup menyalin view, route, dan controller setelah Amura disetujui.

**Tidak termasuk:**
- Poin dari order, OTP, notifikasi WA otomatis dari sistem.
- Layar admin 3j (keamanan akses) dan 3k (Super Admin).
- Port UI Reglow (tahap berikutnya).
- Riwayat poin terperinci untuk pelanggan (data tersedia di `point_movements`, layar menyusul kalau dibutuhkan).

---

## 14. Yang Dibutuhkan dari Owner

1. **Nomor WhatsApp resmi CS** Amura dan Reglow.
2. **HPP Rice PDRN Serum (Reglow)**, catatan dari workbook, sebelum katalog Reglow terbit.
3. **Kontrak Markom:** minta tim Markom menambah `customer_name` di respons order.
