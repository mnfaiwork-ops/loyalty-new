@extends('layouts.play')

@section('title', $theme['name'].' · Masukkan PIN')

@section('content')
    <header class="brandbar">
        <div class="brand-tile" aria-hidden="true">{{ $theme['initial'] }}</div>
        <div>
            <div class="brand-name">{{ $theme['name'] }}</div>
            <div class="brand-sub">Poin &amp; Hadiah</div>
        </div>
    </header>

    <form class="entry" method="POST" action="{{ route('pin.show') }}" data-pin-form novalidate>
        @csrf
        <div class="entry-body">
            <div>
                <h1>Masukkan PIN</h1>
                <p class="lead">Resi cocok. Nomor {{ $customer->maskedPhone() }} sudah punya PIN, masukkan untuk lanjut.</p>
            </div>

            @include('customer.partials.pin-input', ['name' => 'pin', 'label' => 'PIN', 'autocomplete' => 'current-password', 'autofocus' => true])
        </div>

        <div class="entry-foot">
            <button type="submit" class="btn btn-brand" disabled>Lanjut</button>
            @include('customer.partials.forgot-pin')
        </div>
    </form>
@endsection

@push('scripts')
    @include('customer.partials.pin-form-script')
@endpush
