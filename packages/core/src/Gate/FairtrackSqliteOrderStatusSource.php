<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Sumber status dari DB FairTrack Lead (SQLite) — READ ONLY.
 *
 * Dipakai untuk smoke test / fase transisi sebelum API Markom resmi tersedia.
 * Produsi sebaiknya pakai MarkomHttpOrderStatusSource (sumber yang sama
 * dengan yang menulis status_fulfillment).
 *
 * Fail-closed: DB tidak ada / tak bisa dibaca / order tak ketemu → null.
 */
final class FairtrackSqliteOrderStatusSource implements OrderStatusSource
{
    public function __construct(
        private string $database,
        private string $ordersTable = 'orders',
    ) {
    }

    public function find(string $awb, string $phone = ''): ?OrderStatus
    {
        $awb = trim($awb);
        if ($awb === '' || trim($this->database) === '' || ! is_file($this->database)) {
            return null;
        }

        try {
            $pdo = $this->connect();

            // select * supaya DB lama tanpa kolom customer_name tetap terbaca.
            $stmt = $pdo->prepare('select * from '.$this->ordersTable.' where awb = :awb limit 1');
            $stmt->execute([':awb' => $awb]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            // Fail-closed: sumber tak terbaca = tolak. Tanpa depend ke facade Log
            // supaya kelas tetap bisa diuji tanpa bootstrap Laravel.
            return null;
        }

        if (! $row) {
            return null;
        }

        return new OrderStatus(
            awb: (string) $row['awb'],
            phone: $row['customer_phone'] ?? null,
            statusOrder: $row['status_order'] ?? null,
            statusFulfillment: $row['status_fulfillment'] ?? null,
            customerName: $row['customer_name'] ?? null,
        );
    }

    /**
     * Buka SQLite read-only. Pakai URI mode=ro kalau didukung, kalau tidak
     * buka biasa (query hanya SELECT). Tidak memakai PDO::ATTR_OPEN_FLAGS
     * karena konstan itu tidak selalu tersedia di build PDO_SQLITE.
     */
    private function connect(): \PDO
    {
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

        try {
            return new \PDO('sqlite:file:'.$this->database.'?mode=ro', null, null, $options);
        } catch (\Throwable) {
            return new \PDO('sqlite:'.$this->database, null, null, $options);
        }
    }
}
