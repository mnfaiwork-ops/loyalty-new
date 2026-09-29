#!/usr/bin/env bash
# Salin backend bersama dari Amura ke Reglow. Test Reglow adalah salinan test
# Amura dengan nama brand ditukar. UI pelanggan Reglow tidak disalin (tahap berikutnya).
# Jalankan dari root repo: bash scripts/sync-reglow.sh
set -euo pipefail

A=apps/amura
R=apps/reglow

cp "$A/database/migrations/2026_09_29_000001_create_points_and_redeem.php" "$R/database/migrations/"

for c in CsClaimController ShippingController FinanceController RewardCatalogController; do
    cp "$A/app/Http/Controllers/Admin/$c.php" "$R/app/Http/Controllers/Admin/"
done

cp "$A/resources/views/layouts/admin.blade.php" "$R/resources/views/layouts/"
for v in claims claim shipping finance catalog; do
    cp "$A/resources/views/admin/$v.blade.php" "$R/resources/views/admin/"
done

swap() {
    sed -e 's/amura/__B1__/g; s/reglow/amura/g; s/__B1__/reglow/g' \
        -e 's/Amura/__B2__/g; s/Reglow/Amura/g; s/__B2__/Reglow/g' \
        -e 's/AMURA/__B3__/g; s/REGLOW/AMURA/g; s/__B3__/REGLOW/g' "$1" > "$2"
}

for t in CsClaimTest FinanceTest ExpireClaimsTest SpecVerificationTest SmokeSeederTest PlayFlowTest \
         BugfixRegressionTest RedeemSchemaTest PointWalletTest ClaimFlowTest RewardCatalogServiceTest \
         ShippingTest RewardCatalogAdminTest; do
    swap "$A/tests/Feature/$t.php" "$R/tests/Feature/$t.php"
done

echo "Selesai. Jalankan: cd $R && php artisan test"
