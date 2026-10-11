<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountCategory;

/**
 * Suppliers are paid in cash when buying, so nothing is "due" to them.
 * Paying a supplier is an Expense with the category "Supplier purchase": this page opens the
 * Add Expense form with that category already chosen.
 */
class SupplierPaymentController extends Controller
{
    public function payment()
    {
        $category = AccountCategory::firstOrCreate(['type' => 'expense', 'name' => 'Supplier purchase']);

        return redirect()->route('admin.accounting.expense.create', ['category' => $category->id]);
    }
}
