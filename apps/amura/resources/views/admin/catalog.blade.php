@extends('layouts.admin')

@section('title', 'Katalog poin')

@php
    $monthName = fn (string $value) => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $value)->locale('id')->translatedFormat('F Y');
@endphp

@push('head')
<style>
    /* Satu baris per item, satu tombol simpan untuk semua: admin mengubah beberapa harga/stok sekaligus lalu menyimpan sekali. */
    .period-bar { display: flex; gap: 12px 16px; align-items: center; flex-wrap: wrap; padding: 14px 16px; margin-bottom: 20px; }
    .period-bar form { margin: 0; }
    .period-pick { display: flex; gap: 8px; align-items: center; }
    .period-pick label { font-size: 13px; font-weight: 800; }
    .period-pick input[type=month] { font-family: inherit; font-size: 14px; min-height: 44px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--ink); }
    .period-state { flex: 1; min-width: 200px; font-size: 13px; color: var(--muted); }
    .period-state b { color: var(--ink); }
    .period-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    table.items th, table.items td { padding: 10px 12px; }
    table.items th:first-child { width: 40%; }
    table.items th.num, table.items td.num { text-align: right; }
    table.items th.center, table.items td.center { text-align: center; }
    table.items input[type=text] { width: 100%; min-width: 200px; }
    table.items input[type=number] { width: 104px; text-align: right; -moz-appearance: textfield; }
    table.items input.sort { width: 64px; }
    table.items input::-webkit-outer-spin-button, table.items input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    table.items .is-error { border-color: var(--danger); }
    table.items tr.is-off td { background: #FBF8F3; }
    table.items tr.is-off input[type=text] { color: var(--muted); }
    .used { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .used.is-out { color: var(--danger); font-weight: 800; }
    .toggle { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; font-size: 13px; cursor: pointer; }
    .toggle input { width: 20px; height: 20px; accent-color: var(--ok); }
    .save-bar { position: sticky; bottom: 0; display: flex; gap: 12px; align-items: center; justify-content: flex-end; padding: 12px 16px; border-top: 1px solid var(--line); background: #fff; }
    .save-bar .dirty { font-size: 13px; font-weight: 800; color: var(--ink); }

    .add-grid { display: grid; grid-template-columns: minmax(200px, 2fr) repeat(3, minmax(110px, 1fr)) auto; gap: 12px; align-items: end; padding: 16px; }
    .add-grid label, .grid-settings label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; }
    .grid-settings { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; padding: 16px; }
    .field-hint { font-size: 12px; font-weight: 500; color: var(--muted); }
    @media (max-width: 760px) { .add-grid { grid-template-columns: 1fr 1fr; } .add-grid .wide { grid-column: 1 / -1; } }
</style>
@endpush

@section('content')
    <h1>Katalog poin</h1>
    <p class="lead">
        Hadiah yang bisa ditukar pelanggan dengan poin, diatur per bulan.
        Pelanggan sekarang melihat katalog <b>{{ $active ? $monthName($active->period) : 'belum ada' }}</b>.
    </p>

    <div class="card period-bar">
        <form method="get" action="{{ route('admin.catalog') }}" class="period-pick">
            <label for="period">Bulan</label>
            <input type="month" id="period" name="period" value="{{ $period }}">
            <button class="btn light" type="submit">Buka</button>
        </form>
        <div class="period-state">
            @if ($catalog?->published_at)
                <span class="status selesai">Terbit</span>
                <b>{{ $monthName($period) }}</b> terbit {{ $catalog->published_at->locale('id')->translatedFormat('j F Y') }}.
            @elseif ($catalog)
                <span class="status">Draf</span>
                <b>{{ $monthName($period) }}</b> belum terlihat pelanggan sampai diterbitkan.
            @else
                <span class="status">Kosong</span>
                <b>{{ $monthName($period) }}</b> belum punya item.
            @endif
        </div>
        <div class="period-actions">
            <form method="post" action="{{ route('admin.catalog.copy') }}">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <button class="btn light" type="submit">Salin dari {{ $monthName($previous) }}</button>
            </form>
            @unless ($catalog?->published_at)
                <form method="post" action="{{ route('admin.catalog.publish') }}">
                    @csrf
                    <input type="hidden" name="period" value="{{ $period }}">
                    <button class="btn ok" type="submit" @disabled(! $catalog || $catalog->items->isEmpty())>Terbitkan {{ $monthName($period) }}</button>
                </form>
            @endunless
        </div>
    </div>

    <div class="card">
        @if (! $catalog || $catalog->items->isEmpty())
            <p class="empty">Katalog {{ $monthName($period) }} belum berisi item. Salin dari {{ $monthName($previous) }} atau tambahkan item di bawah.</p>
        @else
            <form method="post" action="{{ route('admin.catalog.items.bulk') }}" id="items-form">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <table class="items">
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col" class="num">Harga (poin)</th>
                            <th scope="col" class="num">Stok bulan ini</th>
                            <th scope="col" class="num">Sudah ditukar</th>
                            <th scope="col" class="center">Tampil</th>
                            <th scope="col" class="num">Urutan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($catalog->items as $item)
                            @php($key = "items.{$item->id}")
                            @php($on = (bool) old("{$key}.active", $item->active))
                            <tr class="{{ $on ? '' : 'is-off' }}">
                                <td>
                                    <input type="text" name="items[{{ $item->id }}][name]" value="{{ old("{$key}.name", $item->name) }}"
                                           aria-label="Nama item" maxlength="120" required class="@error("{$key}.name") is-error @enderror">
                                </td>
                                <td class="num">
                                    <input type="number" name="items[{{ $item->id }}][points_cost]" value="{{ old("{$key}.points_cost", $item->points_cost) }}"
                                           min="1" aria-label="Harga poin {{ $item->name }}" required class="@error("{$key}.points_cost") is-error @enderror">
                                </td>
                                <td class="num">
                                    <input type="number" name="items[{{ $item->id }}][stock]" value="{{ old("{$key}.stock", $item->stock) }}"
                                           min="0" placeholder="Tanpa batas" aria-label="Stok {{ $item->name }}" class="@error("{$key}.stock") is-error @enderror">
                                </td>
                                <td class="num">
                                    <span class="used {{ $item->stock !== null && $item->stock_used >= $item->stock ? 'is-out' : '' }}">
                                        {{ $item->stock_used }}{{ $item->stock !== null ? ' dari '.$item->stock : '' }}
                                    </span>
                                </td>
                                <td class="center">
                                    <input type="hidden" name="items[{{ $item->id }}][active]" value="0">
                                    <label class="toggle">
                                        <input type="checkbox" name="items[{{ $item->id }}][active]" value="1" @checked($on) aria-label="Tampilkan {{ $item->name }}">
                                        <span>{{ $on ? 'Ya' : 'Tidak' }}</span>
                                    </label>
                                </td>
                                <td class="num">
                                    <input type="number" class="sort" name="items[{{ $item->id }}][sort]" value="{{ old("{$key}.sort", $item->sort) }}"
                                           min="0" aria-label="Urutan {{ $item->name }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="save-bar">
                    <span class="dirty" id="items-dirty" hidden>Ada perubahan yang belum disimpan.</span>
                    <button class="btn" type="submit">Simpan perubahan</button>
                </div>
            </form>
        @endif
    </div>
    <p class="muted" style="margin-top:8px">Stok kosong berarti tanpa batas. Urutan kecil tampil lebih dulu di katalog pelanggan.</p>

    <h2>Tambah item ke {{ $monthName($period) }}</h2>
    <div class="card">
        <form method="post" action="{{ route('admin.catalog.items') }}" class="add-grid">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <label class="wide">Nama item <input type="text" name="name" maxlength="120" placeholder="Misalnya: PDRN Pore Cleanser" required></label>
            <label>Harga (poin) <input type="number" name="points_cost" min="1" required></label>
            <label>Stok <input type="number" name="stock" min="0" placeholder="Tanpa batas"></label>
            <label>Urutan <input type="number" name="sort" min="0" placeholder="0"></label>
            <button class="btn" type="submit">Tambah item</button>
        </form>
    </div>

    <h2>Aturan poin</h2>
    <div class="card">
        <form method="post" action="{{ route('admin.catalog.settings') }}" class="grid-settings">
            @csrf
            <label>Poin per Rp1.000 belanja <input type="number" name="per_thousand" min="1" max="1000" value="{{ $settings['per_thousand'] }}" required></label>
            <label>Kado poin paling sedikit (% dari penuh) <input type="number" name="min_percent" min="1" max="100" value="{{ $settings['min_percent'] }}" required></label>
            <label>Poin per kado kalau nominal order tidak ada <input type="number" name="per_gift" min="1" value="{{ $settings['per_gift'] }}" required></label>
            <label>Minimal tukar <input type="number" name="min_redeem" min="1" value="{{ $settings['min_redeem'] }}" required></label>
            <label>Masa berlaku (bulan) <input type="number" name="validity_months" min="1" max="60" value="{{ $settings['validity_months'] }}" required></label>
            <button class="btn" type="submit">Simpan aturan</button>
        </form>
        <p class="muted" style="font-size:12px;margin:0;padding:0 16px 16px">
            Kado poin dihitung dari nilai produk order setelah diskon, tanpa ongkir, lalu dibagi rata ke 2 kesempatan. Contoh: 1 poin per Rp1.000 dan 95%, order produk Rp150.000 memberi kado poin 72 sampai 75 per kesempatan, paling banyak 150 kalau dua-duanya poin.
        </p>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('items-form');
        if (!form) return;
        const note = document.getElementById('items-dirty');
        let dirty = false;
        form.addEventListener('input', event => {
            dirty = true;
            note.hidden = false;
            const box = event.target;
            if (box.type === 'checkbox') {
                box.nextElementSibling.textContent = box.checked ? 'Ya' : 'Tidak';
                box.closest('tr').classList.toggle('is-off', !box.checked);
            }
        });
        form.addEventListener('submit', () => { dirty = false; });
        window.addEventListener('beforeunload', event => { if (dirty) event.preventDefault(); });
    })();
</script>
@endpush
