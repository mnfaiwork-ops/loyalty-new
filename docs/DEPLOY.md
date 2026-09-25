# Deploy Loyalty New — Langkah Ops (B1/B2/B3)

Panduan operasional untuk hal-hal yang TIDAK bisa dilakukan dari kode: pisah DB,
aktifkan gate Markom, dan jalankan scheduler. Sesuai catatan
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

## B2 — Aktifkan gate Markom (butuh endpoint + token dari tim Markom)

Gate sudah nyata & teruji. Yang kurang cuma endpoint resmi.

Di `.env` produksi tiap app:
```env
MARKOM_GATE=markom
MARKOM_DRIVER=markom_api
MARKOM_API_URL=https://markom.sinergisuperapp.com/api   # sesuaikan
MARKOM_API_TOKEN=...                                     # dari tim Markom
MARKOM_API_TIMEOUT=5
```

**Kontrak yang diasumsikan** (kalau beda, ubah mapping di
`packages/core/src/Gate/MarkomHttpOrderStatusSource.php`):
```
GET {MARKOM_API_URL}/orders/{awb}
Authorization: Bearer {token}
200 → {"status_order":"...","status_fulfillment":"...","customer_phone":"..."}
404 → order tidak ada
```
Kelulusan: `status_fulfillment='complete'` + AWB cocok HP.

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
3. B2 (Markom).
4. B3 (cron scheduler).
5. Verifikasi: `php artisan loyalty:expire-claims --dry-run` dan 1 order nyata di `/play`.
