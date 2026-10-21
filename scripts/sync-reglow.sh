#!/usr/bin/env bash
# Samakan Reglow dengan Amura. Semua kode app bersama (backend, UI pelanggan, panel
# admin, migration) disalin apa adanya; tampilan per brand datang dari config/play.php.
# Test Reglow adalah salinan test Amura dengan nama brand ditukar.
# Yang TIDAK disalin karena memang per brand: config/app.php, database/seeders, .env*.
# Jalankan dari root repo: bash scripts/sync-reglow.sh
set -euo pipefail

A=apps/amura
R=apps/reglow

# Folder bersama: isi Reglow disamakan persis, termasuk menghapus file yang tidak ada di Amura.
for d in app database/migrations resources/views public/css public/js public/img; do
    mkdir -p "$R/$d"
    rsync -a --delete "$A/$d/" "$R/$d/"
done

for f in bootstrap/app.php config/play.php config/session.php config/trustedproxy.php routes/web.php; do
    cp "$A/$f" "$R/$f"
done

swap() {
    sed -e 's/amura/__B1__/g; s/reglow/amura/g; s/__B1__/reglow/g' \
        -e 's/Amura/__B2__/g; s/Reglow/Amura/g; s/__B2__/Reglow/g' \
        -e 's/AMURA/__B3__/g; s/REGLOW/AMURA/g; s/__B3__/REGLOW/g' "$1" > "$2"
}

for t in "$A"/tests/TestCase.php "$A"/tests/Feature/*.php "$A"/tests/Unit/*.php; do
    swap "$t" "$R/${t#"$A/"}"
done

echo "Selesai. Jalankan: cd $R && php artisan test"
