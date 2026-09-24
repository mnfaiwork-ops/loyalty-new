@extends('layouts.guest')

@section('title', 'Masuk · '.strtoupper(config('app.brand')))

@section('content')
    <h1>Masuk</h1>
    <p class="sub">Panel CS {{ strtoupper(config('app.brand')) }}</p>

    @if ($errors->any())
        <div class="errors">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('login') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Kata Sandi</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <div class="remember">
            <input id="remember" type="checkbox" name="remember" value="1">
            <label for="remember" style="margin:0;font-weight:500;">Ingat saya</label>
        </div>

        <button class="btn" type="submit">Masuk</button>
    </form>
@endsection
