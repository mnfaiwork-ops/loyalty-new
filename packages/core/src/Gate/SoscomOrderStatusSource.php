<?php

namespace LoyaltyNew\Core\Gate;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sumber status via Soscom Open API (WMS). Dokumen: docs/api/soscom-open-api.md
 *
 *   GET {base_url}/v1/open/social-commerce/orders/detail?phone={hp}&awb={awb}
 *   X-Api-Key: {key per brand}
 *   200 → {"code": 200, "msg": {...}, "data": {...order...}}
 *   404 → HP + AWB tidak cocok di order yang sama / order tidak ada
 *
 * Endpoint detail belum punya `status_fulfillment`, dan arti `status` vs
 * `orders.status` belum dikonfirmasi tim WMS. Karena itu path field dibaca dari
 * config (`markom.soscom.fields`), supaya bisa diganti tanpa ubah kode.
 *
 * Fail-closed: error jaringan / key salah / bentuk respons aneh → null (ditolak).
 */
final class SoscomOrderStatusSource implements OrderStatusSource
{
    /**
     * @param  array{status_order?: string, status_fulfillment?: string, phone?: string, customer_name?: string}  $fields
     *         path dot-notation di dalam `data`
     */
    public function __construct(
        private string $baseUrl,
        private ?string $apiKey = null,
        private int $timeout = 5,
        private array $fields = [],
    ) {
    }

    public function find(string $awb, string $phone = ''): ?OrderStatus
    {
        $awb = trim($awb);
        $phone = trim($phone);
        if ($awb === '' || $phone === '' || trim($this->baseUrl) === '' || ! $this->apiKey) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->get(rtrim($this->baseUrl, '/').'/v1/open/social-commerce/orders/detail', [
                    'phone' => $phone,
                    'awb' => $awb,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Soscom gate: request error', ['awb' => $awb, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->status() === 404) {
            return null;
        }

        if (in_array($response->status(), [401, 403], true)) {
            Log::error('Soscom gate: API key ditolak, cek SOSCOM_API_KEY', ['status' => $response->status()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Soscom gate: respons tak terduga', ['awb' => $awb, 'status' => $response->status()]);

            return null;
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            return null;
        }

        return new OrderStatus(
            awb: (string) ($data['awb'] ?? $awb),
            phone: $this->string($data, $this->fields['phone'] ?? 'customer_shipping.phone'),
            statusOrder: $this->string($data, $this->fields['status_order'] ?? 'status'),
            statusFulfillment: $this->string($data, $this->fields['status_fulfillment'] ?? 'status_fulfillment'),
            customerName: $this->string($data, $this->fields['customer_name'] ?? 'customer.name'),
        );
    }

    private function string(array $data, string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $value = data_get($data, $path);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
