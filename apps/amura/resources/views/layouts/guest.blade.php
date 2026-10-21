<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk')</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1E6B63;
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .card {
            width: 100%;
            max-width: 380px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(14, 74, 67, .08);
            padding: 28px 22px;
        }
        .brand-logo { display: block; width: auto; height: calc(28px * var(--ls, 1)); margin-bottom: 18px; }
        h1 { font-family: 'DM Serif Display', serif; font-weight: 400; font-size: 24px; margin: 0 0 4px; color: var(--deep); }
        .sub { color: var(--muted); font-size: 14px; margin: 0 0 20px; }
        label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 6px; }
        input[type=email], input[type=password] {
            width: 100%;
            font-family: inherit;
            font-size: 14px;
            padding: 11px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
        }
        .remember { display: flex; align-items: center; gap: 8px; margin: 14px 0 20px; font-size: 14px; }
        .btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            color: #fff;
            background: var(--primary);
            cursor: pointer;
        }
        .btn:hover { background: var(--deep); }
        .errors { background: #FDECEC; border: 1px solid #F3C6C6; color: #A32B2B; border-radius: 10px; padding: 10px 14px; font-size: 14px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <main class="card">
        @include('partials.logo', ['theme' => config('play.themes.'.config('app.brand'))])
        @yield('content')
    </main>
</body>
</html>
