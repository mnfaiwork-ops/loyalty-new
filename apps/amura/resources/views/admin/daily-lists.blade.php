@extends('layouts.admin')

@section('title', 'Daftar harian BA')

@section('content')
    <h1>Daftar harian BA</h1>
    <p class="lead">Pelanggan {{ ucfirst((string) config('app.brand')) }} yang perlu dihubungi hari ini, menurut data app loyalty. Unduh CSV, lalu cocokkan nomor HP ke BA pemegang customer di FairTrack.</p>

    <div class="card">
        <table>
            <thead><tr><th>Daftar</th><th>Kapan dipakai</th><th>Jumlah</th><th></th></tr></thead>
            <tbody>
                <tr>
                    <td><b>{{ $lists['belum-main'] }}</b></td>
                    <td class="muted">Sudah memasukkan resi, tapi kesempatan buka kado belum dipakai semua.</td>
                    <td>{{ $counts['belum-main'] }}</td>
                    <td><a class="btn light" style="white-space: nowrap" href="{{ route('admin.daily.download', 'belum-main') }}">Unduh CSV</a></td>
                </tr>
                <tr>
                    <td><b>{{ $lists['hadiah-hampir-hangus'] }}</b></td>
                    <td class="muted">Ingatkan redeem lewat WhatsApp sebelum hadiahnya hangus.</td>
                    <td>{{ $counts['hadiah-hampir-hangus'] }}</td>
                    <td><a class="btn light" style="white-space: nowrap" href="{{ route('admin.daily.download', 'hadiah-hampir-hangus') }}">Unduh CSV</a></td>
                </tr>
                <tr>
                    <td><b>{{ $lists['saldo-cukup'] }}</b></td>
                    <td class="muted">Saldo minimal {{ number_format($minRedeem, 0, ',', '.') }} poin. Tawarkan item katalog bulan ini.</td>
                    <td>{{ $counts['saldo-cukup'] }}</td>
                    <td><a class="btn light" style="white-space: nowrap" href="{{ route('admin.daily.download', 'saldo-cukup') }}">Unduh CSV</a></td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="muted" style="margin-top: 10px">Pelanggan yang paketnya sudah diterima tapi belum pernah membuka link tidak ada di app loyalty. Daftar itu diambil dari FairTrack.</p>
@endsection
