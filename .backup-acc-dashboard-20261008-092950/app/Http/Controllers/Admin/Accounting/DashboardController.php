<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.accounting.placeholder', ['title' => 'Accounting Dashboard']);
    }
}
