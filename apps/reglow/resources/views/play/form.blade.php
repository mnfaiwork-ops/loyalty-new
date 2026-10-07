@extends('layouts.play')

@section('title', $theme['name'].' · Paketmu sudah sampai?')

@section('content')
    <header class="brandbar">
        <div class="brand-tile" aria-hidden="true">{{ $theme['initial'] }}</div>
        <div>
            <div class="brand-name">{{ $theme['name'] }}</div>
            <div class="brand-sub">Poin &amp; Hadiah</div>
        </div>
    </header>

    <form class="entry" method="POST" action="{{ route('play.start') }}" id="entry" novalidate>
        @csrf
        <div class="entry-body">
            <div>
                <h1>Paketmu sudah sampai?</h1>
                <p class="lead">Masukkan nomor resi dan nomor HP untuk membuka Gatcha-mu.</p>
            </div>

            @if ($errors->any())
                <div class="alert" role="alert">
                    <b>{{ $errors->first('awb') ?: $errors->first('phone') }}</b>
                    @unless ($errors->has('phone') || str_contains($errors->first('awb'), 'menit') || str_contains($errors->first('awb'), 'berakhir') || str_contains($errors->first('awb'), 'wajib'))
                        Cek lagi angka di label paket, dan pakai nomor HP yang dipakai saat memesan.
                    @endunless
                </div>
            @endif

            <div class="field">
                <label for="awb">Nomor resi (AWB) atau nomor order</label>
                <input class="input @error('awb') is-error @enderror" type="text" id="awb" name="awb"
                       value="{{ old('awb') }}" placeholder="Contoh: {{ $theme['awb_example'] }}"
                       autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="64" required
                       aria-describedby="awb-hint">
                <div class="hint" id="awb-hint">Ada di label paket, di bawah barcode.</div>
            </div>

            <div class="field">
                <label for="phone">Nomor HP (WhatsApp)</label>
                <div class="phone @error('phone') is-error @enderror">
                    <span aria-hidden="true">+62</span>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="812 3456 4471"
                           inputmode="numeric" autocomplete="tel-national" maxlength="20" required aria-describedby="phone-hint">
                </div>
                <div class="hint" id="phone-hint">Harus sama dengan nomor saat memesan.</div>
            </div>
        </div>

        <div class="entry-foot">
            <button type="submit" class="btn btn-brand" id="entrySubmit" disabled>Cek &amp; masuk</button>
            <small>Tanpa daftar, tanpa kata sandi.</small>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('entry');
        const awb = document.getElementById('awb');
        const phone = document.getElementById('phone');
        const submit = document.getElementById('entrySubmit');
        const sync = () => {
            submit.disabled = awb.value.trim().length < 4 || phone.value.replace(/\D/g, '').length < 8;
        };
        awb.addEventListener('input', () => {
            awb.value = awb.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
            sync();
        });
        phone.addEventListener('input', sync);
        form.addEventListener('submit', () => {
            submit.classList.add('is-loading');
            submit.textContent = 'Mengecek…';
        });
        sync();
    })();
</script>
@endpush
