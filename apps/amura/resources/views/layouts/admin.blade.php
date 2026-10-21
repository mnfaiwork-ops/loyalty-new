<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel Admin') · Loyalty {{ ucfirst((string) config('app.brand')) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #2B221D; --muted: #6F6259; --line: #E7DDD0; --bg: #F4EFE8; --panel: #FBF8F4;
            --amura: #1E6B63; --reglow: #A8461F; --ok: #2E7A4E; --danger: #B4372C; --gold: #8A6318;
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink); background: var(--bg); min-height: 100vh; }
        :focus-visible { outline: 3px solid var(--amura); outline-offset: 2px; }
        .shell { display: grid; grid-template-columns: 232px 1fr; min-height: 100vh; }
        aside { background: var(--panel); border-right: 1px solid var(--line); padding: 22px 14px; display: flex; flex-direction: column; gap: 18px; }
        .org b { display: block; font-size: 16px; font-weight: 800; }
        .org small { font-size: 12px; color: var(--muted); }
        .menu { display: flex; flex-direction: column; gap: 4px; }
        .menu a { display: flex; justify-content: space-between; align-items: center; min-height: 44px; padding: 0 12px; border-radius: 10px; color: var(--ink); font-size: 14px; font-weight: 700; text-decoration: none; }
        .menu a:hover { background: #F0E8DD; }
        .menu a[aria-current="page"] { background: var(--ink); color: #fff; }
        .menu a span { font-size: 12px; font-weight: 800; }
        .logout { margin-top: auto; }
        .logout button { width: 100%; min-height: 40px; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--muted); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; }
        main { padding: 26px 28px 48px; max-width: 1180px; width: 100%; min-width: 0; }
        h1 { margin: 0 0 4px; font-size: 26px; font-weight: 800; }
        h2 { margin: 24px 0 10px; font-size: 18px; font-weight: 800; }
        .lead { margin: 0 0 18px; font-size: 13px; color: var(--muted); }
        .flash { background: #E2F1E7; border: 1px solid #C7E3D1; color: var(--ok); border-radius: 12px; padding: 10px 14px; font-size: 14px; font-weight: 700; margin-bottom: 16px; }
        .flash-error { background: #FBE9E6; border: 1px solid #F1C9C3; color: var(--danger); border-radius: 12px; padding: 10px 14px; font-size: 14px; font-weight: 700; margin-bottom: 16px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid #EFE7DC; vertical-align: middle; }
        th { background: #F5EFE6; font-size: 12px; font-weight: 800; color: var(--muted); }
        tr:last-child td { border-bottom: none; }
        code { font-size: 13px; font-weight: 800; letter-spacing: .04em; }
        .badge { display: inline-block; font-size: 11px; font-weight: 800; border-radius: 6px; padding: 3px 8px; background: #F0E8DD; color: var(--ink); }
        .badge.amura { background: var(--amura); color: #fff; }
        .badge.reglow { background: var(--reglow); color: #fff; }
        .badge.gold { background: #F6EBD3; color: var(--gold); }
        .status { display: inline-block; font-size: 11px; font-weight: 800; border-radius: 999px; padding: 3px 9px; background: #F0E8DD; color: var(--muted); }
        .status.diajukan, .status.perlu_finance, .status.diproses, .status.dikirim { background: #E3EEF9; color: #2B5B8A; }
        .status.selesai { background: #E2F1E7; color: var(--ok); }
        .status.hangus { background: #F3E3E1; color: var(--danger); }
        .status.belum_redeem { background: #FBF1DE; color: var(--gold); }
        .muted { color: var(--muted); font-size: 12.5px; }
        .empty { padding: 28px 16px; text-align: center; color: var(--muted); }
        form.search { display: flex; gap: 8px; margin-bottom: 16px; max-width: 520px; }
        input[type=text], input[type=number], input[type=month], input[type=email], input[type=password], select, textarea {
            font-family: inherit; font-size: 14px; min-height: 44px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--ink);
        }
        form.search input { flex: 1; }
        .btn { display: inline-grid; place-items: center; min-height: 44px; border: none; border-radius: 10px; padding: 0 16px; font-family: inherit; font-size: 14px; font-weight: 800; line-height: 1.2; text-align: center; color: #fff; background: var(--ink); cursor: pointer; text-decoration: none; }
        .btn.ok { background: var(--ok); }
        .btn.danger { background: #fff; color: var(--danger); border: 1.5px solid #E7B8B1; }
        .btn.light { background: #fff; color: var(--ink); border: 1.5px solid var(--line); }
        .btn[disabled] { opacity: .45; cursor: not-allowed; }
        a.link { color: var(--amura); font-weight: 800; text-decoration: none; }
        .pager { margin-top: 16px; font-size: 13px; }
        .pager a { color: var(--amura); font-weight: 700; text-decoration: none; margin-right: 10px; }
        @media (max-width: 820px) {
            .shell { grid-template-columns: minmax(0, 1fr); }
            aside { border-right: 0; border-bottom: 1px solid var(--line); padding: 14px 16px; }
            .menu { flex-direction: row; flex-wrap: wrap; }
            .logout { margin-top: 0; }
            main { padding: 20px 16px 40px; }
        }
    </style>
    @stack('head')
</head>
<body>
    @php
        $user = auth()->user();
        $role = $user?->role;
        $counts = \LoyaltyNew\Core\Prizes\ClaimFlow::counts((string) config('app.brand'));
        $roleName = ['cs' => 'CS Staff', 'finance' => 'Finance', 'game_admin' => 'Game Admin', 'super_admin' => 'Super Admin'][$role] ?? 'Staf';
        $is = fn (...$roles) => in_array($role, $roles, true);
    @endphp
    <div class="shell">
        <aside>
            <div class="org">
                <b>Loyalty {{ ucfirst((string) config('app.brand')) }}</b>
                <small>{{ $roleName }} · {{ $user?->name }}</small>
            </div>
            <nav class="menu" aria-label="Menu panel">
                @if ($is('cs', 'super_admin'))
                    <a href="{{ route('admin.claims') }}" @if (request()->routeIs('admin.claims*')) aria-current="page" @endif>Klaim WhatsApp <span>{{ $counts['diajukan'] }}</span></a>
                    <a href="{{ route('admin.shipping') }}" @if (request()->routeIs('admin.shipping*')) aria-current="page" @endif>Pengiriman <span>{{ $counts['diproses'] + $counts['dikirim'] }}</span></a>
                    <a href="{{ route('admin.customers') }}" @if (request()->routeIs('admin.customers*')) aria-current="page" @endif>Pelanggan &amp; PIN</a>
                    <a href="{{ route('admin.daily') }}" @if (request()->routeIs('admin.daily*')) aria-current="page" @endif>Daftar harian BA</a>
                @endif
                @if ($is('finance', 'super_admin'))
                    <a href="{{ route('admin.finance') }}" @if (request()->routeIs('admin.finance*')) aria-current="page" @endif>Finance <span>{{ $counts['perlu_finance'] }}</span></a>
                @endif
                @if ($is('game_admin', 'super_admin'))
                    @if (Route::has('admin.catalog'))
                        <a href="{{ route('admin.catalog') }}" @if (request()->routeIs('admin.catalog*')) aria-current="page" @endif>Katalog poin</a>
                    @endif
                    <a href="{{ route('admin.game') }}" @if (request()->routeIs('admin.game*')) aria-current="page" @endif>Hadiah Gatcha</a>
                    <a href="{{ route('admin.display') }}" @if (request()->routeIs('admin.display*')) aria-current="page" @endif>Tampilan &amp; pop up</a>
                @endif
                @if ($is('super_admin'))
                    <a href="{{ route('admin.users') }}" @if (request()->routeIs('admin.users*')) aria-current="page" @endif>Pengguna</a>
                    <a href="{{ route('admin.setting') }}" @if (request()->routeIs('admin.setting*')) aria-current="page" @endif>Backup setting</a>
                @endif
            </nav>
            <form class="logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        </aside>
        <main>
            @if (session('ok'))
                <div class="flash" role="status">{{ session('ok') }}</div>
            @endif
            @if ($errors->any())
                <div class="flash-error" role="alert">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
