<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CashboxController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\MaterialController;
use App\Http\Controllers\Inventory\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/cashbox', [CashboxController::class, 'index'])->name('cashbox.index');
    Route::post('/cashbox/opening-balance', [CashboxController::class, 'storeOpeningBalance'])
        ->name('cashbox.opening-balance.store');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('materials', MaterialController::class)->except('show');
        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'destroy']);
    });
});
