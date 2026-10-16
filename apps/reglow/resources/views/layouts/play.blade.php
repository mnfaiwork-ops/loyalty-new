<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="@yield('theme-color', '#FBF7F1')">
    <title>@yield('title', $theme['name'])</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/play.css') }}?v={{ filemtime(public_path('css/play.css')) }}">
    <style>
        :root {
            --p: {{ $theme['p'] }}; --d: {{ $theme['d'] }}; --t: {{ $theme['t'] }};
            --b0: {{ $theme['b0'] }}; --b1: {{ $theme['b1'] }}; --b2: {{ $theme['b2'] }};
            --st1: {{ $theme['st1'] }}; --st2: {{ $theme['st2'] }}; --glow-item: {{ $theme['glow'] }};
        }
    </style>
</head>
<body class="@yield('body-class')">
    <main class="app">
        @yield('content')
    </main>
    @include('customer.partials.popup')
    @stack('scripts')
</body>
</html>
