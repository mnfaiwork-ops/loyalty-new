#!/usr/bin/env bash
#
# Arahkan satu app ke DB brand-nya (+ test DB) dengan mengedit .env.
#
#   scripts/use-brand-db.sh amura     # dev  -> loyalty_new_amura,  test -> loyalty_new_amura_test
#   scripts/use-brand-db.sh reglow    # dev  -> loyalty_new_reglow, test -> loyalty_new_reglow_test
#
# Idempotent. Tidak menyentuh kredensial lain.
set -euo pipefail

BRAND="${1:-}"
case "$BRAND" in
  amura|reglow) ;;
  *) echo "Pakai: $0 amura|reglow" >&2; exit 1 ;;
esac

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT/apps/$BRAND"
ENV_FILE="$APP_DIR/.env"

[ -f "$ENV_FILE" ] || { echo "Tidak ada $ENV_FILE" >&2; exit 1; }

set_env() {
  local key="$1" val="$2" file="$3"
  if grep -qE "^${key}=" "$file"; then
    # ganti nilai (BSD/GNU sed kompatibel via python)
    python3 - "$file" "$key" "$val" <<'PY'
import sys, re
path, key, val = sys.argv[1], sys.argv[2], sys.argv[3]
s = open(path).read()
s = re.sub(rf"^{re.escape(key)}=.*$", f"{key}={val}", s, flags=re.M)
open(path, "w").write(s)
PY
  else
    printf '\n%s=%s\n' "$key" "$val" >> "$file"
  fi
}

set_env DB_DATABASE "loyalty_new_${BRAND}" "$ENV_FILE"
set_env APP_BRAND "$BRAND" "$ENV_FILE"

echo "==> $ENV_FILE"
grep -E "^(APP_BRAND|DB_DATABASE)=" "$ENV_FILE" | sed 's/^/   /'

# phpunit.xml juga harus pakai DB test brand ini.
PU="$APP_DIR/phpunit.xml"
if [ -f "$PU" ]; then
  python3 - "$PU" "loyalty_new_${BRAND}_test" <<'PY'
import sys, re
path, db = sys.argv[1], sys.argv[2]
s = open(path).read()
s = re.sub(r'(name="DB_DATABASE"\s+value=")[^"]*(")', rf'\g<1>{db}\g<2>', s)
open(path, "w").write(s)
PY
  echo "==> $PU DB_DATABASE -> loyalty_new_${BRAND}_test"
fi

echo "Selesai. Migrasi: (cd apps/$BRAND && php artisan migrate --force && php artisan db:seed --force)"
