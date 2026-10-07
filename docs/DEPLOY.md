# Deploy Loyalty New — Langkah Ops (B1/B2/B3)

Panduan operasional untuk hal-hal yang TIDAK bisa dilakukan dari kode: pisah DB,
aktifkan gate Soscom, dan jalankan scheduler. Sesuai catatan
`05 Handoff Prompts/loyalty/2026-09-25-loyalty-new-belum-dikerjakan.md` §B.

---

## B1 — Pisahkan 4 database brand (butuh sudo / DBA)

Fase 1 kedua app berbagi `loyalty_new`. **Produksi WAJIB pisah.**

1. Buat 4 DB (idempotent, butuh sudo):
   ```bash
   sudo bash scripts/provision-brand-dbs.sh
   ```
   Membuat: `loyalty_new_amura`, `loyalty_new_reglow`,
   `loyalty_new_amura_test`, `loyalty_new_reglow_test` (owner `loyalty_new`).

2. Arahkan tiap app ke DB-nya:
   ```bash
   bash scripts/use-brand-db.sh amura     # .env DB_DATABASE + phpunit.xml
   bash scripts/use-brand-db.sh reglow
   ```

3. Migrasi + seed tiap app:
   ```bash
   (cd apps/amura  && php artisan migrate --force && php artisan db:seed --force)
   (cd apps/reglow && php artisan migrate --force && php artisan db:seed --force)
   ```

4. Verifikasi suite per app (berurutan):
   ```bash
   (cd apps/amura  && php artisan test)
   (cd apps/reglow && php artisan test)
   ```

> Setelah pisah DB, isolasi brand jadi dua lapis: DB terpisah + `BrandScope`.

---

## B2 — Aktifkan gate Soscom (butuh base URL + API key per brand dari tim WMS)

Sumber status order produksi = **Soscom Open API** dari WMS. Dokumen dan koleksi
Postman: `docs/api/`. Kode: `packages/core/src/Gate/SoscomOrderStatusSource.php`.

Di `.env` produksi tiap app (key **Amura dan Reglow beda**, diambil di WMS:
Lainnya → Client → Aksi → API Key):
```env
MARKOM_GATE=markom
MARKOM_DRIVER=soscom_api
SOSCOM_API_URL=https://wms-api.sinergisuperapp.com   # produksi; WMS tidak punya staging
SOSCOM_API_KEY=...                # key brand app ini
SOSCOM_API_TIMEOUT=5
```

Kontrak:
```
GET {SOSCOM_API_URL}/v1/open/social-commerce/orders/detail?phone={hp}&awb={awb}
X-Api-Key: {key}
200 → {"code":200,"msg":{...},"data":{"status":"completed","awb":"...","customer":{"name":"..."},"customer_shipping":{"phone":"628..."},"orders":{"status":"..."}}}
404 → HP + AWB tidak cocok / order tidak ada · 401/403 → key kosong/salah (dicatat di log error)
```

**Dikonfirmasi WMS (2026-10-06):**
- `status` = status order soscom: `pending`, `process`, `sent`, `completed` (paket diterima), `returned`, `cancelled`.
- `orders.status` = status fulfillment gudang: `pending`, `paid`, `process`, `pick`, `packing`, `packed`,
  `ready_to_pickup`, `sent`, `completed`, `request_return`, `returned`, `request_cancel`, `cancelled`.
- Gagal antar / retur = `request_return`, `returned`. `completed_at` sudah ditambahkan.
- Tidak ada staging (uji langsung ke produksi dengan AWB sampel). Belum ada batas request per key.

Gate lolos kalau `orders.status='completed'` atau `status='completed'`, dan ditolak kalau salah
satunya `request_return`/`returned`/`request_cancel`/`cancelled`. Path field bisa diganti lewat
`.env` tanpa ubah kode:
```env
SOSCOM_FIELD_STATUS_ORDER=status
SOSCOM_FIELD_STATUS_FULFILLMENT=orders.status
SOSCOM_FIELD_PHONE=customer_shipping.phone
SOSCOM_FIELD_CUSTOMER_NAME=customer.name
```
Isi kosong = field tidak dibaca. Kelulusan diatur `OrderStatus::isDelivered()` + AWB cocok HP.

`soscom_api` juga jadi driver default kalau `MARKOM_DRIVER` kosong. Driver lama
`markom_api` (kontrak tebakan `GET /orders/{awb}` + Bearer, di
`MarkomHttpOrderStatusSource.php`) hanya dipakai kalau diisi eksplisit; endpoint-nya
tidak ada di `markom.sinergisuperapp.com` (404, dicek 2026-10-06).

**Uji cepat sebelum produksi:** set `MARKOM_DRIVER=fairtrack_sqlite` +
`FAIRTRACK_DB=/path/fairtrack-lead.db` untuk membuktikan gate jalan terhadap data
nyata tanpa API.

---

## B3 — Jalankan scheduler (auto-hangus klaim)

`loyalty:expire-claims` dijadwalkan harian 01:00 via `routes/console.php`.
Tambahkan cron di server (satu per app):

```cron
* * * * * cd /path/to/apps/amura  && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path/to/apps/reglow && php artisan schedule:run >> /dev/null 2>&1
```

Cek manual:
```bash
php artisan loyalty:expire-claims --dry-run   # lihat berapa yang akan di-hangus
php artisan loyalty:expire-claims             # eksekusi
php artisan schedule:list                     # pastikan terjadwal
```

---

## Urutan aman produksi

1. B1 (buat + arahkan DB + migrasi/seed).
2. Set kredensial produksi: `APP_BRAND`, `ADMIN_PASSWORD` (jangan default), `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_KEY` (`php artisan key:generate`).
3. B2 (Soscom).
4. B3 (cron scheduler).
5. Verifikasi: `php artisan loyalty:expire-claims --dry-run` dan 1 order nyata di `/play`.

## Poin, katalog, dan redeem (2026-09-29)

- Isi `CS_WHATSAPP` di `.env` tiap app (nomor resmi CS brand itu, format 08.. atau 62..). Kosong: tombol WhatsApp di voucher disembunyikan.
- Scheduler wajib jalan (`php artisan schedule:run` tiap menit via cron). Job harian: `loyalty:expire-claims` (01:00) dan `loyalty:expire-points` (01:05).
- Game Admin menerbitkan katalog poin bulan pertama di `/admin/catalog` sebelum rilis. Kalau katalog bulan baru belum terbit, katalog bulan lalu tetap tampil.
- Nama pemenang di voucher diambil dari `customer.name` respons Soscom. Kalau kosong, voucher menampilkan "Nama belum tersedia".
- Setelah mengubah kode bersama di Amura, jalankan `bash scripts/sync-reglow.sh` lalu test ketiga suite.

## PIN pelanggan, papan per kesempatan, dan halaman CS Pelanggan & PIN (2026-10-07)

- Migration baru (jalankan di kedua app sebelum kode dipasang; idempoten, aman di DB bersama):
  `2026_10_07_000001_add_customer_pin_and_rounds` (kolom PIN di `customers`, kolom `round` di `game_prizes`)
  dan `2026_10_07_000002_create_customer_pin_resets` (log setiap reset PIN oleh CS).
  ```bash
  (cd apps/amura  && php artisan migrate --force)
  (cd apps/reglow && php artisan migrate --force)
  php artisan migrate:status | grep Pending   # harus kosong
  ```
- `CS_WHATSAPP` juga dipakai tautan "Lupa PIN? Chat CS". Kosong: tautan diganti teks biasa.
- `ADMIN_URL_AMURA` dan `ADMIN_URL_REGLOW` (alamat dasar app, tanpa `/admin`), isi sama di kedua app,
  untuk tab Amura / Reglow di `/admin/customers`. Kosong: tab brand lain tidak muncul.
- Salah PIN 5 kali per nomor HP mengunci masuk 15 menit (ikut `loyalty.lockout.*`). Reset PIN oleh CS
  membuka kunci itu.
- Reglow memakai kode yang sama dengan Amura: setelah mengubah Amura, jalankan
  `bash scripts/sync-reglow.sh` lalu test ketiga suite.
