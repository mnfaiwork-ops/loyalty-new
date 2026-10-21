@extends('layouts.admin')

@section('title', 'Cek perubahan import')

@push('head')
<style>
    .changes { margin: 0; padding: 14px 16px 14px 34px; font-size: 14px; line-height: 1.6; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
</style>
@endpush

@section('content')
    <h1>Cek perubahan import</h1>
    <p class="lead">File <b>{{ $fileName }}</b> untuk {{ ucfirst((string) config('app.brand')) }}. Belum ada yang diubah.</p>

    <div class="card">
        @if ($changes)
            <ol class="changes">
                @foreach ($changes as $change)
                    <li>{{ $change }}</li>
                @endforeach
            </ol>
        @else
            <p class="empty">Isi file sama dengan setting sekarang. Tidak ada yang perlu diubah.</p>
        @endif
    </div>

    <form method="post" action="{{ route('admin.setting.apply') }}" class="actions">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @if ($changes)
            <button class="btn ok" type="submit">Terapkan {{ count($changes) }} perubahan</button>
        @endif
        <a class="btn light" href="{{ route('admin.setting') }}">Batal</a>
    </form>
    @if ($changes)
        <p class="muted" style="margin-top: 10px">Setting sekarang otomatis disimpan sebagai backup sebelum perubahan diterapkan. Pratinjau berlaku 30 menit.</p>
    @endif
@endsection
