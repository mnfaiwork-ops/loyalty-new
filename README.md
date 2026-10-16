# Loyalty New: Amura & Reglow

Program loyalty berbasis game Gatcha untuk Amura dan Reglow (Laravel 12, PHP 8.5, PostgreSQL 18).

- Panduan serah terima untuk tim IT: [`docs/HANDOVER.md`](docs/HANDOVER.md)
- Langkah deploy: [`docs/DEPLOY.md`](docs/DEPLOY.md)
- Aturan kerja untuk agent dan developer: [`AGENTS.md`](AGENTS.md)

| Folder | Isi |
|---|---|
| `packages/core` | logika bersama |
| `apps/amura` | app Amura (sumber utama) |
| `apps/reglow` | salinan Amura untuk Reglow, lewat `scripts/sync-reglow.sh` |
