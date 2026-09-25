<?php

namespace LoyaltyNew\Core\Console;

use Illuminate\Console\Command;
use LoyaltyNew\Core\Prizes\ClaimService;

/**
 * Hanguskan klaim yang sudah lewat batas waktu (spec §2: 14 hari biasa, 30 hari
 * hadiah besar). Dijadwalkan harian. Idempotent.
 *
 *   php artisan loyalty:expire-claims
 */
final class ExpireClaimsCommand extends Command
{
    protected $signature = 'loyalty:expire-claims';

    protected $description = 'Tandai klaim yang kedaluwarsa sebagai hangus (brand ini saja)';

    public function handle(): int
    {
        $count = ClaimService::expireOverdue();

        $this->info("Klaim di-hangus: {$count}");

        return self::SUCCESS;
    }
}
