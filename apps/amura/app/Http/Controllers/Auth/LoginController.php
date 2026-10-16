<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

final class LoginController extends Controller
{
    /** Kata sandi salah per email sebelum akun itu dikunci, di atas batas per IP di route. */
    private const MAX_FAILS = 5;

    private const LOCK_SECONDS = 15 * 60;

    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = 'admin-login:'.config('app.brand').':'.strtolower($credentials['email']);
        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$minutes} menit."]);
        }

        $user = User::where('email', $credentials['email'])->first();

        // Fase 1 memakai satu tabel users untuk kedua brand: tolak user brand lain
        // dengan error generik agar keberadaan akun di brand lain tidak bocor.
        if ($user && $user->brand_scope !== config('app.brand')) {
            RateLimiter::hit($key, self::LOCK_SECONDS);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau kata sandi salah.']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::LOCK_SECONDS);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau kata sandi salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect($this->landingFor(Auth::user()->role));
    }

    /** Landing per role; pakai nama route pertama yang sudah terdaftar, jika tidak ada fallback ke root. */
    private function landingFor(?string $role): string
    {
        $routes = match ($role) {
            'cs' => ['admin.claims'],
            'game_admin' => ['admin.game'],
            'finance' => ['admin.finance'],
            'super_admin' => ['admin.users', 'admin.claims'],
            default => [],
        };

        foreach ($routes as $route) {
            if (Route::has($route)) {
                return route($route);
            }
        }

        return '/';
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
