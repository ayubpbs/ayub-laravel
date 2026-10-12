<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;

class AdminAuth
{
    public function handle($request, Closure $next)
    {
        if (!session('admin_ok')) {
            return redirect()->route('admin.login');
        }

        // A staff member who was switched off or deleted is signed out on their very next click
        if (session('admin_staff_id')) {
            $staff = AdminAccess::staff();

            if (!$staff || !$staff->is_active) {
                $request->session()->forget(['admin_ok', 'admin_staff_id']);

                return redirect()->route('admin.login')->with('error', 'This account is switched off. Please contact the owner.');
            }
        }

        $route = optional($request->route())->getName();

        if ($route && !AdminAccess::allows($route)) {
            // The dashboard is where everyone lands: send them to a page they may use instead
            if ($route === 'admin.dashboard' && ($url = AdminAccess::firstAllowedUrl())) {
                return redirect($url);
            }

            return response()->view('admin.denied', ['home' => AdminAccess::firstAllowedUrl()], 403);
        }

        return $next($request);
    }
}
