@extends('layouts.admin')

@section('title', 'Backup setting')

@push('head')
<style>
    .stack { display: grid; gap: 14px; padding: 16px; max-width: 640px; }
    .stack p { margin: 0; font-size: 14px; line-height: 1.55; }
    .stack label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; }
    .stack input[type=file] { font-family: inherit; font-size: 14px; min-height: 44px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; }
    .hint { font-size: 12px; font-weight: 500; color: var(--muted); margin: 0; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .errors { margin: 0 0 16px; padding: 12px 14px 12px 32px; background: #FBE9E6; border: 1px solid #F1C9C3; border-radius: 12px; color: var(--danger); font-size: 13.5px; line-height: 1.5; }
</style>
@endpush

@section('content')
    <h1>Backup setting</h1>
    <p class="lead">Simpan atau pindahkan setting game {{ ucfirst((string) config('app.brand')) }} sebagai satu file. Hanya Super Admin.</p>

    @if (session('import_errors'))
        <ul class="errors" role="alert">
            @foreach (session('import_errors') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <h2>Export</h2>
    <div class="card">
        <div class="stack">
            <p>Isi file: aturan poin, bobot hadiah, kendali Gatcha (periode, peluang hadiah utama), teks halaman resi, daftar hadiah Gatcha dengan kuota hadiah utama, dan katalog tukar poin bulan ini ke depan.</p>
            <p class="hint">Tidak ikut: data pelanggan, saldo poin, klaim, gambar pop up, dan gambar item katalog.</p>
            <div class="actions"><a class="btn" href="{{ route('admin.setting.export') }}">Export setting (.json)</a></div>
        </div>
    </div>

    <h2>Import</h2>
    <div class="card">
        <form method="post" action="{{ route('admin.setting.preview') }}" enctype="multipart/form-data" class="stack">
            @csrf
            <label for="file">File backup setting
                <input type="file" id="file" name="file" accept=".json,application/json" required>
            </label>
            <p class="hint">Setelah diunggah, perubahan ditampilkan dulu. Setting baru berubah setelah tombol Terapkan ditekan. Hadiah atau item yang tidak ada di file dinonaktifkan, tidak dihapus.</p>
            <div class="actions"><button class="btn" type="submit">Lihat perubahan</button></div>
        </form>
    </div>

    <h2>Riwayat import</h2>
    <div class="card">
        @if ($imports->isEmpty())
            <p class="empty">Belum pernah ada import. Setiap import menyimpan backup setting sebelumnya di sini.</p>
        @else
            <table>
                <thead><tr><th>Waktu</th><th>Oleh</th><th>File</th><th>Perubahan</th><th>Setting sebelumnya</th></tr></thead>
                <tbody>
                    @foreach ($imports as $import)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($import->created_at)->translatedFormat('j M Y, H.i') }}</td>
                            <td>{{ $import->user_name ?? 'Akun dihapus' }}</td>
                            <td class="muted">{{ $import->file_name }}</td>
                            <td>{{ $import->change_count }}</td>
                            <td><a class="link" href="{{ route('admin.setting.backup', $import->id) }}">Unduh backup</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <p class="hint" style="margin-top: 10px">Untuk mengembalikan setting lama: unduh backup-nya, lalu import file itu.</p>
@endsection
