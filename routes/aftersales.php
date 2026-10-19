<?php

use App\Http\Controllers\Admin\ClaimController;
use App\Http\Controllers\Admin\CustomerAddressController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\OrderCreateController;
use App\Http\Controllers\Admin\WarrantyController;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Route;

// Admin: customers added by hand and their saved addresses. Every route name starts with
// "admin.customers." so the existing "Customers" permission applies (see App\Support\AdminAccess).
Route::prefix('admin')->name('admin.')->middleware(AdminAuth::class)->group(function () {
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');

    $a = CustomerAddressController::class;
    Route::get('customers/{phone}/addresses/create', [$a, 'create'])->where('phone', '[0-9]+')->name('customers.addresses.create');
    Route::post('customers/{phone}/addresses', [$a, 'store'])->where('phone', '[0-9]+')->name('customers.addresses.store');
    Route::get('customers/{phone}/addresses/{id}/edit', [$a, 'edit'])->where('phone', '[0-9]+')->whereNumber('id')->name('customers.addresses.edit');
    Route::put('customers/{phone}/addresses/{id}', [$a, 'update'])->where('phone', '[0-9]+')->whereNumber('id')->name('customers.addresses.update');
    Route::post('customers/{phone}/addresses/{id}/default', [$a, 'makeDefault'])->where('phone', '[0-9]+')->whereNumber('id')->name('customers.addresses.default');
    Route::delete('customers/{phone}/addresses/{id}', [$a, 'destroy'])->where('phone', '[0-9]+')->whereNumber('id')->name('customers.addresses.destroy');

    // Admin: create an order by hand and Reorder (phase 4). The URL is /admin/new-order (not /admin/orders/create)
    // because the existing route admin/orders/{id} would catch "create" first. Names start with "admin.orders."
    // and are mapped to the "orders_create" permission above "admin.orders.*" in App\Support\AdminAccess.
    $o = OrderCreateController::class;
    Route::get('new-order', [$o, 'create'])->name('orders.create');
    Route::get('new-order/customer', [$o, 'lookup'])->name('orders.create.customer');
    Route::get('new-order/products', [$o, 'products'])->name('orders.create.products');
    Route::post('new-order', [$o, 'store'])->name('orders.store');

    // Phase 5: claims (exchange / wrong product / defective goods), replacement orders, return shipments, private evidence.
    // Names start with "admin.claims." and are mapped to claims_view / claims_manage / claims_inspect in App\Support\AdminAccess.
    // The private evidence files are only served by "claims.evidence.show" (nothing is stored under public/).
    $c = ClaimController::class;
    Route::get('claims', [$c, 'index'])->name('claims.index');
    Route::get('claims/new', [$c, 'create'])->name('claims.create');
    Route::post('claims', [$c, 'store'])->name('claims.store');
    Route::get('claims/products', [$c, 'products'])->name('claims.products');
    Route::get('claims/{id}', [$c, 'show'])->whereNumber('id')->name('claims.show');
    Route::post('claims/{id}/decision', [$c, 'decide'])->whereNumber('id')->name('claims.decide');
    Route::post('claims/{id}/order', [$c, 'order'])->whereNumber('id')->name('claims.order');
    Route::post('claims/{id}/refund', [$c, 'refund'])->whereNumber('id')->name('claims.refund');
    Route::post('claims/{id}/inspect', [$c, 'inspect'])->whereNumber('id')->name('claims.inspect');
    Route::post('claims/{id}/close', [$c, 'close'])->whereNumber('id')->name('claims.close');
    Route::post('claims/{id}/evidence', [$c, 'evidenceStore'])->whereNumber('id')->name('claims.evidence.store');
    Route::get('claims/{id}/evidence/{fileId}', [$c, 'evidenceShow'])->whereNumber('id')->whereNumber('fileId')->name('claims.evidence.show');
    Route::delete('claims/{id}/evidence/{fileId}', [$c, 'evidenceDestroy'])->whereNumber('id')->whereNumber('fileId')->name('claims.evidence.destroy');
    Route::post('claims/{id}/shipments', [$c, 'shipmentStore'])->whereNumber('id')->name('claims.shipments.store');
    Route::post('claims/{id}/shipments/{shipmentId}', [$c, 'shipmentUpdate'])->whereNumber('id')->whereNumber('shipmentId')->name('claims.shipments.update');

    // Phase 6: warranty claims. Names start with "admin.claims.warranty." and are mapped in App\Support\AdminAccess:
    // accept / reject / solution = aftersales_approve, inspection = claims_inspect, the rest = claims_manage.
    $w = WarrantyController::class;
    Route::get('claims/warranty/new', [$w, 'create'])->name('claims.warranty.create');
    Route::post('claims/warranty', [$w, 'store'])->name('claims.warranty.store');
    Route::post('claims/{id}/warranty/accept', [$w, 'accept'])->whereNumber('id')->name('claims.warranty.accept');
    Route::post('claims/{id}/warranty/reject', [$w, 'reject'])->whereNumber('id')->name('claims.warranty.reject');
    Route::post('claims/{id}/warranty/inspect', [$w, 'inspect'])->whereNumber('id')->name('claims.warranty.inspect');
    Route::post('claims/{id}/warranty/resolve', [$w, 'resolve'])->whereNumber('id')->name('claims.warranty.resolve');
    Route::post('claims/{id}/warranty/serials', [$w, 'serials'])->whereNumber('id')->name('claims.warranty.serials');
});
