@extends('layouts.play')

@section('title', 'Reglow — Main')

@section('content')
    <div class="brand">Reglow</div>
    <h1 class="headline">Buka Hadiahmu</h1>
    <p class="sub">Masukkan nomor AWB dan nomor HP untuk mulai bermain.</p>

    <form method="POST" action="{{ route('play.start') }}">
        @csrf
        <label for="awb">Nomor AWB</label>
        <input type="text" id="awb" name="awb" value="{{ old('awb') }}" placeholder="Contoh: RG123" autocomplete="off">
        @error('awb') <div class="error">{{ $message }}</div> @enderror

        <label for="phone">Nomor HP</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone') }}" placeholder="Contoh: 08123456789" inputmode="numeric" autocomplete="off">
        @error('phone') <div class="error">{{ $message }}</div> @enderror

        <button type="submit" class="btn">Mulai Main</button>
    </form>

    <p class="note">Main berlaku 30 hari sejak paket diterima.</p>
@endsection
