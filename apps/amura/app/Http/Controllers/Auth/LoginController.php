<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

final class LoginController extends Controller
{
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau kata sandi salah.']);
        }

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
