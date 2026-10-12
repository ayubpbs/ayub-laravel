<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Staff;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Owner only: add staff, choose what each person may do, switch accounts on/off */
class StaffController extends Controller
{
    public function index()
    {
        if (!Staff::tableReady()) {
            return redirect()->route('admin.dashboard')->with('error', 'Please run "php artisan migrate" once, then open Staff & Permissions again.');
        }

        $staff = Staff::orderBy('name')->get();

        // how many orders each person has sent to the courier
        $sent = Order::select('courier_sent_by', DB::raw('count(*) as c'))
            ->whereNotNull('courier_sent_by')->groupBy('courier_sent_by')->pluck('c', 'courier_sent_by');

        return view('admin.staff.index', ['staff' => $staff, 'sent' => $sent, 'labels' => AdminAccess::permissions()]);
    }

    public function create()
    {
        return view('admin.staff.form', ['member' => new Staff(['is_active' => true, 'permissions' => []]), 'perms' => AdminAccess::permissions()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Staff::create([
            'name'        => $data['name'],
            'username'    => $data['username'],
            'password'    => Hash::make($data['password']),
            'permissions' => $data['permissions'],
            'is_active'   => true,
        ]);

        return redirect()->route('admin.staff.index')->with('success', 'Staff "' . $data['name'] . '" added. They can sign in at /admin/login with this username and password.');
    }

    public function edit($id)
    {
        return view('admin.staff.form', ['member' => Staff::findOrFail($id), 'perms' => AdminAccess::permissions()]);
    }

    public function update(Request $request, $id)
    {
        $member = Staff::findOrFail($id);
        $data   = $this->validated($request, $member->id);

        $member->name        = $data['name'];
        $member->username    = $data['username'];
        $member->permissions = $data['permissions'];
        if (!empty($data['password'])) {
            $member->password = Hash::make($data['password']);
        }
        $member->save();

        return redirect()->route('admin.staff.index')->with('success', 'Staff "' . $member->name . '" updated.' . (!empty($data['password']) ? ' The new password is active.' : ''));
    }

    public function toggle($id)
    {
        $member = Staff::findOrFail($id);
        $member->is_active = !$member->is_active;
        $member->save();

        return back()->with('success', $member->is_active
            ? '"' . $member->name . '" can sign in again.'
            : '"' . $member->name . '" is switched off. They are signed out on their next click.');
    }

    public function destroy($id)
    {
        $member = Staff::findOrFail($id);
        $name   = $member->name;
        $member->delete(); // orders they sent keep their name

        return redirect()->route('admin.staff.index')->with('success', 'Staff "' . $name . '" deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'name'     => trim((string) $request->input('name')),
            'username' => strtolower(trim((string) $request->input('username'))),
        ]);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100', Rule::unique('staff_members', 'name')->ignore($ignoreId)],
            'username'      => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', Rule::unique('staff_members', 'username')->ignore($ignoreId)],
            'password'      => [$ignoreId ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(AdminAccess::permissions()))],
        ], [
            'name.required'     => 'Please enter the staff name.',
            'name.unique'       => 'Another staff member already has this name. Use a different name so you can tell them apart on courier orders.',
            'username.required' => 'Please enter a username.',
            'username.min'      => 'The username needs at least 3 characters.',
            'username.regex'    => 'The username can only have small letters, numbers, dot, dash and underscore (no spaces).',
            'username.unique'   => 'This username is already taken.',
            'password.required' => 'Please set a password.',
            'password.min'      => 'The password needs at least 8 characters.',
        ]);

        // "Owner" is the label used on courier orders sent by you, and your own username belongs to you
        if (strtolower($data['name']) === 'owner') {
            throw ValidationException::withMessages(['name' => '"Owner" is reserved for you. Please use the staff member\'s own name.']);
        }
        if (strtolower((string) config('shop.admin_user')) === $data['username']) {
            throw ValidationException::withMessages(['username' => 'This username is used by the owner. Please choose another.']);
        }

        $permissions = array_values(array_unique($data['permissions'] ?? []));
        // changing a status needs the order list
        if (in_array('orders_manage', $permissions, true) && !in_array('orders_view', $permissions, true)) {
            $permissions[] = 'orders_view';
        }
        $data['permissions'] = $permissions;

        return $data;
    }
}
