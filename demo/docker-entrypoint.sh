#!/usr/bin/env bash
# Start container demo: siapkan DB brand, cache config, lalu jalankan Apache.
set -euo pipefail

: "${APP_BRAND:?APP_BRAND wajib diisi (amura atau reglow)}"
export APP_ROOT="/app/apps/${APP_BRAND}"
cd "$APP_ROOT"

# Render memberi string acak; Laravel butuh tepat 32 byte.
if [[ "${APP_KEY:-}" != base64:* ]]; then
    : "${APP_KEY_SEED:?APP_KEY atau APP_KEY_SEED wajib diisi}"
    APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_KEY_SEED"), true));')"
    export APP_KEY
fi

export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"
export MARKOM_DEMO_FILE="${MARKOM_DEMO_FILE:-/app/demo/orders-${APP_BRAND}.csv}"
export DEMO_SETTING_FILE="${DEMO_SETTING_FILE:-/app/demo/setting-${APP_BRAND}.json}"
export DB_SEARCH_PATH="${DB_SEARCH_PATH:-${APP_BRAND}}"

php artisan loyalty:demo-setup
php artisan storage:link --quiet || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

# Klaim dan poin hangus tetap jalan seperti di produksi.
runuser -u www-data -- php artisan schedule:work >/dev/null 2>&1 &

exec apache2-foreground
