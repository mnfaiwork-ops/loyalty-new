<?php

namespace LoyaltyNew\Core\Prizes;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\ClaimEvent;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\RewardCatalogItem;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Points\PointWallet;

/**
 * Mesin status klaim (spec §3). Setiap transisi dikunci per klaim, dicek status
 * asalnya, lalu dicatat di claim_events.
 */
final class ClaimFlow
{
    public const STATUSES = ['belum_redeem', 'diajukan', 'perlu_finance', 'diproses', 'dikirim', 'selesai', 'hangus'];

    /** Status yang hangus kalau lewat batas redeem. */
    public const EXPIRABLE = ['belum_redeem', 'diajukan', 'perlu_finance'];

    public const IN_PROGRESS = ['diajukan', 'perlu_finance', 'diproses', 'dikirim'];

    public const DONE = ['selesai', 'hangus'];

    public const REJECT_REASON = 'Nomor pengirim tidak cocok';

    public static function request(Claim $claim, Customer $customer): Claim
    {
        return self::move($claim, ['belum_redeem'], 'diajukan', 'customer', null, null,
            function (Claim $locked) use ($customer) {
                if ((int) $locked->customer_id !== (int) $customer->id) {
                    throw new ClaimTransitionException('Klaim ini bukan milikmu.');
                }
                self::ensureNotExpired($locked);

                return ['redeem_requested_at' => now(), 'rejected_reason' => null];
            });
    }

    public static function confirm(Claim $claim, User $cs, string $senderPhone, ?string $note = null): Claim
    {
        $to = $claim->isBigPrize() ? 'perlu_finance' : 'diproses';

        return self::move($claim, ['diajukan'], $to, 'cs', $cs, $note,
            function (Claim $locked) use ($senderPhone, $cs) {
                self::ensureNotExpired($locked);
                if (! self::phoneMatches($locked, $senderPhone)) {
                    throw new ClaimTransitionException('Nomor pengirim tidak cocok dengan nomor pemenang. Tolak klaim ini.');
                }

                return ['handled_by' => $cs->id, 'claimed_at' => now()];
            });
    }

    public static function reject(Claim $claim, User $cs, string $senderPhone, ?string $note = null): Claim
    {
        return self::move($claim, ['diajukan'], 'belum_redeem', 'cs', $cs, $note,
            function (Claim $locked) use ($senderPhone, $cs) {
                if (self::phoneMatches($locked, $senderPhone)) {
                    throw new ClaimTransitionException('Nomor pengirim cocok dengan nomor pemenang. Konfirmasi klaim ini.');
                }

                return ['handled_by' => $cs->id, 'rejected_reason' => self::REJECT_REASON];
            });
    }

    public static function approveFinance(Claim $claim, User $finance): Claim
    {
        return self::move($claim, ['perlu_finance'], 'diproses', 'finance', $finance, null,
            function (Claim $locked) use ($finance) {
                self::ensureNotExpired($locked);

                return ['finance_by' => $finance->id, 'claimed_at' => now()];
            });
    }

    public static function ship(Claim $claim, User $cs, string $awb): Claim
    {
        return self::move($claim, ['diproses'], 'dikirim', 'cs', $cs, null,
            fn () => ['shipping_awb' => trim($awb), 'handled_by' => $cs->id]);
    }

    public static function deliver(Claim $claim, User $cs): Claim
    {
        return self::move($claim, ['dikirim'], 'selesai', 'cs', $cs, null, fn () => ['handled_by' => $cs->id]);
    }

    /** Hanguskan klaim. Hadiah utama mengembalikan kuota; hadiah katalog mengembalikan poin dan stok. */
    public static function expire(Claim $claim, string $actor = 'system', ?User $user = null): Claim
    {
        $expired = self::move($claim, self::EXPIRABLE, 'hangus', $actor, $user, null, fn () => [],
            function (Claim $locked) {
                ClaimService::releaseBigPrizeQuota($locked, (string) $locked->brand);

                if ($locked->source === 'katalog' && $locked->customer_id) {
                    $customer = Customer::withoutGlobalScopes()->findOrFail($locked->customer_id);
                    (new PointWallet($customer))->refund($locked);

                    if ($locked->reward_catalog_item_id) {
                        RewardCatalogItem::whereKey($locked->reward_catalog_item_id)
                            ->where('stock_used', '>', 0)
                            ->decrement('stock_used');
                    }
                }
            });

        return $expired;
    }

    public static function phoneMatches(Claim $claim, string $senderPhone): bool
    {
        $winner = Customer::withoutGlobalScopes()->whereKey($claim->customer_id)->value('phone');
        $sender = AwbMatcher::normalizePhone($senderPhone);

        return $winner !== null && $sender !== '' && AwbMatcher::normalizePhone($winner) === $sender;
    }

    /** Label status untuk pelanggan dan dashboard. */
    public static function label(string $status): string
    {
        return match ($status) {
            'belum_redeem' => 'Belum di-redeem',
            'diajukan' => 'Menunggu CS',
            'perlu_finance' => 'Diperiksa Finance',
            'diproses' => 'Dikemas',
            'dikirim' => 'Dikirim',
            'selesai' => 'Diterima',
            'hangus' => 'Hangus',
            default => $status,
        };
    }

    /** Label satu langkah di timeline status (layar 3d). */
    public static function eventLabel(?string $from, string $to): string
    {
        return match (true) {
            $to === 'belum_redeem' && $from === null => 'Hadiah didapat',
            $to === 'belum_redeem' => 'Ditolak CS: nomor pengirim tidak cocok',
            $to === 'diajukan' => 'Redeem diajukan',
            $to === 'perlu_finance' => 'Dikonfirmasi CS, diperiksa Finance',
            $to === 'diproses' && $from === 'perlu_finance' => 'Disetujui Finance, sedang dikemas',
            $to === 'diproses' => 'Dikonfirmasi CS, sedang dikemas',
            $to === 'dikirim' => 'Dalam pengiriman',
            $to === 'selesai' => 'Diterima',
            $to === 'hangus' => 'Hangus: lewat batas redeem',
            default => self::label($to),
        };
    }

    /** @return array{diajukan:int,perlu_finance:int,diproses:int,dikirim:int} */
    public static function counts(string $brand): array
    {
        $rows = Claim::where('brand', $brand)
            ->whereIn('status', ['diajukan', 'perlu_finance', 'diproses', 'dikirim'])
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'diajukan' => (int) ($rows['diajukan'] ?? 0),
            'perlu_finance' => (int) ($rows['perlu_finance'] ?? 0),
            'diproses' => (int) ($rows['diproses'] ?? 0),
            'dikirim' => (int) ($rows['dikirim'] ?? 0),
        ];
    }

    private static function ensureNotExpired(Claim $claim): void
    {
        if ($claim->expires_at?->isPast()) {
            throw new ClaimTransitionException("Klaim {$claim->claim_code} sudah lewat batas redeem.");
        }
    }

    /**
     * @param  array<int,string>  $from
     * @param  callable(Claim):array  $check  validasi + kolom tambahan yang ikut disimpan
     * @param  callable(Claim):void|null  $after  efek samping setelah status berubah
     */
    private static function move(Claim $claim, array $from, string $to, string $actor, ?User $user, ?string $note,
        callable $check, ?callable $after = null): Claim
    {
        return DB::transaction(function () use ($claim, $from, $to, $actor, $user, $note, $check, $after) {
            $locked = Claim::with('gamePrize')->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $from, true)) {
                $current = self::label($locked->status);
                throw new ClaimTransitionException("Klaim {$locked->claim_code} sudah berstatus {$current}, tidak bisa diubah.");
            }

            $extra = $check($locked);
            $previous = $locked->status;
            $locked->update(['status' => $to] + $extra);

            ClaimEvent::create([
                'claim_id' => $locked->id,
                'from_status' => $previous,
                'to_status' => $to,
                'actor' => $actor,
                'user_id' => $user?->id,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ]);

            if ($after) {
                $after($locked);
            }

            return $locked;
        });
    }
}
