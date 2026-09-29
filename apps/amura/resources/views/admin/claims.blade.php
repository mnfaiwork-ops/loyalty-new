@extends('layouts.admin')

@section('title', 'Klaim WhatsApp')

@section('content')
    <h1>Klaim WhatsApp</h1>
    <p class="lead">Pelanggan yang sudah menekan Redeem. Cocokkan nomor pengirim WhatsApp dengan nomor pemenang sebelum konfirmasi.</p>

    <form class="search" method="get" action="{{ route('admin.claims') }}">
        <input type="text" name="q" value="{{ $q }}" placeholder="Tempel kode dari chat WhatsApp" aria-label="Cari kode klaim">
        <button class="btn" type="submit">Cari</button>
        @if ($q !== '')
            <a class="btn light" href="{{ route('admin.claims') }}">Antrian</a>
        @endif
    </form>

    <div class="card">
        @if ($claims->isEmpty())
            <p class="empty">{{ $q !== '' ? 'Kode tidak ditemukan.' : 'Tidak ada klaim yang menunggu. Antrian kosong.' }}</p>
        @else
            <table>
                <thead>
                    <tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Status</th><th>Diajukan</th><th>Batas</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($claims as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: 'Nama belum tersedia' }}<div class="muted">{{ $claim->customer?->phone }}</div></td>
                            <td>{{ $claim->prize_name }}<div class="muted">{{ $claim->source === 'katalog' ? 'Tukar poin' : 'Gatcha' }}</div></td>
                            <td><span class="status {{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span></td>
                            <td class="muted">{{ $claim->redeem_requested_at?->format('d/m H:i') ?? '-' }}</td>
                            <td class="muted">{{ $claim->expires_at->format('d/m/Y') }}</td>
                            <td><a class="link" href="{{ route('admin.claims.show', $claim->claim_code) }}">Periksa</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="pager">{{ $claims->links() }}</div>
@endsection
