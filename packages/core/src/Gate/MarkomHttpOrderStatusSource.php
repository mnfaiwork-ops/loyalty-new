<?php

namespace LoyaltyNew\Core\Gate;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sumber status via API Markom.
 *
 * KONTRAK (yang kita definisikan; sesuaikan saat Markom merilis resmi):
 *   GET {base_url}/orders/{awb}
 *   Authorization: Bearer {token}
 *   200 → {"status_order": "...", "status_fulfillment": "...", "customer_phone": "..."}
 *   404 → order tidak ada
 *
 * Fail-closed: error jaringan / status tak terduga → null (dianggap tidak ketemu,
 * sehingga customer ditolak, bukan lolos).
 */
final class MarkomHttpOrderStatusSource implements OrderStatusSource
{
    public function __construct(
        private string $baseUrl,
        private ?string $token = null,
        private int $timeout = 5,
    ) {
    }

    public function find(string $awb): ?OrderStatus
    {
        $awb = trim($awb);
        if ($awb === '' || trim($this->baseUrl) === '') {
            return null;
        }

        try {
            $request = Http::timeout($this->timeout)
                ->acceptJson()
                ->withHeaders($this->token ? ['Authorization' => 'Bearer '.$this->token] : []);

            $response = $request->get(rtrim($this->baseUrl, '/').'/orders/'.rawurlencode($awb));
        } catch (\Throwable $e) {
            Log::warning('Markom gate: request error', ['awb' => $awb, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            Log::warning('Markom gate: respons tak terduga', ['awb' => $awb, 'status' => $response->status()]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        return new OrderStatus(
            awb: $awb,
            phone: $data['customer_phone'] ?? null,
            statusOrder: $data['status_order'] ?? null,
            statusFulfillment: $data['status_fulfillment'] ?? null,
            customerName: $data['customer_name'] ?? null,
        );
    }
}
