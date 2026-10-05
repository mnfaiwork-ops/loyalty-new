# Soscom Open API

Base URL: `{{base_url}}/v1/open/social-commerce`

Auth: header `X-Api-Key: <api key client>` — ambil di WMS menu **Lainnya → Client → Aksi → API Key**. Data otomatis dibatasi ke client (brand) pemilik key. Tanpa key → `401`, key salah → `403`.

Format response:

```json
{ "code": 200, "msg": { "id": "Sukses", "en": "Success" }, "data": ... }
```

Error: `400` request tidak valid, `401` tanpa key, `403` key tidak valid, `404` data tidak ditemukan, `500` server error. Detail error di field `error`.

---

## 1. Get Order Detail by Phone + AWB

Untuk sistem loyalti Sinergi.

`GET /orders/detail?phone={phone}&awb={awb}`

| Param | Wajib | Keterangan |
|---|---|---|
| `phone` | ya | No. HP customer. Format `08xx`, `628xx`, `+628xx` diterima |
| `awb` | ya | Nomor resi |

Phone + AWB harus cocok di order yang sama milik client pemilik key. Tidak cocok → `404`.

Response `data` (ringkas):

```json
{
  "id": 129005,
  "reference_no": "SC-...",
  "order_at": "2026-10-04T10:00:00+07:00",
  "status": "completed",
  "payment_method": "COD",
  "payment_status": "paid",
  "is_cod": true,
  "qty": 2,
  "amount": 250000,
  "discount_amount": 0,
  "shipping_fee": 15000,
  "discount_shipping_fee": 0,
  "cod_fee": 7500,
  "other_amount": 0,
  "courier": "jne",
  "courier_label": "REG",
  "awb": "C1MACRQU",
  "customer_service": "CS A",
  "ads_platform_name": "Meta Ads",
  "warehouse_name": "Gudang Jakarta",
  "customer": { "id": 1, "name": "Budi", "type": "..." },
  "customer_shipping": { "name": "Budi", "phone": "628123...", "email": "", "address": "...", "location": { } },
  "products": [
    { "product": { "id": 10, "name": "...", "sku": "..." }, "qty": 2, "sell_price": 125000, "discount": 0, "amount": 250000, "het": 125000 }
  ],
  "orders": { "status": "completed", "awb": "C1MACRQU" }
}
```

Struktur sama dengan detail order soscom di WMS.

---

## 2. Get Orders by Date Range

Untuk tim CRM. Isi kolom = export xlsx Soscom di WMS.

`GET /orders/export?start_date={YYYY-MM-DD}&end_date={YYYY-MM-DD}`

| Param | Wajib | Keterangan |
|---|---|---|
| `start_date` | ya | `YYYY-MM-DD` |
| `end_date` | ya | `YYYY-MM-DD`, maks range 31 hari |
| `date_type` | tidak | `created_at` (default), `order_at`, `leads_at` |
| `status` | tidak | Status order, pisah koma |
| `status_payment` | tidak | Status pembayaran, pisah koma |
| `ads_platform_id` | tidak | Filter leads |
| `customer_service_id` | tidak | Filter CS |
| `warehouse_id` | tidak | Filter gudang |
| `customer` | tidak | Cari nama / no. HP (like) |
| `search` | tidak | Reference no / AWB (exact) |

Tanpa pagination — semua data dalam range dikembalikan, urut terbaru.

Response `data` (array):

```json
[
  {
    "order_id": 3578560,
    "reference_no": "SC-...",
    "order_at": "2026-10-04 10:00:00",
    "created": "2026-10-04 10:05:00",
    "completed_at": "",
    "leads": "Meta Ads",
    "customer_service": "CS A",
    "customer_name": "Budi",
    "customer_phone": "628123...",
    "customer_address": "Jl. ...",
    "customer_district": "DKI Jakarta,Jakarta Selatan,Kebayoran Baru",
    "product_summary": "...",
    "qty": 2,
    "product_price": 250000,
    "shipping_fee": 15000,
    "cod_fee": 7500,
    "discount": 0,
    "discount_shipping_fee": 0,
    "other_fee": 0,
    "total": 272500,
    "payment_method": "COD",
    "courier_type": "lincah",
    "courier": "jne",
    "courier_services": "REG",
    "warehouse": "Gudang Jakarta",
    "awb": "C1MACRQU",
    "status_order": "process",
    "status_fulfillment": "sent",
    "note": "",
    "reason_return": ""
  }
]
```

`total` = `product_price + shipping_fee + cod_fee - discount - discount_shipping_fee + other_fee`.
