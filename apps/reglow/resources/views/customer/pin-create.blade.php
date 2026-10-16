@extends('layouts.play')

@section('title', $theme['name'].' · '.($replacing ? 'Ganti PIN' : 'Buat PIN'))

@section('content')
    <header class="brandbar">
        <div class="brand-tile" aria-hidden="true">{{ $theme['initial'] }}</div>
        <div>
            <div class="brand-name">{{ $theme['name'] }}</div>
            <div class="brand-sub">Poin &amp; Hadiah</div>
        </div>
    </header>

    <form class="entry" method="POST" action="{{ route('pin.create') }}" data-pin-form data-pin-rules novalidate>
        @csrf
        <div class="entry-body">
            @if ($replacing)
                <div class="ok-strip"><span class="ico" aria-hidden="true">✓</span><span>Masuk dengan PIN dari CS {{ $theme['name'] }}. Tinggal satu langkah.</span></div>
            @endif
            <div>
                @if ($replacing)
                    <h1>Ganti PIN sementara</h1>
                    <p class="lead">PIN dari CS hanya untuk masuk sekali. Buat PIN barumu sekarang.</p>
                @else
                    <h1>Buat PIN</h1>
                    <p class="lead">Lain kali kamu bisa masuk dengan nomor {{ $customer->maskedPhone() }} dan PIN ini, tanpa resi.</p>
                @endif
            </div>

            @include('customer.partials.pin-input', ['name' => 'pin', 'label' => 'PIN baru', 'autocomplete' => 'new-password', 'autofocus' => true, 'hint' => \LoyaltyNew\Core\Customers\CustomerPin::LENGTH.' angka. Hindari angka yang sama semua atau berurutan seperti 1234.'])
            @include('customer.partials.pin-input', ['name' => 'pin_confirmation', 'label' => 'Ketik ulang PIN', 'autocomplete' => 'new-password'])

            <div class="note-box"><span class="ico" aria-hidden="true">i</span><span>Simpan PIN ini sendiri. CS tidak akan pernah menanyakannya.</span></div>
        </div>

        <div class="entry-foot">
            <button type="submit" class="btn btn-brand" disabled>Simpan PIN</button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('customer.partials.pin-form-script')
@endpush
