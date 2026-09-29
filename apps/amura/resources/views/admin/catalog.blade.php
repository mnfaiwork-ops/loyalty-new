@extends('layouts.admin')

@section('title', 'Katalog poin')

@push('head')
<style>
    .toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
    .row-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .row-form input[name=name] { min-width: 180px; }
    .row-form input[type=number] { width: 110px; }
    .grid-settings { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; padding: 16px; }
    .grid-settings label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; }
    .check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; min-height: 44px; }
</style>
@endpush

@section('content')
    <h1>Katalog poin</h1>
    <p class="lead">
        Pelanggan melihat katalog terbit terbaru yang bulannya sudah berjalan.
        Aktif sekarang: <b>{{ $active?->period ?? 'belum ada' }}</b>.
    </p>

    <div class="toolbar">
        <form method="get" action="{{ route('admin.catalog') }}" class="row-form">
            <label for="period" class="muted">Bulan</label>
            <input type="month" id="period" name="period" value="{{ $period }}">
            <button class="btn light" type="submit">Buka</button>
        </form>
        <form method="post" action="{{ route('admin.catalog.copy') }}">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <button class="btn light" type="submit">Salin dari {{ $previous }}</button>
        </form>
        <form method="post" action="{{ route('admin.catalog.publish') }}">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <button class="btn ok" type="submit" @disabled($catalog?->published_at)>{{ $catalog?->published_at ? 'Sudah terbit' : 'Terbitkan '.$period }}</button>
        </form>
    </div>

    <div class="card">
        @if (! $catalog || $catalog->items->isEmpty())
            <p class="empty">Katalog {{ $period }} belum berisi item. Tambahkan item atau salin dari bulan lalu.</p>
        @else
            <table>
                <thead><tr><th>Item</th><th>Dipakai</th></tr></thead>
                <tbody>
                    @foreach ($catalog->items as $item)
                        <tr>
                            <td>
                                <form method="post" action="{{ route('admin.catalog.items.update', $item->id) }}" class="row-form">
                                    @csrf
                                    <input type="text" name="name" value="{{ $item->name }}" aria-label="Nama item" required>
                                    <input type="number" name="points_cost" value="{{ $item->points_cost }}" min="1" aria-label="Harga poin" required>
                                    <input type="number" name="stock" value="{{ $item->stock }}" min="0" placeholder="Tanpa batas" aria-label="Stok">
                                    <input type="number" name="sort" value="{{ $item->sort }}" min="0" aria-label="Urutan">
                                    <label class="check"><input type="checkbox" name="active" value="1" @checked($item->active)> Aktif</label>
                                    <button class="btn light" type="submit">Simpan</button>
                                </form>
                            </td>
                            <td class="muted">{{ $item->stock_used }}@if ($item->stock !== null) / {{ $item->stock }}@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Tambah item</h2>
    <div class="card" style="padding:16px">
        <form method="post" action="{{ route('admin.catalog.items') }}" class="row-form">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="text" name="name" placeholder="Nama hadiah" aria-label="Nama hadiah" required>
            <input type="number" name="points_cost" placeholder="Poin" min="1" aria-label="Harga poin" required>
            <input type="number" name="stock" placeholder="Stok (kosong = tanpa batas)" min="0" aria-label="Stok">
            <input type="number" name="sort" placeholder="Urutan" min="0" aria-label="Urutan">
            <button class="btn" type="submit">Tambah</button>
        </form>
    </div>

    <h2>Aturan poin</h2>
    <div class="card">
        <form method="post" action="{{ route('admin.catalog.settings') }}" class="grid-settings">
            @csrf
            <label>Poin per kado poin <input type="number" name="per_gift" min="1" value="{{ $settings['per_gift'] }}" required></label>
            <label>Minimal tukar <input type="number" name="min_redeem" min="1" value="{{ $settings['min_redeem'] }}" required></label>
            <label>Masa berlaku (bulan) <input type="number" name="validity_months" min="1" max="60" value="{{ $settings['validity_months'] }}" required></label>
            <button class="btn" type="submit">Simpan aturan</button>
        </form>
    </div>
@endsection
