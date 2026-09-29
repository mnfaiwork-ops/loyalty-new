@extends('layouts.admin')

@section('title', 'Klaim '.$claim->claim_code)

@push('head')
<style>
    .detail { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .panel { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px; }
    .kv { display: grid; grid-template-columns: 150px 1fr; gap: 10px 14px; margin: 0; font-size: 14px; }
    .kv dt { color: var(--muted); font-weight: 700; }
    .kv dd { margin: 0; font-weight: 800; overflow-wrap: anywhere; }
    .verify label { display: block; font-size: 13px; font-weight: 800; margin: 12px 0 6px; }
    .verify input, .verify textarea { width: 100%; }
    .match { margin-top: 8px; font-size: 13px; font-weight: 800; min-height: 20px; }
    .match.yes { color: var(--ok); }
    .match.no { color: var(--danger); }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .events { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; font-size: 13px; }
    .events small { color: var(--muted); }
    @media (max-width: 820px) { .detail { grid-template-columns: 1fr; } .kv { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <a class="link" href="{{ route('admin.claims') }}">‹ Klaim WhatsApp</a>
    <h1 style="margin-top:10px">Klaim <code>{{ $claim->claim_code }}</code></h1>
    <p class="lead"><span class="status {{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span></p>

    <div class="detail">
        <div class="panel">
            <dl class="kv">
                <dt>Nama pemenang</dt><dd>{{ $claim->customer?->name ?: 'Nama belum tersedia' }}</dd>
                <dt>Nomor pemenang</dt><dd id="winner" data-phone="{{ $claim->customer?->phone }}">{{ $claim->customer?->phone }}</dd>
                <dt>Hadiah</dt><dd>{{ $claim->prize_name }} @if ($claim->isBigPrize()) <span class="badge gold">Hadiah utama</span> @endif</dd>
                <dt>Sumber</dt><dd>{{ $claim->source === 'katalog' ? 'Tukar poin ('.$claim->points_spent.' poin)' : 'Gatcha' }}</dd>
                <dt>Batas redeem</dt><dd>{{ $claim->expires_at->format('d/m/Y') }}</dd>
                @if ($claim->rejected_reason)
                    <dt>Penolakan terakhir</dt><dd>{{ $claim->rejected_reason }}</dd>
                @endif
            </dl>

            <h2>Riwayat</h2>
            <ol class="events">
                @foreach ($claim->events as $event)
                    <li><b>{{ \LoyaltyNew\Core\Prizes\ClaimFlow::eventLabel($event->from_status, $event->to_status) }}</b>
                        <small>· {{ $event->created_at->format('d/m H:i') }} · {{ $event->actor }}@if ($event->note) · {{ $event->note }}@endif</small></li>
                @endforeach
            </ol>
        </div>

        @if ($claim->status === 'diajukan')
            <div class="panel verify">
                <h2 style="margin-top:0">Cocokkan nomor</h2>
                <p class="muted">Tempel nomor pengirim dari chat WhatsApp. Konfirmasi hanya kalau nomornya sama dengan nomor pemenang.</p>
                <form method="POST" id="verifyForm">
                    @csrf
                    <label for="sender_phone">Nomor pengirim WhatsApp</label>
                    <input type="text" id="sender_phone" name="sender_phone" inputmode="tel" autocomplete="off" required value="{{ old('sender_phone') }}">
                    <div class="match" id="match" aria-live="polite"></div>
                    <label for="note">Catatan (opsional)</label>
                    <textarea id="note" name="note" rows="2" maxlength="255" placeholder="Misal: alamat Kendari dicek">{{ old('note') }}</textarea>
                    <div class="actions">
                        <button class="btn ok" type="submit" formaction="{{ route('admin.claims.confirm', $claim->claim_code) }}" id="confirmBtn" disabled>Konfirmasi</button>
                        <button class="btn danger" type="submit" formaction="{{ route('admin.claims.reject', $claim->claim_code) }}" id="rejectBtn" disabled>Tolak: nomor tidak cocok</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const input = document.getElementById('sender_phone');
        if (!input) return;
        const norm = v => { let d = String(v).replace(/\D/g, ''); if (d.startsWith('62')) d = '0' + d.slice(2); return d; };
        const winner = norm(document.getElementById('winner').dataset.phone);
        const match = document.getElementById('match');
        const sync = () => {
            const value = norm(input.value);
            const filled = value.length >= 8;
            const same = filled && value === winner;
            document.getElementById('confirmBtn').disabled = !same;
            document.getElementById('rejectBtn').disabled = !filled || same;
            match.textContent = !filled ? '' : (same ? 'Nomor cocok dengan pemenang.' : 'Nomor berbeda dengan pemenang.');
            match.className = 'match ' + (!filled ? '' : (same ? 'yes' : 'no'));
        };
        input.addEventListener('input', sync);
        sync();
    })();
</script>
@endpush
