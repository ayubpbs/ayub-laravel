<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;

class AccountController extends Controller
{
    const TITLES = [
        'cash'  => 'Cash Account',
        'bank'  => 'Bank Account',
        'bkash' => 'bKash Account',
        'nagad' => 'Nagad Account',
    ];

    public function show($type)
    {
        abort_unless(isset(self::TITLES[$type]), 404);

        return view('admin.accounting.placeholder', ['title' => self::TITLES[$type]]);
    }
}
