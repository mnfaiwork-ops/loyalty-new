<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\User;

final class SuperAdminController extends Controller
{
    public function index()
    {
        $users = User::where('brand_scope', config('app.brand'))
            ->latest()
            ->paginate(20);

        return view('admin.users', ['users' => $users]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', 'in:cs,game_admin,finance,super_admin'],
            'brand_scope' => ['required', 'in:amura,reglow'],
        ]);

        $data['brand_scope'] = config('app.brand');

        User::create($data);

        return back()->with('ok', 'Pengguna dibuat');
    }

    public function role(Request $request, int $id)
    {
        $user = User::where('brand_scope', config('app.brand'))->findOrFail($id);

        $data = $request->validate([
            'role' => ['required', 'in:cs,game_admin,finance,super_admin'],
            'brand_scope' => ['required', 'in:amura,reglow'],
        ]);

        if ($data['brand_scope'] !== config('app.brand')) {
            abort(404);
        }

        $user->update(['role' => $data['role'], 'brand_scope' => config('app.brand')]);

        return back()->with('ok', 'Peran diperbarui');
    }
}
