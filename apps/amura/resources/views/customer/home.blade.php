@extends('layouts.play')

@section('title', $theme['name'].' · Poin & Hadiah')

@section('content')
    <header class="brandbar">
        <div class="brand-tile" aria-hidden="true">{{ $theme['initial'] }}</div>
        <div class="brandbar-text">
            <div class="brand-name">{{ $theme['name'] }}</div>
            <div class="brand-sub">Poin &amp; Hadiah</div>
        </div>
        <form method="POST" action="{{ route('customer.logout') }}">
            @csrf
            <button type="submit" class="link-btn">Bukan kamu? Keluar</button>
        </form>
    </header>

    <div class="page">
        @if (session('ok'))
            <div class="notice" role="status">{{ session('ok') }}</div>
        @endif

        <section class="wallet" aria-label="Saldo poin">
            <div class="wallet-hi">Halo, {{ $customer->name ?: 'Pelanggan '.$theme['name'] }} · {{ $customer->maskedPhone() }}</div>
            <div class="wallet-label">Poin {{ $theme['name'] }}</div>
            <div class="wallet-balance serif">{{ number_format($balance, 0, ',', '.') }}</div>
            <div class="wallet-note">
                @if ($expiring)
                    {{ number_format($expiring['points'], 0, ',', '.') }} poin hangus {{ $expiring['expires_at']->translatedFormat('j F Y') }}
                @else
                    Poin didapat dari kado Gatcha.
                @endif
            </div>
        </section>

        @if ($open->isNotEmpty())
            @php($first = $open->first())
            <section class="due">
                <div class="due-head">
                    <b>{{ $open->count() }} hadiah belum di-redeem</b>
                    <span class="pill">{{ $first->daysLeft() }} hari lagi</span>
                </div>
                <div class="due-item">
                    <span class="thumb {{ $first->isBigPrize() ? 'is-gold' : '' }}" aria-hidden="true"></span>
                    <span>
                        <b>{{ $first->prize_name }}</b>
                        <small>Kode {{ $first->claim_code }} · redeem sebelum {{ $first->expires_at->translatedFormat('j M') }}</small>
                    </span>
                </div>
                <a class="btn btn-wa" href="{{ route('rewards.show', $first->claim_code) }}">Redeem sekarang</a>
            </section>
        @endif

        <section>
            <div class="section-head">
                <h2>Hadiahku</h2>
                <a href="{{ route('rewards') }}">Lihat semua</a>
            </div>
            <div class="claim-list">
                @forelse ($recent as $claim)
                    @include('customer.partials.claim-row', ['claim' => $claim])
                @empty
                    <p class="empty-card">Belum ada hadiah. Main Gatcha untuk mendapatkan poin dan hadiah.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="entry-foot">
        <div class="home-actions">
            <a class="btn btn-outline" href="{{ route('catalog') }}">Katalog</a>
            @if ($session)
                <a class="btn btn-brand" href="{{ route('play.board', $session->session_token) }}">Gatcha ({{ $chances }})</a>
            @else
                <span class="btn btn-off" aria-disabled="true">Gatcha (0)</span>
            @endif
        </div>
    </div>
@endsection
