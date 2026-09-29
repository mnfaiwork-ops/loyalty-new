@extends('layouts.play')

@section('title', 'Hadiahku · '.$theme['name'])

@section('content')
    <div class="page">
        <a class="back" href="{{ route('home') }}"><span aria-hidden="true">‹</span> Beranda</a>
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
                        Tidak ada hadiah yang menunggu redeem. Main Gatcha atau tukar poin di <a href="{{ route('catalog') }}">Katalog</a>.
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
