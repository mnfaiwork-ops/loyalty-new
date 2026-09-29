@extends('layouts.play')

@section('title', $claim->prize_name.' · '.$theme['name'])

@section('content')
    <div class="page">
        <a class="back" href="{{ route('rewards') }}"><span aria-hidden="true">‹</span> Hadiahku</a>

        @if (session('ok'))
            <div class="notice" role="status">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="voucher {{ $claim->isBigPrize() ? 'is-gold' : '' }}" aria-label="Voucher hadiah">
            <div class="voucher-top">
                <span class="chip">{{ $claim->source === 'katalog' ? 'TUKAR POIN' : ($claim->isBigPrize() ? 'HADIAH UTAMA' : 'HADIAH GATCHA') }}</span>
                <span class="status status-{{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span>
            </div>
            <div class="voucher-name serif">{{ $claim->prize_name }}</div>
            <dl class="voucher-meta">
                <div><dt>Nama pemenang</dt><dd>{{ $customer->name ?: 'Nama belum tersedia' }}</dd></div>
                <div><dt>Nomor pemenang</dt><dd>{{ $customer->phone }}</dd></div>
                <div><dt>Kode</dt><dd class="voucher-code">{{ $claim->claim_code }}</dd></div>
                <div><dt>Redeem sebelum</dt><dd>{{ $claim->expires_at->translatedFormat('j F Y') }}</dd></div>
            </dl>
        </section>

        @if ($claim->status === 'belum_redeem')
            @if ($claim->rejected_reason)
                <div class="alert" role="alert"><b>Klaim sebelumnya ditolak.</b> {{ $claim->rejected_reason }}. Kirim dari nomor pemenang.</div>
            @endif
            @if ($claim->daysLeft() === 0)
                <div class="alert" role="alert">Batas redeem sudah lewat. Hadiah ini hangus.</div>
            @else
                <form method="POST" action="{{ route('rewards.redeem', $claim->claim_code) }}" class="redeem-form">
                    @csrf
                    <button type="submit" class="btn btn-brand">Redeem sekarang</button>
                    <small>Setelah ditekan, kirim pesan ke WhatsApp CS dari nomor {{ $customer->phone }}.</small>
                </form>
            @endif
        @elseif ($claim->status === 'diajukan')
            @if ($waUrl)
                <a class="btn btn-wa" href="{{ $waUrl }}" target="_blank" rel="noopener">Buka WhatsApp CS {{ $theme['name'] }}</a>
                <details class="fallback">
                    <summary>WhatsApp tidak terbuka?</summary>
                    <ol>
                        <li>
                            Simpan nomor CS {{ $theme['name'] }}: <b>{{ $waNumber }}</b>
                            <button type="button" class="copy-btn" data-copy="{{ $waNumber }}">Salin nomor</button>
                        </li>
                        <li>
                            Kirim pesan ini dari nomor pemenang:
                            <pre class="wa-text">{{ $waMessage }}</pre>
                            <button type="button" class="copy-btn" data-copy="{{ $waMessage }}">Salin pesan</button>
                        </li>
                    </ol>
                </details>
            @else
                <div class="alert" role="alert">Nomor CS belum diatur. Hubungi admin {{ $theme['name'] }}.</div>
            @endif
        @endif

        <section aria-label="Status redeem">
            <h2>Status</h2>
            <ol class="timeline">
                @foreach ($claim->events as $event)
                    <li>
                        <b>{{ \LoyaltyNew\Core\Prizes\ClaimFlow::eventLabel($event->from_status, $event->to_status) }}</b>
                        <small>
                            {{ $event->created_at->translatedFormat('j M, H.i') }}
                            @if ($event->to_status === 'dikirim' && $claim->shipping_awb) · Resi {{ $claim->shipping_awb }} @endif
                        </small>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.copy-btn').forEach(btn => btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(btn.dataset.copy);
            btn.textContent = 'Tersalin';
        } catch (e) {
            btn.textContent = 'Salin manual ya';
        }
    }));
</script>
@endpush
