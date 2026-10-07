<?php

// Warna per brand dari kanvas desain (Revisi 1, GT/TH) untuk halaman /play.
return [
    // Perangkat pelanggan diingat sekian hari setelah resi + HP cocok.
    'remember_days' => 30,

    // Panel admin tiap brand (app terpisah), untuk tab Amura / Reglow di halaman CS.
    // Kosong = tab brand lain tidak ditampilkan.
    'admin_urls' => [
        'amura' => env('ADMIN_URL_AMURA'),
        'reglow' => env('ADMIN_URL_REGLOW'),
    ],

    'themes' => [
        'amura' => [
            'name' => 'Amura', 'initial' => 'A', 'awb_example' => 'JX2409187734',
            'p' => '#1E6B63', 'd' => '#0F413C', 't' => '#DCEDE9',
            'b0' => '#4DB5A8', 'b1' => '#27897E', 'b2' => '#0E4A43',
            'st1' => '#11403A', 'st2' => '#061816', 'glow' => 'rgba(90,200,185,.65)',
        ],
        'reglow' => [
            'name' => 'Reglow', 'initial' => 'R', 'awb_example' => 'JX2409187734',
            'p' => '#A8461F', 'd' => '#6E2A12', 't' => '#F7E7DC',
            'b0' => '#E08450', 'b1' => '#C45A2C', 'b2' => '#7E3012',
            'st1' => '#4A2010', 'st2' => '#1E0C05', 'glow' => 'rgba(232,140,90,.7)',
        ],
    ],
];
