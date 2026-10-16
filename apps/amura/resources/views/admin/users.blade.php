@extends('layouts.admin')

@section('title', 'Kelola Pengguna')

@section('content')
    <h1>Kelola Pengguna</h1>

    <div class="card" style="padding:16px;margin-bottom:18px">
        <h2 style="margin:0 0 12px;font-size:16px">Tambah Pengguna</h2>
        <form class="resolve" method="post" action="{{ route('admin.users.store') }}">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Nama" aria-label="Nama" required>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" aria-label="Email" required>
            <input type="password" name="password" placeholder="Kata sandi (min. 12)" aria-label="Kata sandi" required>
            <select name="role" aria-label="Peran">
                @foreach (['cs', 'game_admin', 'finance', 'super_admin'] as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                @endforeach
            </select>
            <select name="brand_scope" aria-label="Brand">
                <option value="{{ config('app.brand') }}" selected>{{ config('app.brand') }}</option>
            </select>
            <button class="btn" type="submit">Tambah</button>
        </form>
        @error('name')<div class="muted">{{ $message }}</div>@enderror
        @error('email')<div class="muted">{{ $message }}</div>@enderror
        @error('password')<div class="muted">{{ $message }}</div>@enderror
        @error('role')<div class="muted">{{ $message }}</div>@enderror
    </div>

    <div class="card">
        @if ($users->isEmpty())
            <p class="empty">Belum ada pengguna.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Brand</th>
                        <th>Dibuat</th>
                        <th>Ubah Peran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge">{{ $user->role }}</span></td>
                            <td>{{ $user->brand_scope }}</td>
                            <td class="muted">{{ $user->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                <form class="resolve" method="post" action="{{ route('admin.users.role', $user->id) }}">
                                    @csrf
                                    <select name="role" aria-label="Peran pengguna">
                                        @foreach (['cs', 'game_admin', 'finance', 'super_admin'] as $role)
                                            <option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
                                        @endforeach
                                    </select>
                                    <select name="brand_scope" aria-label="Brand pengguna">
                                        <option value="{{ config('app.brand') }}" selected>{{ config('app.brand') }}</option>
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
        {{ $users->links() }}
    </div>
@endsection
