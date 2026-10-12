<?php

use App\Http\Controllers\Admin\StaffController;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Route;

// Staff & Permissions (owner only: /admin/staff/* is not open to staff accounts)
Route::prefix('admin')->name('admin.')->middleware(AdminAuth::class)->group(function () {
    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('staff/{id}/edit', [StaffController::class, 'edit'])->whereNumber('id')->name('staff.edit');
    Route::put('staff/{id}', [StaffController::class, 'update'])->whereNumber('id')->name('staff.update');
    Route::post('staff/{id}/toggle', [StaffController::class, 'toggle'])->whereNumber('id')->name('staff.toggle');
    Route::delete('staff/{id}', [StaffController::class, 'destroy'])->whereNumber('id')->name('staff.destroy');
});
