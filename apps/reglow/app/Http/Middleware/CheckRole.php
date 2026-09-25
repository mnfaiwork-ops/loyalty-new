<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        if ($user->brand_scope !== config('app.brand')) {
            abort(403);
        }

        return $next($request);
    }
}
