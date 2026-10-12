<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('admin_ok')) {
            return redirect()->route('admin.dashboard');
        }

        $ownerOff = (string) config('shop.admin_password') === '';
        $hasStaff = Staff::tableReady() && Staff::where('is_active', true)->exists();

        return view('admin.login', ['disabled' => $ownerOff && !$hasStaff]);
    }

    public function login(Request $request)
    {
        $username = trim((string) $request->input('username'));
        $password = (string) $request->input('password');

        // 1) The owner: ADMIN_USER / ADMIN_PASSWORD from .env, exactly as before (needs no database)
        $ownerPassword = (string) config('shop.admin_password');
        if ($ownerPassword !== ''
            && hash_equals((string) config('shop.admin_user'), $username)
            && hash_equals($ownerPassword, $password)) {
            $request->session()->regenerate();
            $request->session()->forget('admin_staff_id');
            session(['admin_ok' => true]);

            return redirect()->route('admin.dashboard');
        }

        // 2) A staff member with their own username and password
        $staff = Staff::tableReady() ? Staff::where('username', strtolower($username))->first() : null;

        // A hash is always checked, so the answer takes the same time whether or not the username exists
        $passwordOk = Hash::check($password, $staff ? $staff->password : '$2y$10$JxT59RI61wqRHvNPvMidkeY9eOhpy279JXgH9e0Ba0cMjqOcqag.u');

        if ($staff && $passwordOk) {
            if (!$staff->is_active) {
                return back()->withInput($request->only('username'))->with('error', 'This account is switched off. Please contact the owner.');
            }

            $request->session()->regenerate();
            session(['admin_ok' => true, 'admin_staff_id' => $staff->id]);
            $staff->forceFill(['last_login_at' => now()])->save();

            return redirect(AdminAccess::firstAllowedUrl() ?: route('admin.dashboard'));
        }

        return back()->withInput($request->only('username'))->with('error', 'Incorrect username or password.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['admin_ok', 'admin_staff_id']);
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }
}
