@extends('layouts.play')

@section('title', 'Tukar Poin · '.$theme['name'])

@section('content')
    <div class="page">
        <a class="back" href="{{ route('home') }}"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg> Beranda</a>
        <div class="catalog-head">
            <h1>Tukar poin</h1>
            <span class="balance-chip">Saldo <b>{{ number_format($balance, 0, ',', '.') }}</b> poin</span>
        </div>

        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        @if (! $catalog || $items->isEmpty())
            <p class="empty-card">Katalog bulan ini segera hadir. Poinmu tetap aman.</p>
        @else
            <p class="catalog-note">Minimal tukar {{ number_format($minimum, 0, ',', '.') }} poin. Ongkir hadiah ditanggung penerima.</p>
            <div class="catalog-list">
                @foreach ($items as $item)
                    @php
                        $short = max($minimum, $item->points_cost) - $balance;
                    @endphp
                    <article class="reward-item">
                        <span class="thumb" aria-hidden="true"></span>
                        <div class="reward-body">
                            <b>{{ $item->name }}</b>
                            <span class="reward-cost">{{ number_format($item->points_cost, 0, ',', '.') }} poin</span>
                            @if ($item->stock !== null)
                                <small>Sisa {{ $item->remaining() }}</small>
                            @endif
                        </div>
                        @if (! $item->inStock())
                            <span class="btn-sm is-off">Stok habis</span>
                        @elseif ($short > 0)
                            <span class="btn-sm is-off">Kurang {{ number_format($short, 0, ',', '.') }} poin</span>
                        @else
                            <button type="button" class="btn-sm" data-open="confirm-{{ $item->id }}" aria-expanded="false" aria-controls="confirm-{{ $item->id }}">Tukar</button>
                        @endif

                        <form id="confirm-{{ $item->id }}" class="reward-confirm" method="POST" action="{{ route('catalog.exchange', $item->id) }}" hidden>
                            @csrf
                            <p>Poin dipotong {{ number_format($item->points_cost, 0, ',', '.') }}. Hadiah masuk Hadiahku, redeem sebelum {{ $redeemBy->translatedFormat('j F Y') }}.</p>
                            <div class="reward-confirm-actions">
                                <button type="submit" class="btn-sm">Ya, tukar</button>
                                <button type="button" class="btn-sm is-ghost" data-close="confirm-{{ $item->id }}">Batal</button>
                            </div>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-open]').forEach(btn => btn.addEventListener('click', () => {
        const panel = document.getElementById(btn.dataset.open);
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        panel.querySelector('button[type="submit"]').focus();
    }));
    document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => {
        const panel = document.getElementById(btn.dataset.close);
        panel.hidden = true;
        const opener = document.querySelector(`[data-open="${btn.dataset.close}"]`);
        opener?.setAttribute('aria-expanded', 'false');
        opener?.focus();
    }));
    document.querySelectorAll('.reward-confirm').forEach(form => form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"]').disabled = true;
    }));
</script>
@endpush
