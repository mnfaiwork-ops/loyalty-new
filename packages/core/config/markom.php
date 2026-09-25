<?php

return [
    /*
     | Sumber status "paket diterima".
     |
     | - 'markom_api'       : panggil API Markom (produksi).
     | - 'fairtrack_sqlite' : baca langsung DB FairTrack Lead (read-only).
     |                        DIPAKAI untuk smoke test / fase transisi.
     |
     | Penentu kelulusan SELALU status_fulfillment='complete' (spec §3),
     | bukan status_order. Padanan aman: status_order='completed'.
     */
    'driver' => env('MARKOM_DRIVER', 'markom_api'),

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
