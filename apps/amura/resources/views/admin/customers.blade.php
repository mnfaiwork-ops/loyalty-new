@extends('layouts.admin')

@section('title', 'Pelanggan & PIN')

@php
    $display = fn ($phone) => \App\Support\PhoneInput::display((string) $phone);
@endphp

@push('head')
<style>
    /* Desain 3l. Pola sama dengan halaman CS lain: judul, tab brand, kolom cari, tabel. */
    .pin-page { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; align-items: start; }
    .pin-page.has-panel { grid-template-columns: minmax(0, 1fr) 340px; }
    /* Tanpa ini kolom grid melebar mengikuti tabel dan halaman bisa digeser ke samping di HP. */
    .pin-page > * { min-width: 0; }
    .pin-table .who { min-width: 160px; }
    .pin-table .who .muted, .pin-table td:nth-child(2), .pin-table .btn { white-space: nowrap; }
    .pin-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
    .pin-head .lead { max-width: 420px; margin: 0; }
    .brand-tabs { flex: none; display: flex; gap: 2px; padding: 3px; border: 1px solid var(--line); border-radius: 10px; background: #fff; }
    .brand-tabs a, .brand-tabs span { min-height: 38px; display: grid; place-items: center; padding: 0 12px; border-radius: 8px; font-size: 13px; font-weight: 800; color: var(--ink); text-decoration: none; }
    .brand-tabs [aria-current] { background: var(--ink); color: #fff; }
    .phone-search { display: flex; align-items: center; gap: 12px; padding: 6px 6px 6px 14px; border: 2px solid var(--ink); border-radius: 14px; background: #fff; margin-bottom: 14px; }
    .phone-search label { flex: none; font-size: 12px; font-weight: 800; color: var(--muted); }
    .phone-search input[type=text] { flex: 1; min-width: 0; border: 0; padding: 0; font-size: 18px; font-weight: 800; letter-spacing: .02em; }
    .phone-search input[type=text]:focus { outline: none; }
    .phone-search input::placeholder { font-weight: 500; color: #8C7E72; }
    .phone-search:focus-within { box-shadow: 0 0 0 3px #E7DDD0; }
    .count { margin: 0 0 10px; font-size: 13px; color: var(--muted); }
    .pin-table tr.is-reset td:first-child { box-shadow: inset 3px 0 0 var(--ink); }
    .pin-table .who b { display: block; font-size: 14px; }
    .chip { display: inline-block; font-size: 11.5px; font-weight: 800; border-radius: 999px; padding: 4px 10px; white-space: nowrap; }
    .chip.none { background: #F0E8DD; color: var(--muted); }
    .chip.temp { background: #FBF1DE; color: var(--gold); }
    .chip.active { background: #E2F1E7; color: #1C5233; }
    .legend { margin-top: 14px; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; }
    .legend h2 { margin: 0 0 10px; font-size: 11px; font-weight: 800; letter-spacing: .06em; color: var(--muted); }
    .legend dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 10px 12px; align-items: center; font-size: 13px; color: var(--muted); }
    .legend dd { margin: 0; line-height: 1.45; }
    .hint-card { border: 1.5px dashed var(--line); border-radius: 14px; padding: 48px 20px; text-align: center; }
    .hint-card b { display: block; font-size: 16px; }
    .hint-card p { margin: 6px auto 0; max-width: 340px; font-size: 13px; color: var(--muted); }
    .miss { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
    .miss b { display: block; font-size: 16px; margin-bottom: 6px; }
    .miss ul { margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.6; color: var(--muted); }
    /* Panel kanan setelah reset: hasil berhasil yang harus segera dikirim, jadi hijau. */
    .temp-panel { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 14px; position: sticky; top: 16px; }
    .temp-panel .who { font-size: 11px; font-weight: 800; letter-spacing: .06em; color: var(--muted); }
    .temp-card { background: #E1F0E6; border: 1px solid #C7E3D1; border-radius: 14px; padding: 14px; color: #1C5233; }
    .temp-card .for { font-size: 13px; font-weight: 800; }
    /* 64 px DM Serif: mudah dieja CS kalau perlu lewat telepon. */
    .temp-card .pin { font-family: 'DM Serif Display', serif; font-size: 64px; line-height: 1; letter-spacing: .18em; color: var(--ink); margin: 10px 0 12px; }
    .temp-card .once { display: flex; gap: 6px; font-size: 12.5px; line-height: 1.45; color: var(--ink); margin-bottom: 12px; }
    .temp-card .btn { width: 100%; min-height: 48px; }
    .msg-label { font-size: 11px; font-weight: 800; letter-spacing: .06em; color: var(--muted); margin-bottom: 6px; }
    .msg { background: #F5EFE6; border-radius: 12px; padding: 12px 14px; font-size: 13px; line-height: 1.55; }
    .logged { font-size: 12px; line-height: 1.5; color: var(--muted); }
    dialog.confirm { border: 0; border-radius: 16px; padding: 18px; width: min(370px, calc(100vw - 32px)); color: var(--ink); }
    dialog.confirm::backdrop { background: rgba(43, 34, 29, .45); }
    dialog.confirm h2 { margin: 0 0 6px; font-size: 16px; }
    dialog.confirm p { margin: 0 0 16px; font-size: 13px; line-height: 1.5; color: var(--muted); }
    dialog.confirm .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    @media (max-width: 1100px) {
        .pin-page.has-panel { grid-template-columns: minmax(0, 1fr); }
        .temp-panel { order: -1; position: static; }
    }
    @media (max-width: 600px) {
        .pin-head { flex-direction: column; }
        .phone-search { flex-wrap: wrap; }
        .phone-search label { width: 100%; }
    }
</style>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="pin-page {{ $temporary ? 'has-panel' : '' }}">
    <div>
        <div class="pin-head">
            <div>
                <h1>Pelanggan &amp; PIN</h1>
                <p class="lead">Reset hanya kalau pelanggan chat dari nomor akunnya sendiri. Setiap reset tercatat di log.</p>
            </div>
            @if ($brandTabs->count() > 1)
                <nav class="brand-tabs" aria-label="Brand">
                    @foreach ($brandTabs as $key => $tab)
                        @if ($key === $brand)
                            <span aria-current="page">{{ $tab['name'] }}</span>
                        @else
                            <a href="{{ $tab['url'] }}">{{ $tab['name'] }}</a>
                        @endif
                    @endforeach
                </nav>
            @endif
        </div>

        <form class="phone-search" method="get" action="{{ route('admin.customers') }}" role="search">
            <label for="q">Nomor HP</label>
            <input type="text" id="q" name="q" value="{{ $q }}" inputmode="tel" autocomplete="off" placeholder="Contoh: 0812 3456 7890" @if ($q === '') autofocus @endif>
            <button class="btn" type="submit">Cari</button>
        </form>

        @if ($q === '')
            <div class="hint-card">
                <b>Ketik nomor HP dari chat WhatsApp pelanggan.</b>
                <p>Salin dari profil chat, bukan dari isi pesan, supaya yang direset benar-benar pemilik nomor.</p>
            </div>
        @elseif ($customers->isEmpty())
            <div class="miss" role="status">
                <b>Nomor {{ $display($q) }} tidak ditemukan di {{ $brandName }}</b>
                <ul>
                    <li>Cek lagi angka dari profil chat.</li>
                    @if ($brandTabs->count() > 1)
                        <li>Mungkin pelanggan brand lain: ganti tab brand di kanan atas.</li>
                    @else
                        <li>Mungkin pelanggan brand lain: cari di panel admin brand itu.</li>
                    @endif
                    <li>Kalau belum pernah main, pelanggan perlu masuk dengan resi dulu. Tidak ada PIN untuk direset.</li>
                </ul>
            </div>
        @else
            <p class="count">{{ $customers->count() }} pelanggan {{ $brandName }} dengan nomor ini</p>
            <div class="card">
                <table class="pin-table">
                    <thead>
                        <tr><th>Pelanggan</th><th>Status PIN</th><th>Reset terakhir</th><th aria-label="Aksi"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr @class(['is-reset' => ($temporary['customer_id'] ?? null) === $customer->id])>
                                <td class="who"><b>{{ $customer->name ?: 'Nama belum tersedia' }}</b><span class="muted">{{ $display($customer->phone) }}</span></td>
                                <td>
                                    @if ($customer->pin_hash === null)
                                        <span class="chip none">Belum punya PIN</span>
                                    @elseif ($customer->pin_must_change)
                                        <span class="chip temp">PIN sementara, belum diganti</span>
                                    @else
                                        <span class="chip active">Aktif sejak {{ $customer->pin_set_at?->format('d/m/Y') }}</span>
                                    @endif
                                </td>
                                <td class="muted">
                                    @if ($customer->pin_reset_at)
                                        {{ $customer->pin_reset_at->format('d/m/Y H.i') }} · {{ $customer->pinResetBy?->name }}
                                    @else
                                        Belum pernah
                                    @endif
                                </td>
                                <td>
                                    {{-- Reset nonaktif untuk "Belum punya PIN": panduannya ada di kartu tiga status. --}}
                                    <form method="POST" action="{{ route('admin.customers.reset-pin', $customer->id) }}" data-reset data-phone="{{ $display($customer->phone) }}">
                                        @csrf
                                        <button class="btn light" type="submit" @disabled($customer->pin_hash === null)>Reset PIN</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <section class="legend" aria-labelledby="legend-title">
                <h2 id="legend-title">TIGA STATUS PIN</h2>
                <dl>
                    <dt><span class="chip none">Belum punya PIN</span></dt>
                    <dd>Belum pernah buat PIN. Tombol Reset PIN nonaktif. Arahkan pelanggan masuk dengan resi.</dd>
                    <dt><span class="chip temp">PIN sementara, belum diganti</span></dt>
                    <dd>Sudah direset, pelanggan belum masuk dan membuat PIN baru.</dd>
                    <dt><span class="chip active">Aktif sejak …</span></dt>
                    <dd>PIN pilihan pelanggan sendiri.</dd>
                </dl>
            </section>
        @endif
    </div>

    @if ($temporary)
        <aside class="temp-panel" aria-labelledby="temp-title">
            <div class="who">{{ mb_strtoupper(($temporary['name'] ?? null) ?: $display($temporary['phone'])) }} · {{ mb_strtoupper($brandName) }}</div>
            <div class="temp-card">
                <div class="for" id="temp-title">PIN sementara untuk {{ $display($temporary['phone']) }}</div>
                <div class="pin">{{ $temporary['pin'] }}</div>
                <div class="once"><b aria-hidden="true">!</b><span><b>Hanya tampil sekali.</b> Setelah panel ini ditutup, PIN tidak bisa dilihat lagi.</span></div>
                <a class="btn ok" href="{{ $temporary['wa'] }}" target="_blank" rel="noopener">Kirim ke WhatsApp {{ $display($temporary['phone']) }}</a>
            </div>
            @isset($temporary['message'])
                <div>
                    <div class="msg-label">PESAN YANG TERKIRIM</div>
                    <div class="msg">{{ $temporary['message'] }}</div>
                </div>
            @endisset
            @isset($temporary['by'])
                <p class="logged">Tercatat di log: {{ $temporary['by'] }}, {{ $temporary['at'] }}. HP yang diingat dengan PIN lama sudah dikeluarkan.</p>
            @endisset
            <a class="btn light" href="{{ route('admin.customers', ['q' => $temporary['phone']]) }}">Tutup</a>
        </aside>
    @endif
</div>

<dialog class="confirm" id="resetDialog" aria-labelledby="resetTitle">
    <form method="POST" id="resetForm">
        @csrf
        <h2 id="resetTitle">Buat PIN sementara?</h2>
        <p>PIN lamanya tidak bisa dipakai lagi. HP yang diingat dengan PIN lama akan keluar.</p>
        <div class="actions">
            <button class="btn light" type="button" value="cancel" data-close>Batal</button>
            <button class="btn" type="submit">Ya, reset PIN</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    // Konfirmasi reset: dialog gelap seperti "Buka kunci" (3j), bukan confirm() bawaan browser.
    (() => {
        const dialog = document.getElementById('resetDialog');
        const form = document.getElementById('resetForm');
        document.querySelectorAll('form[data-reset]').forEach(row => row.addEventListener('submit', event => {
            if (!dialog.showModal) return;
            event.preventDefault();
            form.action = row.action;
            document.getElementById('resetTitle').textContent = `Buat PIN sementara untuk ${row.dataset.phone}?`;
            dialog.showModal();
        }));
        dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
        form.addEventListener('submit', () => form.querySelector('[type=submit]').disabled = true);
    })();
</script>
@endpush
