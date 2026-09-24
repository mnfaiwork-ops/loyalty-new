@extends('layouts.admin')

@section('title', 'Panel Game')

@section('content')
    <h1>Katalog Hadiah</h1>

    <div class="card">
        @if ($catalog->isEmpty())
            <p class="empty">Belum ada hadiah di katalog.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Tier</th>
                        <th>Nilai</th>
                        <th>Kuota</th>
                        <th>Cap</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($catalog as $prize)
                        <tr>
                            <td>{{ $prize->name }}</td>
                            <td><span class="badge {{ $prize->tier }}">{{ $prize->tier }}</span></td>
                            <td class="muted">
                                @if ($prize->point_value)
                                    {{ number_format($prize->point_value, 0, ',', '.') }} poin
                                @endif
                                @if ($prize->face_value)
                                    Rp {{ number_format($prize->face_value, 0, ',', '.') }}
                                @endif
                                @if (! $prize->point_value && ! $prize->face_value)
                                    —
                                @endif
                            </td>
                            <td class="muted">
                                {{ $prize->quota_used }} / {{ $prize->quota_total ?? '∞' }}
                                @php($sisa = $remaining[$prize->id] ?? null)
                                @if ($sisa !== null)
                                    <div>Sisa {{ $sisa }} dari {{ $prize->quota_total ?? $sisa }}</div>
                                @endif
                            </td>
                            <td>{{ $prize->is_capped ? 'Ya' : 'Tidak' }}</td>
                            <td>{{ $prize->active ? 'Aktif' : 'Nonaktif' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="pager">{{ $catalog->links() }}</div>

    <h1 style="margin-top:32px">Tambah Hadiah</h1>
    <div class="card" style="padding:18px">
        <form method="post" action="{{ route('admin.game.catalog') }}">
            @csrf
            <p><input type="text" name="name" value="{{ old('name') }}" placeholder="Nama hadiah" required style="width:100%"></p>
            @error('name') <p class="muted">{{ $message }}</p> @enderror

            <p>
                <select name="tier" style="width:100%">
                    @foreach (['poin' => 'Poin', 'voucher' => 'Voucher', 'merch' => 'Merch', 'hadiah_besar' => 'Hadiah Besar'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('tier') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </p>
            @error('tier') <p class="muted">{{ $message }}</p> @enderror

            <p><input type="number" name="point_value" value="{{ old('point_value') }}" placeholder="Nilai poin (opsional)" min="0" style="width:100%"></p>
            <p><input type="number" name="face_value" value="{{ old('face_value') }}" placeholder="Nilai rupiah (opsional)" min="0" style="width:100%"></p>
            <p><input type="number" name="quota_total" value="{{ old('quota_total') }}" placeholder="Total kuota (opsional)" min="0" style="width:100%"></p>

            <p>
                <label class="muted"><input type="checkbox" name="is_capped" value="1" @checked(old('is_capped'))> Batasi kuota (hadiah besar)</label>
            </p>

            <p><input type="number" name="cap_units" value="{{ old('cap_units') }}" placeholder="Jumlah unit cap" min="1" style="width:100%"></p>
            @error('cap_units') <p class="muted">{{ $message }}</p> @enderror

            <p><input type="date" name="program_start" value="{{ old('program_start') }}" style="width:100%"></p>
            <p><input type="date" name="program_end" value="{{ old('program_end') }}" style="width:100%"></p>

            <button class="btn" type="submit">Tambah Hadiah</button>
        </form>
    </div>

    <h1 style="margin-top:32px">Bobot Tier</h1>
    <div class="card" style="padding:18px">
        <form method="post" action="{{ route('admin.game.tiers') }}">
            @csrf
            <p><label class="muted">Poin</label><input type="number" name="poin" value="{{ old('poin', $tiers['poin']) }}" min="0" required style="width:100%"></p>
            <p><label class="muted">Voucher</label><input type="number" name="voucher" value="{{ old('voucher', $tiers['voucher']) }}" min="0" required style="width:100%"></p>
            <p><label class="muted">Merch</label><input type="number" name="merch" value="{{ old('merch', $tiers['merch']) }}" min="0" required style="width:100%"></p>
            <button class="btn" type="submit">Simpan Bobot</button>
        </form>
    </div>
@endsection
