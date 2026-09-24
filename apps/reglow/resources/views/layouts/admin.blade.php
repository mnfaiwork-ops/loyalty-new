<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel Admin')</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1E6B63;
            --secondary: #27897E;
            --deep: #0E4A43;
            --ink: #14332F;
            --muted: #5C7873;
            --line: #D7E5E2;
            --bg: #F2F8F7;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            color: var(--ink);
            background: var(--bg);
            min-height: 100vh;
        }
        header {
            background: var(--deep);
            color: #fff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        header .brand { font-family: 'DM Serif Display', serif; font-size: 20px; letter-spacing: .5px; }
        header nav a { color: #CDE6E2; text-decoration: none; font-size: 14px; font-weight: 600; }
        header nav a:hover { color: #fff; }
        main { max-width: 960px; margin: 0 auto; padding: 22px 16px 48px; }
        h1 { font-family: 'DM Serif Display', serif; font-weight: 400; font-size: 24px; margin: 0 0 16px; color: var(--deep); }
        .flash { background: #E7F3F1; border: 1px solid var(--line); color: var(--primary); border-radius: 10px; padding: 10px 14px; font-size: 14px; margin-bottom: 16px; }
        form.search { display: flex; gap: 8px; margin-bottom: 18px; }
        input[type=text], select {
            font-family: inherit;
            font-size: 14px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
        }
        form.search input[type=text] { flex: 1; }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            color: #fff;
            background: var(--primary);
            cursor: pointer;
        }
        .btn:hover { background: var(--deep); }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; box-shadow: 0 8px 24px rgba(14, 74, 67, .05); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { background: #F7FBFA; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        tr:last-child td { border-bottom: none; }
        code { font-size: 13px; font-weight: 700; color: var(--primary); background: #E7F3F1; border-radius: 6px; padding: 3px 7px; }
        .badge { display: inline-block; font-size: 12px; font-weight: 700; border-radius: 999px; padding: 3px 10px; background: #E7F3F1; color: var(--primary); }
        .badge.hadiah_besar { background: #FBEED2; color: #8A6410; }
        .muted { color: var(--muted); font-size: 12.5px; }
        form.resolve { display: flex; gap: 6px; align-items: center; }
        form.resolve select { padding: 7px 9px; }
        form.resolve .btn { padding: 7px 12px; font-size: 13px; }
        .pager { margin-top: 16px; font-size: 13px; }
        .pager a { color: var(--primary); font-weight: 600; text-decoration: none; margin-right: 10px; }
        .empty { padding: 28px 16px; text-align: center; color: var(--muted); }
    </style>
</head>
<body>
    <header>
        <span class="brand">{{ strtoupper(config('app.brand')) }} · Panel CS</span>
        <nav>
            @if (auth()->user()?->role === 'game_admin' || auth()->user()?->role === 'super_admin')
                <a href="{{ route('admin.game') }}">Game</a>
            @endif
            <a href="{{ route('admin.claims') }}">Klaim</a>
            @if (auth()->user()?->role === 'finance' || auth()->user()?->role === 'super_admin')
                <a href="{{ route('admin.finance') }}">Finance</a>
            @endif
            @if (auth()->user()?->role === 'super_admin')
                <a href="{{ route('admin.users') }}">Pengguna</a>
            @endif
        </nav>
    </header>
    <main>
        @if (session('ok'))
            <div class="flash">{{ session('ok') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
