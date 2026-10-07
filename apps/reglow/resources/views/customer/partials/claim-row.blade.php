<a class="claim-row" href="{{ route('rewards.show', $claim->claim_code) }}">
    <span class="thumb {{ $claim->isBigPrize() ? 'is-gold' : '' }}" aria-hidden="true"></span>
    <span class="claim-row-body">
        <b>{{ $claim->prize_name }}</b>
        <small>Kode {{ $claim->claim_code }} · {{ $claim->created_at->translatedFormat('j M') }}</small>
    </span>
    <span class="status status-{{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span>
</a>
