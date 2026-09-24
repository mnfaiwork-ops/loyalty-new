@extends('layouts.admin')

@section('title', 'Daftar Klaim')

@section('content')
    <h1>Klaim Hadiah</h1>

    <form class="search" method="get" action="{{ route('admin.claims') }}">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari kode klaim…" aria-label="Cari kode klaim">
        <button class="btn" type="submit">Cari</button>
    </form>

    <div class="card">
        @if ($claims->isEmpty())
            <p class="empty">Belum ada klaim.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Hadiah</th>
                        <th>Status</th>
                        <th>Kedaluwarsa</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($claims as $claim)
                        <tr>
                            <td><code>{{ $claim->claim_code }}</code></td>
                            <td>
                                <span class="badge {{ $claim->gamePrize?->tier }}">{{ $claim->gamePrize?->tier ?? '—' }}</span>
                            </td>
                            <td>
                                {{ $claim->status }}
                                @if ($claim->claimed_at)
                                    <div class="muted">Diproses {{ $claim->claimed_at->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>
                            <td class="muted">{{ $claim->expires_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                <form class="resolve" method="post" action="{{ route('admin.claims.resolve', $claim->claim_code) }}">
                                    @csrf
                                    <select name="status" aria-label="Status klaim">
                                        @php($needsFinance = $claim->gamePrize?->tier === 'hadiah_besar' || $claim->status === 'perlu_finance')
                                        @if ($needsFinance)
                                            <option value="" disabled selected>— menunggu Finance —</option>
                                            <option value="hangus">hangus</option>
                                        @else
                                            @foreach (['diproses', 'selesai', 'hangus'] as $status)
                                                <option value="{{ $status }}" @selected($claim->status === $status)>{{ $status }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <button class="btn" type="submit">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="pager">
        {{ $claims->links() }}
    </div>
@endsection
