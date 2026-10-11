<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('admin_ok')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login', ['disabled' => (string) config('shop.admin_password') === '']);
    }

    public function login(Request $request)
    {
        $password = (string) config('shop.admin_password');
        if ($password === '') {
            return back()->with('error', 'Admin password is not set.');
        }

        $userOk = hash_equals((string) config('shop.admin_user'), (string) $request->input('username'));
        $passOk = hash_equals($password, (string) $request->input('password'));

        if (!($userOk && $passOk)) {
            return back()->withInput($request->only('username'))->with('error', 'Incorrect username or password.');
        }

        $request->session()->regenerate();
        session(['admin_ok' => true]);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_ok');
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }
}
