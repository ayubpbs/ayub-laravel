<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/category', [CategoryController::class, 'index'])->name('category.index');
Route::get('/category/{id}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/product/{id}', [ProductController::class, 'show'])->name('product.show');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{id}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');

// Checkout & orders
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/order/success/{id}', [CheckoutController::class, 'success'])->name('order.success');

// Customer's own copy of an invoice: only works with the signed link made in the admin panel
Route::get('/invoice/{id}', [\App\Http\Controllers\Admin\InvoiceController::class, 'publicShow'])
    ->middleware(['signed', 'throttle:30,1'])->name('invoice.public');

// ===== Admin panel (/admin) =====
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.submit');

    Route::middleware(\App\Http\Middleware\AdminAuth::class)->group(function () {
        Route::post('logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{id}/status', [\App\Http\Controllers\Admin\OrderController::class, 'status'])->name('orders.status');

        Route::get('customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{phone}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->where('phone', '[0-9]+')->name('customers.show');

        Route::resource('products', \App\Http\Controllers\Admin\ProductController::class)->except(['show']);
        Route::get('payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/export', [\App\Http\Controllers\Admin\PaymentController::class, 'export'])->name('payments.export');
        Route::get('payments/create', [\App\Http\Controllers\Admin\PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [\App\Http\Controllers\Admin\PaymentController::class, 'store'])->name('payments.store');
        Route::post('payments/collect-bulk', [\App\Http\Controllers\Admin\PaymentController::class, 'collectBulk'])->name('payments.collect.bulk');
        Route::post('payments/collect/{id}', [\App\Http\Controllers\Admin\PaymentController::class, 'collect'])->name('payments.collect');
        Route::delete('payments/{id}', [\App\Http\Controllers\Admin\PaymentController::class, 'destroy'])->name('payments.destroy');

        Route::get('invoices', [\App\Http\Controllers\Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/print', [\App\Http\Controllers\Admin\InvoiceController::class, 'printMany'])->name('invoices.print');
        Route::post('invoices/generate-missing', [\App\Http\Controllers\Admin\InvoiceController::class, 'generateMissing'])->name('invoices.generate.missing');
        Route::post('invoices/generate/{orderId}', [\App\Http\Controllers\Admin\InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::get('invoices/{id}', [\App\Http\Controllers\Admin\InvoiceController::class, 'show'])->whereNumber('id')->name('invoices.show');

        Route::prefix('courier')->name('courier.')->group(function () {
            $c = \App\Http\Controllers\Admin\CourierController::class;
            Route::get('steadfast', [$c, 'steadfast'])->name('steadfast');
            Route::post('steadfast/send-bulk', [$c, 'sendBulk'])->name('send.bulk');
            Route::post('steadfast/send/{id}', [$c, 'send'])->name('send');
            Route::get('tracking', [$c, 'tracking'])->name('tracking');
            Route::post('tracking/refresh-all', [$c, 'refreshAll'])->name('refresh.all');
            Route::post('tracking/{id}/refresh', [$c, 'refresh'])->name('refresh');
            Route::get('returns', [$c, 'returns'])->name('returns');
            Route::post('returns/{id}/request', [$c, 'requestReturn'])->name('returns.request');
            Route::post('returns/{id}/mark-returned', [$c, 'markReturned'])->name('returns.mark');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            $r = \App\Http\Controllers\Admin\ReportController::class;
            Route::get('daily', [$r, 'daily'])->name('daily');
            Route::get('monthly', [$r, 'monthly'])->name('monthly');
            Route::get('products', [$r, 'products'])->name('products');
            Route::get('profit', [$r, 'profit'])->name('profit');
            Route::get('orders/{status}', [$r, 'orders'])->whereIn('status', ['delivered', 'cancelled', 'returned'])->name('orders');
        });

        Route::prefix('notifications')->name('notifications.')->group(function () {
            $n = \App\Http\Controllers\Admin\NotificationController::class;
            Route::get('/', [$n, 'index'])->name('index');
            Route::post('read-all', [$n, 'readAll'])->name('read.all');
            Route::post('clear', [$n, 'clearRead'])->name('clear');
            Route::get('{id}/open', [$n, 'open'])->whereNumber('id')->name('open');
            Route::post('{id}/read', [$n, 'read'])->whereNumber('id')->name('read');
        });

        Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');

        Route::resource('brands', \App\Http\Controllers\Admin\BrandController::class)->except(['show']);
        Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class)->except(['show']);
    });
});
