@extends('layouts.play')

@section('title', $theme['name'].' · Masuk')

@section('content')
    <header class="brandbar">
        @include('partials.logo')
        <div class="brand-sub">Poin &amp; Hadiah</div>
    </header>

    <form class="entry" method="POST" action="{{ route('customer.login') }}" data-pin-form novalidate>
        @csrf
        <div class="entry-body">
            <div>
                <h1>Masuk dengan nomor HP</h1>
                <p class="lead">Pakai nomor HP dan PIN yang kamu buat waktu pertama main.</p>
            </div>

            {{-- Galat "belum punya PIN" menandai kolom HP karena yang salah jalurnya (4e). --}}
            @error('phone')
                <div class="field-alert" role="alert" id="phone-error"><b aria-hidden="true">!</b><span>{{ $message }}</span></div>
            @enderror

            <div class="field">
                <label for="phone">Nomor HP (WhatsApp)</label>
                <div class="phone @error('phone') is-error @enderror">
                    <span aria-hidden="true">+62</span>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="812 3456 4471"
                           inputmode="numeric" autocomplete="tel-national" maxlength="20" required
                           @error('phone') aria-invalid="true" aria-describedby="phone-error" autofocus @enderror>
                </div>
            </div>

            @include('customer.partials.pin-input', ['name' => 'pin', 'label' => 'PIN', 'autocomplete' => 'current-password'])
        </div>

        <div class="entry-foot">
            <button type="submit" class="btn btn-brand" disabled>Masuk</button>
            @include('customer.partials.forgot-pin')
            <a class="entry-link is-ink" href="{{ route('play.form') }}">Paket baru datang? Masuk dengan nomor resi</a>
        </div>
    </form>
@endsection

@push('scripts')
    @include('customer.partials.pin-form-script')
@endpush
