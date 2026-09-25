#!/usr/bin/env bash
#
# B1 — Buat 4 database brand Loyalty New + migrasi + seed.
#
# Jalankan dengan sudo (butuh akses postgres superuser):
#   sudo bash scripts/provision-brand-dbs.sh
#
# Idempotent: aman dijalankan berulang.
#
# Setelah ini, arahkan tiap app ke DB-nya lewat scripts/use-brand-db.sh.
set -euo pipefail

ROLE="${LOYALTY_DB_ROLE:-loyalty_new}"
HOST="${LOYALTY_DB_HOST:-127.0.0.1}"
PORT="${LOYALTY_DB_PORT:-5432}"
DBS=(loyalty_new_amura loyalty_new_reglow loyalty_new_amura_test loyalty_new_reglow_test)

echo "==> Pastikan role '$ROLE' ada (idempotent)"
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '$ROLE') THEN
      RAISE EXCEPTION 'Role $ROLE belum ada. Buat dulu: CREATE ROLE $ROLE LOGIN PASSWORD ''...'';';
   END IF;
END
\$\$;
SQL

echo "==> Buat database (idempotent)"
for db in "${DBS[@]}"; do
  exists=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$db'")
  if [ "$exists" = "1" ]; then
    echo "   $db sudah ada — dilewati"
  else
    echo "   CREATE DATABASE $db OWNER $ROLE"
    sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE DATABASE $db OWNER $ROLE;"
  fi
done

echo
echo "==> Daftar DB loyalty:"
sudo -u postgres psql -lqt | cut -d'|' -f1 | grep -i loyalty | sed 's/^/   /'

echo
echo "Selesai. Langkah berikut:"
echo "   scripts/use-brand-db.sh amura    # arahkan app amura ke loyalty_new_amura"
echo "   scripts/use-brand-db.sh reglow   # arahkan app reglow ke loyalty_new_reglow"
echo "   lalu: (cd apps/amura && php artisan migrate --force && php artisan db:seed --force)"
