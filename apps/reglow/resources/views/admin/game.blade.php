@extends('layouts.admin')

@section('title', 'Panel Game')

@push('head')
<style>
    .row-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .row-form input[type=number] { width: 110px; }
    .row-form input[type=date] { width: 150px; }
    .grid-settings { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; padding: 16px; }
    .grid-settings label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; }
    .check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; min-height: 44px; }
    .paused { border: 2px solid #b42318; }
    .paused-note { margin: 0; padding: 12px 16px 0; color: #b42318; font-weight: 800; }
    .hint { font-size: 12px; margin: 4px 0 0; }
    td .name { font-weight: 800; }
</style>
@endpush

@section('content')
    <h1>Panel Game</h1>
    <p class="lead">Atur seberapa sering hadiah selain poin keluar dari Gatcha. Perubahan berlaku untuk kado yang dibuka setelah disimpan.</p>

    <h2>Kendali Gatcha</h2>
    <div class="card {{ $controls['paused'] ? 'paused' : '' }}">
        @if ($controls['paused'])
            <p class="paused-note">Gatcha sedang dijeda. Semua kado yang dibuka berisi poin.</p>
        @endif
        <form method="post" action="{{ route('admin.game.controls') }}" class="grid-settings">
            @csrf
            <label class="check" style="flex-direction:row">
                <input type="checkbox" name="paused" value="1" @checked($controls['paused'])>
                Jeda hadiah selain poin
            </label>
            <label>
                Peluang hadiah utama per papan (%)
                <input type="number" name="big_prize_chance" min="0" max="100" value="{{ old('big_prize_chance', $controls['big_prize_chance']) }}" required>
            </label>
            <label>
                Order mulai tanggal
                <input type="date" name="order_from" value="{{ old('order_from', $controls['order_from']) }}">
            </label>
            <label>
                Order sampai tanggal
                <input type="date" name="order_until" value="{{ old('order_until', $controls['order_until']) }}">
            </label>
            <button class="btn" type="submit">Simpan kendali</button>
        </form>
        <p class="muted hint" style="padding:0 16px 8px">
            @if ($controls['order_from'] || $controls['order_until'])
                Hanya order tanggal {{ $controls['order_from'] ? \Illuminate\Support\Carbon::parse($controls['order_from'])->locale('id')->translatedFormat('j F Y') : 'berapa pun' }}
                sampai {{ $controls['order_until'] ? \Illuminate\Support\Carbon::parse($controls['order_until'])->locale('id')->translatedFormat('j F Y') : 'seterusnya' }} yang bisa ikut.
            @else
                Periode program belum diatur: order tanggal berapa pun bisa ikut.
            @endif
            Yang dihitung tanggal order, bukan tanggal paket sampai. Pelanggan yang sudah mulai main tetap bisa melanjutkan sesinya.
        </p>
        <p class="muted hint" style="padding:0 16px 16px">
            Peluang 20% berarti hadiah utama hanya ditaruh di 1 dari 5 papan, dan pelanggan masih harus memilih kotaknya (3 dari 24).
            Hadiah utama tetap dibatasi kuota dan jadwal di tabel bawah.
        </p>
    </div>

    <h2>Hadiah Gatcha</h2>
    <div class="card">
        @if ($catalog->isEmpty())
            <p class="empty">Belum ada hadiah. Tambahkan lewat formulir di bawah. Selama kosong, kado voucher dan merch berisi poin.</p>
        @else
            <table>
                <thead><tr><th>Hadiah</th><th>Terpakai</th></tr></thead>
                <tbody>
                    @foreach ($catalog as $prize)
                        @php($use = $usage[$prize->id])
                        <tr>
                            <td>
                                <p class="name" style="margin:0 0 6px">{{ $prize->name }} <span class="badge">{{ \App\Http\Controllers\PlayController::tierLabel($prize->tier) }}</span></p>
                                @if ($prize->tier === 'poin')
                                    <p class="muted hint">Nilai kado poin diatur di Katalog poin, bagian Aturan poin.</p>
                                @else
                                    <form method="post" action="{{ route('admin.game.catalog.update', $prize->id) }}" class="row-form">
                                        @csrf
                                        <input type="number" name="face_value" value="{{ $prize->face_value }}" min="0" placeholder="Nilai Rp" aria-label="Nilai rupiah {{ $prize->name }}">
                                        @if (isset($use['cap']))
                                            <input type="number" name="cap_units" value="{{ $use['cap']->cap_units }}" min="1" aria-label="Kuota unit {{ $prize->name }}" required>
                                            <input type="date" name="program_start" value="{{ \Illuminate\Support\Carbon::parse($use['cap']->program_start)->toDateString() }}" aria-label="Mulai program {{ $prize->name }}" required>
                                            <input type="date" name="program_end" value="{{ \Illuminate\Support\Carbon::parse($use['cap']->program_end)->toDateString() }}" aria-label="Selesai program {{ $prize->name }}" required>
                                            <label class="check">Mulai keluar <input type="date" name="release_from" value="{{ $use['cap']->release_from ? \Illuminate\Support\Carbon::parse($use['cap']->release_from)->toDateString() : '' }}" aria-label="Mulai keluar {{ $prize->name }}"></label>
                                        @else
                                            <input type="number" name="monthly_stock" value="{{ $prize->monthly_stock }}" min="0" placeholder="Tanpa batas" aria-label="Stok bulanan {{ $prize->name }}">
                                        @endif
                                        <label class="check"><input type="checkbox" name="active" value="1" @checked($prize->active)> Aktif</label>
                                        <button class="btn light" type="submit">Simpan</button>
                                    </form>
                                @endif
                            </td>
                            <td class="muted">
                                @if (isset($use['cap']))
                                    {{ $use['cap']->used_units }} dari {{ $use['cap']->cap_units }} unit
                                    <div class="hint">Mulai keluar {{ $use['release']->locale('id')->translatedFormat('j F Y') }}@unless ($use['cap']->release_from) (otomatis)@endunless</div>
                                    <div class="hint">Boleh keluar sampai hari ini: {{ $use['unlocked'] }}</div>
                                @elseif ($prize->tier === 'poin')
                                    -
                                @else
                                    {{ $use['used'] }}@if ($prize->monthly_stock !== null) dari {{ $prize->monthly_stock }}@endif bulan ini
                                    @if ($prize->monthly_stock !== null && $use['used'] >= $prize->monthly_stock)
                                        <div class="hint">Stok habis, kotaknya jadi poin</div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <p class="muted hint">Stok voucher/merch dihitung per bulan kalender dan kosong berarti tanpa batas. Hadiah utama baru bisa keluar mulai tanggal "Mulai keluar". Kalau kosong, otomatis 14 hari terakhir periode. Setelah itu kuota dibuka bertahap sampai periode selesai: kuota 2 untuk 14 hari berarti unit kedua baru bisa keluar mulai hari ke-8.</p>

    <h2>Tambah hadiah</h2>
    <div class="card" style="padding:16px">
        <form method="post" action="{{ route('admin.game.catalog') }}" class="grid-settings" style="padding:0">
            @csrf
            <label>Nama hadiah <input type="text" name="name" value="{{ old('name') }}" required></label>
            <label>Jenis
                <select name="tier">
                    @foreach (['voucher' => 'Voucher', 'merch' => 'Merch', 'hadiah_besar' => 'Hadiah Utama'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('tier') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Nilai Rp <input type="number" name="face_value" value="{{ old('face_value') }}" min="0"></label>
            <label>Stok bulanan (voucher/merch) <input type="number" name="monthly_stock" value="{{ old('monthly_stock') }}" min="0" placeholder="Kosong = tanpa batas"></label>
            <label>Kuota unit (hadiah utama) <input type="number" name="cap_units" value="{{ old('cap_units') }}" min="1"></label>
            <label>Mulai program <input type="date" name="program_start" value="{{ old('program_start') }}"></label>
            <label>Selesai program <input type="date" name="program_end" value="{{ old('program_end') }}"></label>
            <label>Mulai keluar (hadiah utama) <input type="date" name="release_from" value="{{ old('release_from') }}"></label>
            <button class="btn" type="submit">Tambah hadiah</button>
        </form>
    </div>

    <h2>Bobot tier</h2>
    <div class="card">
        <form method="post" action="{{ route('admin.game.tiers') }}" class="grid-settings">
            @csrf
            <label>Poin <input type="number" name="poin" value="{{ old('poin', $tiers['poin']) }}" min="0" required></label>
            <label>Voucher <input type="number" name="voucher" value="{{ old('voucher', $tiers['voucher']) }}" min="0" required></label>
            <label>Merch <input type="number" name="merch" value="{{ old('merch', $tiers['merch']) }}" min="0" required></label>
            <button class="btn" type="submit">Simpan bobot</button>
        </form>
        <p class="muted hint" style="padding:0 16px 16px">Bobot berlaku per kotak. Pelanggan membuka 3 kotak dan mengambil yang terbaik, jadi peluang nyata per kesempatan lebih besar dari angka ini. Bobot baru berlaku untuk pelanggan yang mulai main setelah disimpan.</p>
    </div>
@endsection
