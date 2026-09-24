# Loyalty New — Amura & Reglow

Program loyalty berbasis game Gatcha untuk dua brand (Amura & Reglow), terpisah total per brand.
Repo ini adalah **project Laravel 12** (bukan app lama `loyaltypointamura`).

## Struktur

- `app/` — aplikasi Laravel 12.69.2 (PHP 8.5, PostgreSQL 18.6)
- `design/` — mock desain dari Claude Design (sumber kebenaran visual). Jangan diubah.
- `.db-credentials` — kredensial Postgres. **Jangan pernah di-commit** (sudah di `.gitignore`).

## Stack

- PHP 8.5.4 CLI · Laravel 12.69.2 · Composer 2.9.8
- PostgreSQL 18.6 — DB dev `loyalty_new`, test `loyalty_new_test`
- Blade + (Vite/Tailwind sesuai laravel default)

> ⚠️ Jangan pakai app lama di `~/Documents/Claude Code/Amura Loyalty Point/loyaltypointamura`
> sebagai basis kode, hanya sebagai **acuan pola** (model Customer/PointTransaction, pessimistic lock,
> unique index anti-poin-ganda, rate limit).

## Perintah

```bash
cd app
php artisan serve     # dev server
php artisan test      # pakai loyalty_new_test
php artisan migrate   # ke loyalty_new
```

## Keputusan yang sudah dikunci

Lihat handoff di Obsidian:
`05 Handoff Prompts/loyalty/2026-09-24-loyalty-new-design-handoff.md` +
`05 Handoff Prompts/loyalty/2026-09-25-loyalty-new-scaffold-progress.md`

Ringkas:
- **24 kado** per chance, pilih 3, ambil 1
- Klaim hadiah via **WhatsApp CS**
- Brand **terpisah total**
- Main berlaku **30 hari** sejak paket diterima; klaim hadiah utama **30 hari**, hadiah biasa **14 hari**
- Gate "paket diterima" **baca DUA field**: `status_order` diterima **DAN** `status_fulfillment`
  ∉ {problem, request_return, returned}
- Hadiah **diundi di server** saat sesi dibuat, tidak pernah dikirim ke browser sebelum kado dibuka
- Bahasa antarmuka: **Indonesia**. Mobile first (390 px).

<!-- antislop:start -->
## antislop

Untuk pekerjaan UI, copy, aksesibilitas (people), mobile layout, atau komentar kode, baca core
`antislop` lalu skill yang sesuai:

- UI / visual: `antislop-ui`
- Copy & text: `antislop-copywriting`
- People / aksesibilitas: `antislop-human`
- Mobile / responsive: `antislop-layoutmobile`
- Komentar kode: `antislop-code`

Sebelum mulai, tanyakan ke user kapan antislop berlaku: selama pengerjaan, atau setelah selesai.
Antislop berlaku **selama pengerjaan** (Mode DURING) di project ini.
<!-- antislop:end -->
