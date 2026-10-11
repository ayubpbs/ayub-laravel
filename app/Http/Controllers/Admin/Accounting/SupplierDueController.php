<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;

class SupplierDueController extends Controller
{
    public function due()
    {
        return view('admin.accounting.placeholder', ['title' => 'Supplier Due']);
    }

    public function payment()
    {
        return view('admin.accounting.placeholder', ['title' => 'Supplier Payment']);
    }
}
