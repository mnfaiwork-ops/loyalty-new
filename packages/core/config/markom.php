<?php

return [
    /*
     | Sumber status "paket diterima".
     |
     | - 'soscom_api'       : Soscom Open API dari WMS (docs/api/soscom-open-api.md).
     | - 'markom_api'       : kontrak Markom tebakan lama (sebelum API Soscom ada).
     | - 'fairtrack_sqlite' : baca langsung DB FairTrack Lead (read-only).
     |                        DIPAKAI untuk smoke test / fase transisi.
     |
     | Penentu kelulusan SELALU status_fulfillment='complete' (spec §3),
     | bukan status_order. Padanan aman: status_order='completed'.
     */
    'driver' => env('MARKOM_DRIVER', 'soscom_api'),

    /*
     | Implementasi gate yang dipakai:
     | - 'stub'   : StubOrderGate (fase 1 / dev, selalu lolos selama AWB+HP terisi)
     | - 'markom' : MarkomApiOrderGate (cek order nyata + cocokkan AWB dengan HP)
     */
    'gate' => env('MARKOM_GATE', 'stub'),

    'api' => [
        'base_url' => env('MARKOM_API_URL', 'https://markom.sinergisuperapp.com/api'),
        'token' => env('MARKOM_API_TOKEN'),
        'timeout' => (int) env('MARKOM_API_TIMEOUT', 5),
    ],

    /*
     | Soscom Open API. Key per brand (WMS: Lainnya → Client → Aksi → API Key),
     | jadi .env Amura dan Reglow memakai key masing-masing.
     |
     | `fields`: path dot-notation di dalam `data` respons detail. `status` =
     | status order soscom, `orders.status` = status fulfillment gudang
     | (dikonfirmasi WMS 2026-10-06). Isi kosong = field tidak dibaca.
     */
    'soscom' => [
        'base_url' => env('SOSCOM_API_URL', 'https://wms-api.sinergisuperapp.com'),
        'api_key' => env('SOSCOM_API_KEY'),
        'timeout' => (int) env('SOSCOM_API_TIMEOUT', 5),
        'fields' => [
            'status_order' => env('SOSCOM_FIELD_STATUS_ORDER', 'status'),
            'status_fulfillment' => env('SOSCOM_FIELD_STATUS_FULFILLMENT', 'orders.status'),
            'phone' => env('SOSCOM_FIELD_PHONE', 'customer_shipping.phone'),
            'customer_name' => env('SOSCOM_FIELD_CUSTOMER_NAME', 'customer.name'),
            'amount' => env('SOSCOM_FIELD_AMOUNT', 'amount'),
            'discount' => env('SOSCOM_FIELD_DISCOUNT', 'discount_amount'),
            'ordered_at' => env('SOSCOM_FIELD_ORDERED_AT', 'order_at'),
        ],
    ],

    /*
     | Cache status order saat main (detik). 0 = tanpa cache.
     | Webhook Markom (POST /webhooks/markom) mem-bust cache AWB terkait.
     */
    'cache_ttl' => (int) env('MARKOM_CACHE_TTL', 60),

    /*
     | Rahasia tanda tangan webhook Markom (header X-Markom-Signature:
     | HMAC-SHA256 body). Kosong = webhook ditolak (fail-closed).
     */
    'webhook_secret' => env('MARKOM_WEBHOOK_SECRET'),

    'fairtrack' => [
        // Path DB FairTrack Lead (SQLite). Kosongkan di produksi.
        'database' => env('FAIRTRACK_DB'),
        'orders_table' => 'orders',
    ],
];
