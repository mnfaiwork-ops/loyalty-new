<?php

namespace LoyaltyNew\Core\Console;

use Illuminate\Console\Command;
use LoyaltyNew\Core\Points\PointWallet;

/**
 * Hanguskan poin yang lewat masa berlaku (spec §4). Dijadwalkan harian. Idempotent.
 *
 *   php artisan loyalty:expire-points
 */
final class ExpirePointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Hanguskan poin yang lewat masa berlaku (brand ini saja)';

    public function handle(): int
    {
        $points = PointWallet::expireDue((string) config('app.brand'));

        $this->info("Poin hangus: {$points}");

        return self::SUCCESS;
    }
}
