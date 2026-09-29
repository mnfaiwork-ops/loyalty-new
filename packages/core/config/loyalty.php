<?php

return [
    /*
     | Kunci akses percobaan masuk game (spec §7):
     | setelah N kali AWB+HP salah, kunci selama M menit.
     */
    'lockout' => [
        'max_fails' => (int) env('LOYALTY_MAX_FAILS', 5),
        'minutes' => (int) env('LOYALTY_LOCKOUT_MINUTES', 15),
    ],

    /*
     | Nomor WhatsApp resmi CS brand ini (spec poin-redeem §10). Kosong: tombol WA
     | disembunyikan dan voucher menampilkan "Nomor CS belum diatur".
     */
    'cs_whatsapp' => env('CS_WHATSAPP'),
];
