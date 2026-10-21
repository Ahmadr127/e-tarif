<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        if (! Auth::check()) {
            return redirect('/login');
        }

        $permissions = array_filter(array_map('trim', explode('|', (string) $permission)));

        foreach ($permissions as $permissionName) {
            if (Auth::user()->hasPermission($permissionName)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
