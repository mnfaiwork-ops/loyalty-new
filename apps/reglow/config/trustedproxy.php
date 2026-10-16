<?php

return [
    /*
     | IP reverse proxy (nginx, Cloudflare, load balancer) di depan app, dipisah koma.
     | Tanpa ini, di belakang proxy semua pengunjung terlihat satu IP: batas per IP
     | (/play/start, /masuk, /login) dan kunci PIN per jaringan berlaku untuk semua
     | orang sekaligus. Kosong = tidak ada proxy yang dipercaya (app diakses langsung).
     | Jangan isi '*' kalau app juga bisa diakses tanpa lewat proxy: header
     | X-Forwarded-For bisa dipalsukan untuk melewati batas per IP.
     */
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
