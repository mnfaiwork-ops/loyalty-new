@extends('layouts.admin')

@section('title', 'Pengiriman')

@section('content')
    <h1>Pengiriman</h1>
    <p class="lead">Hadiah yang sudah dikonfirmasi. Isi resi saat dikirim, lalu tandai diterima.</p>

    <h2>Dikemas ({{ $packing->count() }})</h2>
    <div class="card">
        @if ($packing->isEmpty())
            <p class="empty">Tidak ada hadiah yang perlu dikirim.</p>
        @else
            <table>
                <thead><tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Resi hadiah</th></tr></thead>
                <tbody>
                    @foreach ($packing as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: '-' }}<div class="muted">{{ $claim->customer?->phone }}</div></td>
                            <td>{{ $claim->prize_name }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.shipping.ship', $claim->claim_code) }}" style="display:flex;gap:6px;flex-wrap:wrap">
                                    @csrf
                                    <input type="text" name="shipping_awb" aria-label="Resi untuk {{ $claim->claim_code }}" placeholder="Nomor resi" required>
                                    <button class="btn" type="submit">Kirim</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Dalam pengiriman ({{ $shipped->count() }})</h2>
    <div class="card">
        @if ($shipped->isEmpty())
            <p class="empty">Belum ada hadiah dalam pengiriman.</p>
        @else
            <table>
                <thead><tr><th>Kode</th><th>Pemenang</th><th>Hadiah</th><th>Resi</th><th></th></tr></thead>
                <tbody>
                    @foreach ($shipped as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>{{ $claim->customer?->name ?: '-' }}<div class="muted">{{ $claim->customer?->phone }}</div></td>
                            <td>{{ $claim->prize_name }}</td>
                            <td>{{ $claim->shipping_awb }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.shipping.deliver', $claim->claim_code) }}">
                                    @csrf
                                    <button class="btn ok" type="submit">Tandai diterima</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
