<?php

use App\Http\Controllers\MarkomWebhookController;
use Illuminate\Support\Facades\Route;

// Webhook server-to-server dari Markom (tanpa CSRF/session — pakai HMAC).
Route::post('/webhooks/markom', MarkomWebhookController::class);
