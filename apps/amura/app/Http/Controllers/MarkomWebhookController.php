<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Gate\CachedOrderStatusSource;
use LoyaltyNew\Core\Gate\WebhookSignature;

/**
 * Penerima webhook Markom: saat status paket berubah, Markom memanggil ini
 * supaya cache status AWB dibuang (gate lalu ambil status segar).
 *
 * Keamanan: HMAC-SHA256 atas body mentah, header X-Markom-Signature,
 * secret dari config markom.webhook_secret. Fail-closed.
 *
 * Kontrak body (yang kita definisikan; minimal 'awb'):
 *   {"awb":"LNCH...","status_order":"completed","status_fulfillment":"complete"}
 */
final class MarkomWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $signature = $request->header('X-Markom-Signature');
        $secret = config('markom.webhook_secret');

        if (! WebhookSignature::verify($request->getContent(), $signature, $secret)) {
            return response()->json(['ok' => false, 'error' => 'Tanda tangan tidak valid'], 401);
        }

        $data = $request->validate([
            'awb' => ['required', 'string', 'max:64'],
        ]);

        CachedOrderStatusSource::forget((string) config('app.brand'), $data['awb']);

        return response()->json(['ok' => true]);
    }
}
