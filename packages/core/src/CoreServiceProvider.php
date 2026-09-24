<?php

namespace LoyaltyNew\Core;

use Illuminate\Support\ServiceProvider;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Gate\StubOrderGate;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fase 1: stub. Nanti tinggal ganti binding ke MarkomApiOrderGate.
        $this->app->bind(OrderGate::class, StubOrderGate::class);
    }
}
