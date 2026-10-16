@extends('layouts.admin')

@section('title', 'Tampilan & pop up')

@push('head')
<style>
    .stack { display: grid; gap: 14px; padding: 16px; max-width: 640px; }
    .stack label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; }
    .stack input[type=url], .stack input[type=file] {
        font-family: inherit; font-size: 14px; min-height: 44px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--ink);
    }
    .stack fieldset { border: 1px solid var(--line); border-radius: 10px; padding: 8px 12px 4px; margin: 0; }
    .stack legend { font-size: 13px; font-weight: 800; padding: 0 4px; }
    .check { display: flex; flex-direction: row !important; align-items: center; gap: 8px; font-weight: 500 !important; min-height: 44px; }
    .check input { width: 20px; height: 20px; }
    .hint { font-size: 12px; font-weight: 500; color: var(--muted); margin: 0; }
    .popup-now { display: flex; gap: 14px; align-items: flex-start; padding: 16px 16px 0; flex-wrap: wrap; }
    .popup-now img { width: 140px; height: auto; border-radius: 10px; border: 1px solid var(--line); }
    .popup-now p { margin: 0 0 4px; font-size: 14px; }
    .stack .actions { display: flex; }
</style>
@endpush

@section('content')
    <h1>Tampilan &amp; pop up</h1>
    <p class="lead">Ganti judul halaman resi dan pasang pop up promo untuk pelanggan {{ ucfirst((string) config('app.brand')) }}.</p>

    <h2>Halaman resi</h2>
    <div class="card">
        <form method="post" action="{{ route('admin.display.texts') }}" class="stack">
            @csrf
            <label>Judul
                <input type="text" name="play_title" value="{{ old('play_title', $content['play_title']) }}" maxlength="80" placeholder="{{ \LoyaltyNew\Core\Content\SiteContent::DEFAULT_TITLE }}">
            </label>
            <label>Keterangan di bawah judul
                <textarea name="play_lead" rows="2" maxlength="160" placeholder="{{ \LoyaltyNew\Core\Content\SiteContent::DEFAULT_LEAD }}">{{ old('play_lead', $content['play_lead']) }}</textarea>
            </label>
            <p class="hint">Kosongkan untuk kembali ke teks bawaan: "{{ \LoyaltyNew\Core\Content\SiteContent::DEFAULT_TITLE }}"</p>
            <div class="actions"><button class="btn" type="submit">Simpan teks</button></div>
        </form>
    </div>

    <h2>Pop up promo</h2>
    <div class="card">
        @php($popup = $content['popup'])
        <div class="popup-now">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $popup['alt'] }}">
            @endif
            <div>
                @if ($popup['active'] && $imageUrl && $popup['pages'])
                    <p><span class="status selesai">Aktif</span></p>
                    <p>Muncul di: {{ collect($popup['pages'])->map(fn ($page) => $pages[$page])->join(', ') }}</p>
                    <p class="muted">{{ $popup['link'] ? 'Gambar diklik membuka '.$popup['link'] : 'Gambar diklik menutup pop up' }}</p>
                @else
                    <p><span class="status">Tidak aktif</span></p>
                    <p class="muted">{{ $imageUrl ? 'Pelanggan tidak melihat pop up ini.' : 'Belum ada gambar. Unggah gambar di bawah untuk memasang pop up.' }}</p>
                @endif
            </div>
        </div>

        <form method="post" action="{{ route('admin.display.popup') }}" enctype="multipart/form-data" class="stack">
            @csrf
            <label>Gambar {{ $imageUrl ? '(kosongkan kalau tidak diganti)' : '' }}
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </label>
            <p class="hint">JPG, PNG, atau WebP, maksimal 2 MB. Paling pas tegak 4:5 atau persegi, lebar minimal 720 px.</p>
            <label>Teks gambar
                <input type="text" name="alt" value="{{ old('alt', $popup['alt']) }}" maxlength="120" placeholder="Isi promo, misalnya: Rebut emas Antam sekarang">
            </label>
            <p class="hint">Dibacakan pembaca layar dan tampil kalau gambar gagal dimuat.</p>
            <label>Link tujuan (boleh kosong)
                <input type="url" name="link" value="{{ old('link', $popup['link']) }}" maxlength="500" placeholder="https://">
            </label>
            <p class="hint">Diisi: gambar diklik membuka link ini. Kosong: gambar diklik menutup pop up. Link ke situs lain dibuka di tab baru.</p>
            <fieldset>
                <legend>Muncul di halaman</legend>
                @foreach ($pages as $key => $label)
                    <label class="check"><input type="checkbox" name="pages[]" value="{{ $key }}" @checked(in_array($key, old('pages', $popup['pages']), true))> {{ $label }}</label>
                @endforeach
            </fieldset>
            <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $popup['active']))> Tampilkan ke pelanggan</label>
            <p class="hint">Setiap pelanggan melihat pop up sekali per hari di perangkatnya. Setelah pop up disimpan ulang, pop up muncul lagi untuk semua pelanggan.</p>
            <div class="actions"><button class="btn" type="submit">Simpan pop up</button></div>
        </form>
    </div>
@endsection
