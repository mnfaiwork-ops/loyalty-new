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
];
