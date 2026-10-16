@extends('layouts.play')

@section('title', 'Hadiahku · '.$theme['name'])

@section('content')
    <div class="page">
        <a class="back" href="{{ route('home') }}"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg> Beranda</a>
        <h1>Hadiahku</h1>

        <nav class="tabs" aria-label="Status hadiah">
            @foreach (['belum' => 'Belum di-redeem', 'proses' => 'Sedang diproses', 'selesai' => 'Selesai & hangus'] as $key => $label)
                <a href="{{ route('rewards', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="claim-list">
            @forelse ($claims as $claim)
                @include('customer.partials.claim-row', ['claim' => $claim])
            @empty
                <p class="empty-card">
                    @if ($tab === 'belum')
                        Tidak ada hadiah yang menunggu redeem. Main Gatcha atau <a href="{{ route('catalog') }}">tukar poin</a>.
                    @elseif ($tab === 'proses')
                        Belum ada hadiah yang sedang diproses CS.
                    @else
                        Belum ada hadiah yang selesai atau hangus.
                    @endif
                </p>
            @endforelse
        </div>
    </div>
@endsection
