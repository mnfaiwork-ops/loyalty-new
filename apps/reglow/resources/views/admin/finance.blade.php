@extends('layouts.admin')

@section('title', 'Finance')

@section('content')
    <h1>Persetujuan hadiah utama</h1>
    <p class="lead">Nomor pemenang sudah dicocokkan CS. Setujui untuk meneruskan ke Pengiriman.</p>

    <div class="card">
        @if ($pending->isEmpty())
            <p class="empty">Tidak ada hadiah utama yang menunggu persetujuan.</p>
        @else
            <table>
                <thead><tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Batas</th><th></th></tr></thead>
                <tbody>
                    @foreach ($pending as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: 'Nama belum tersedia' }}<div class="muted">{{ $claim->customer?->phone }}</div></td>
                            <td>{{ $claim->prize_name }}</td>
                            <td class="muted">{{ $claim->expires_at->format('d/m/Y') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.finance.approve', $claim->claim_code) }}">
                                    @csrf
                                    <button class="btn ok" type="submit">Setujui</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="pager">{{ $pending->links() }}</div>

    <h2>Sudah disetujui</h2>
    <div class="card">
        @if ($done->isEmpty())
            <p class="empty">Belum ada persetujuan.</p>
        @else
            <table>
                <thead><tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($done as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: '-' }}</td>
                            <td>{{ $claim->prize_name }}</td>
                            <td><span class="status {{ $claim->status }}">{{ \LoyaltyNew\Core\Prizes\ClaimFlow::label($claim->status) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="pager">{{ $done->links() }}</div>
@endsection
