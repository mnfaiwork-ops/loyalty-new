<?php

return [
    /*
     | Kunci akses percobaan masuk (spec §7): setelah N kali resi+HP atau PIN
     | salah dari satu perangkat/jaringan, kunci M menit. Lintas jaringan, satu
     | nomor HP dikunci 24 jam setelah `daily_max_fails` gagal (CS bisa membuka).
     */
    'lockout' => [
        'max_fails' => (int) env('LOYALTY_MAX_FAILS', 5),
        'minutes' => (int) env('LOYALTY_LOCKOUT_MINUTES', 15),
        'daily_max_fails' => (int) env('LOYALTY_DAILY_MAX_FAILS', 20),
    ],

    /*
     | Nomor WhatsApp resmi CS brand ini (spec poin-redeem §10). Kosong: tombol WA
     | disembunyikan dan voucher menampilkan "Nomor CS belum diatur".
     */
    'cs_whatsapp' => env('CS_WHATSAPP'),

    /*
     | Server demo (loyalty:demo-setup). setting_file: export setting produksi
     | (demo/setting-<brand>.json). Ganti reset_key untuk mengosongkan data demo.
     */
    'demo' => [
        'setting_file' => env('DEMO_SETTING_FILE'),
        'reset_key' => env('DEMO_RESET_KEY', ''),
    ],
];
