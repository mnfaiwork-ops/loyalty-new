<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Amura')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1E6B63;
            --secondary: #27897E;
            --secondary-light: #4DB5A8;
            --deep: #0E4A43;
            --gold: linear-gradient(180deg, #F5DA9A, #C99A3A);
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
            align-items: flex-start;
            justify-content: center;
            padding: 24px 16px;
        }
        h1, h2, .serif { font-family: 'DM Serif Display', serif; font-weight: 400; margin: 0; }
        .card {
            width: 100%;
            max-width: 390px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 28px 22px;
            box-shadow: 0 12px 32px rgba(14, 74, 67, .08);
        }
        .brand { font-family: 'DM Serif Display', serif; color: var(--primary); font-size: 26px; letter-spacing: .5px; }
        .headline { font-size: 24px; margin-top: 6px; color: var(--deep); }
        .sub { color: var(--muted); font-size: 14px; margin: 8px 0 22px; line-height: 1.5; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--deep); }
        input[type=text] {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            background: #FBFDFD;
            margin-bottom: 16px;
        }
        input[type=text]:focus { outline: 2px solid var(--secondary-light); border-color: var(--secondary); }
        .error { color: #B4372C; font-size: 12.5px; margin: -10px 0 14px; }
        .btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            color: #fff;
            background: var(--primary);
        }
        .btn:active { background: var(--deep); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .note { margin-top: 18px; font-size: 12.5px; color: var(--muted); text-align: center; line-height: 1.5; }
        .chance { display: inline-block; margin: 4px 0 18px; font-size: 13px; font-weight: 600; color: var(--primary); background: #E7F3F1; border-radius: 999px; padding: 6px 14px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
        .slot {
            aspect-ratio: 1;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            background: #FBFDFD;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            color: var(--secondary);
        }
        .slot.selected { background: var(--secondary); border-color: var(--secondary); color: #fff; }
        .slot.opened { background: var(--gold); border-color: #C99A3A; color: var(--deep); cursor: default; }
        .slot.chosen { box-shadow: 0 0 0 3px var(--secondary); }
        .reveal { margin-top: 20px; border-top: 1px solid var(--line); padding-top: 18px; }
        .reveal-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 14px 0; }
        .reveal-card {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            border: 1.5px solid var(--line); border-radius: 12px; padding: 14px 6px;
            background: #FBFDFD; font-family: inherit; font-size: 13px; font-weight: 700; color: var(--deep);
        }
        .reveal-card.chosen { background: var(--gold); border-color: #C99A3A; }
        .reveal-card:disabled { opacity: .9; }
        .reveal-slot { font-size: 12px; color: var(--muted); font-weight: 600; }
        .reveal-tier { text-align: center; line-height: 1.3; }
        .result { margin-top: 20px; border-top: 1px solid var(--line); padding-top: 18px; text-align: center; }
        .claim-code { font-family: 'DM Serif Display', serif; font-size: 30px; letter-spacing: 4px; color: var(--deep); background: var(--gold); border-radius: 12px; padding: 14px 8px; margin: 10px 0 14px; }
        .result .note { text-align: left; }
    </style>
</head>
<body>
    <div class="card">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
