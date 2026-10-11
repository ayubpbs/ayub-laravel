<?php

use App\Http\Controllers\Admin\Accounting as A;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Route;

// ===== Accounting (/admin/accounting) =====
Route::prefix('admin/accounting')->name('admin.accounting.')->middleware(AdminAuth::class)->group(function () {

    Route::get('/', [A\DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('income')->name('income.')->group(function () {
        Route::get('/', [A\IncomeController::class, 'index'])->name('index');
        Route::get('create', [A\IncomeController::class, 'create'])->name('create');
        Route::post('/', [A\IncomeController::class, 'store'])->name('store');
        Route::get('{id}/edit', [A\IncomeController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('{id}', [A\IncomeController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('{id}', [A\IncomeController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    Route::prefix('expenses')->name('expense.')->group(function () {
        Route::get('/', [A\ExpenseController::class, 'index'])->name('index');
        Route::get('create', [A\ExpenseController::class, 'create'])->name('create');
        Route::post('/', [A\ExpenseController::class, 'store'])->name('store');
        Route::get('{id}/edit', [A\ExpenseController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('{id}', [A\ExpenseController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('{id}', [A\ExpenseController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('due', [A\CustomerDueController::class, 'due'])->name('due');
        Route::get('receive-payment', [A\CustomerDueController::class, 'receive'])->name('receive');
        Route::post('receive-payment', [A\CustomerDueController::class, 'storeReceive'])->name('receive.store');
    });

    // Suppliers are paid in cash at purchase time, so there is no "due": a supplier payment is an expense
    Route::get('suppliers/payment', [A\SupplierPaymentController::class, 'payment'])->name('suppliers.payment');

    Route::get('accounts/{type}', [A\AccountController::class, 'show'])
        ->whereIn('type', ['cash', 'bkash', 'nagad'])->name('accounts.show');

    Route::prefix('accounts/{type}')->name('accounts.')->group(function () {
        Route::post('entries', [A\AccountController::class, 'store'])->where('type', 'cash|bkash|nagad')->name('entries.store');
        Route::delete('entries/{id}', [A\AccountController::class, 'destroy'])->where('type', 'cash|bkash|nagad')->whereNumber('id')->name('entries.destroy');
        Route::post('opening', [A\AccountController::class, 'updateOpening'])->where('type', 'cash|bkash|nagad')->name('opening.update');
    });

    Route::prefix('banks')->name('banks.')->group(function () {
        $b = A\BankAccountController::class;
        Route::get('/', [$b, 'index'])->name('index');
        Route::post('/', [$b, 'store'])->name('store');
        Route::get('{bank}', [$b, 'show'])->whereNumber('bank')->name('show');
        Route::put('{bank}', [$b, 'update'])->whereNumber('bank')->name('update');
        Route::delete('{bank}', [$b, 'destroy'])->whereNumber('bank')->name('destroy');
        Route::post('{bank}/entries', [$b, 'storeEntry'])->whereNumber('bank')->name('entries.store');
        Route::delete('{bank}/entries/{entry}', [$b, 'destroyEntry'])->whereNumber('bank')->whereNumber('entry')->name('entries.destroy');
        Route::post('{bank}/opening', [$b, 'updateOpening'])->whereNumber('bank')->name('opening.update');
    });

    Route::prefix('transfers')->name('transfers.')->group(function () {
        $t = A\TransferController::class;
        Route::get('/', [$t, 'index'])->name('index');
        Route::post('/', [$t, 'store'])->name('store');
        Route::get('{ref}/edit', [$t, 'edit'])->whereUuid('ref')->name('edit');
        Route::put('{ref}', [$t, 'update'])->whereUuid('ref')->name('update');
        Route::delete('{ref}', [$t, 'destroy'])->whereUuid('ref')->name('destroy');
    });
    Route::get('ledger', [A\LedgerController::class, 'index'])->name('ledger.index');

    Route::prefix('reports')->name('reports.')->group(function () {
        $r = A\ReportController::class;
        Route::get('profit-loss', [$r, 'profitLoss'])->name('profit_loss');
        Route::get('income', [$r, 'income'])->name('income');
        Route::get('expense', [$r, 'expense'])->name('expense');
        Route::get('cash-flow', [$r, 'cashFlow'])->name('cash_flow');
        Route::get('customer-due', [$r, 'customerDue'])->name('customer_due');
    });

    Route::prefix('categories')->name('categories.')->group(function () {
        $c = A\CategoryController::class;
        Route::get('income', [$c, 'income'])->name('income');
        Route::get('expense', [$c, 'expense'])->name('expense');
        Route::post('{type}', [$c, 'store'])->whereIn('type', ['income', 'expense'])->name('store');
        Route::put('{type}/{id}', [$c, 'update'])->whereIn('type', ['income', 'expense'])->whereNumber('id')->name('update');
        Route::delete('{type}/{id}', [$c, 'destroy'])->whereIn('type', ['income', 'expense'])->whereNumber('id')->name('destroy');
    });

    Route::get('settings', [A\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [A\SettingController::class, 'update'])->name('settings.update');
});
