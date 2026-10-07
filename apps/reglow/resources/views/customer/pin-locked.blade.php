@extends('layouts.play')

@section('title', $theme['name'].' · Coba lagi sebentar')

@php
    $clock = sprintf('%d:%02d', intdiv($remaining, 60), $remaining % 60);
    $minutes = (int) ceil($remaining / 60);
@endphp

@section('content')
    <header class="brandbar">
        <div class="brand-tile" aria-hidden="true">{{ $theme['initial'] }}</div>
        <div>
            <div class="brand-name">{{ $theme['name'] }}</div>
            <div class="brand-sub">Poin &amp; Hadiah</div>
        </div>
    </header>

    {{-- Desain 4f, bentuk sama dengan 3a: cincin hitung mundur dan tombol abu dengan sisa waktu. --}}
    <div class="entry" id="pinLock" data-remaining="{{ $remaining }}" data-total="{{ max($total, $remaining) }}" data-retry="{{ $retryUrl }}">
        <div class="entry-body">
            <div class="lock">
                <div class="lock-ring" style="--left: {{ round($remaining / max($total, $remaining, 1), 3) }}" aria-hidden="true">
                    <div class="lock-face">
                        <div class="lock-time" data-lock-clock>{{ $clock }}</div>
                        <div class="lock-unit">menit tersisa</div>
                    </div>
                </div>
                <h1>Coba lagi sebentar, ya</h1>
                <p>Terlalu banyak PIN salah. Coba lagi dalam {{ $minutes }} menit, atau hubungi CS.</p>
            </div>
            <div class="safe-card">
                <b>Poin, hadiah, dan kesempatan Gatcha-mu tetap aman.</b> Yang dikunci hanya masuk dengan nomor {{ $customer->maskedPhone() }}.
            </div>
        </div>

        <div class="entry-foot">
            <button type="button" class="btn btn-brand" data-lock-retry disabled>Coba lagi dalam <span data-lock-clock>{{ $clock }}</span></button>
            @include('customer.partials.forgot-pin')
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Hitung mundur dari jam perangkat; saat habis, tombol aktif dan membuka lagi layar PIN.
    (() => {
        const root = document.getElementById('pinLock');
        const total = Number(root.dataset.total);
        const until = Date.now() + Number(root.dataset.remaining) * 1000;
        const ring = root.querySelector('.lock-ring');
        const clocks = root.querySelectorAll('[data-lock-clock]');
        const retry = root.querySelector('[data-lock-retry]');
        const tick = () => {
            const left = Math.max(0, Math.ceil((until - Date.now()) / 1000));
            const text = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
            clocks.forEach(node => { node.textContent = text; });
            ring.style.setProperty('--left', (left / total).toFixed(3));
            if (left > 0) return;
            clearInterval(timer);
            retry.disabled = false;
            retry.textContent = 'Coba lagi';
        };
        retry.addEventListener('click', () => { location.href = root.dataset.retry; });
        const timer = setInterval(tick, 1000);
        tick();
    })();
</script>
@endpush
