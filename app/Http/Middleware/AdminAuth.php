<?php

namespace App\Http\Middleware;

use Closure;

class AdminAuth
{
    public function handle($request, Closure $next)
    {
        if (!session('admin_ok')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
