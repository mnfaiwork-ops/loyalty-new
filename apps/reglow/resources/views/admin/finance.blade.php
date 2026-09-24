@extends('layouts.admin')

@section('title', 'Persetujuan Finance')

@section('content')
    <h1>Persetujuan Hadiah Besar</h1>

    <h2>Perlu Persetujuan</h2>
    <div class="card">
        @if ($pending->isEmpty())
            <p class="empty">Tidak ada klaim menunggu persetujuan.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Hadiah</th>
                        <th>Brand</th>
                        <th>Info</th>
                        <th>Kedaluwarsa</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pending as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>
                                <span class="badge {{ $claim->gamePrize?->tier }}">{{ \App\Http\Controllers\PlayController::tierLabel($claim->gamePrize?->tier ?? 'poin') }}</span>
                            </td>
                            <td class="muted">{{ $claim->gameSession?->brand ?? '—' }}</td>
                            <td>
                                <div class="muted">Diminta {{ $claim->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                @if ($claim->handled_by)
                                    <div class="muted">Ditangani ID {{ $claim->handled_by }}</div>
                                @endif
                            </td>
                            <td class="muted">{{ $claim->expires_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                <form class="resolve" method="post" action="{{ route('admin.finance.approve', $claim->claim_code) }}">
                                    @csrf
                                    <button class="btn" type="submit">Setujui</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="pager">{{ $pending->links() }}</div>

    <h2>Rekap Selesai</h2>
    <div class="card">
        @if ($done->isEmpty())
            <p class="empty">Belum ada hadiah besar yang diselesaikan.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Hadiah</th>
                        <th>Disetujui Oleh</th>
                        <th>Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($done as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>
                                <span class="badge {{ $claim->gamePrize?->tier }}">{{ \App\Http\Controllers\PlayController::tierLabel($claim->gamePrize?->tier ?? 'hadiah_besar') }}</span>
                            </td>
                            <td class="muted">{{ $claim->finance_by ? 'ID '.$claim->finance_by : '—' }}</td>
                            <td class="muted">{{ $claim->claimed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="pager">{{ $done->links() }}</div>
@endsection
