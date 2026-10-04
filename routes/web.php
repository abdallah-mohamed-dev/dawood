<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CashboxController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\Inventory\MaterialController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfitController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SettingsController;
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::get('/cashbox', [CashboxController::class, 'index'])->name('cashbox.index');
    Route::post('/cashbox/opening-balance', [CashboxController::class, 'storeOpeningBalance'])
        ->name('cashbox.opening-balance.store');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::resource('materials', MaterialController::class)->except(['show', 'create']);
        Route::resource('purchases', PurchaseController::class)->only(['index', 'store', 'destroy']);
    });

    Route::resource('customers', CustomerController::class)->except('create');

    Route::resource('rooms', RoomController::class)->only(['show', 'destroy']);
    Route::post('/customers/{customer}/rooms', [RoomController::class, 'store'])->name('customers.rooms.store');
    Route::post('/rooms/{room}/status', [RoomController::class, 'updateStatus'])->name('rooms.status.update');
    Route::post('/rooms/{room}/materials', [RoomController::class, 'storeMaterial'])->name('rooms.materials.store');
    Route::post('/rooms/{room}/materials/{roomMaterial}/issue', [RoomController::class, 'issueMaterial'])->name('rooms.materials.issue');
    Route::delete('/rooms/{room}/materials/{roomMaterial}', [RoomController::class, 'destroyMaterial'])->name('rooms.materials.destroy');
    Route::post('/rooms/{room}/costs', [RoomController::class, 'storeCost'])->name('rooms.costs.store');
    Route::delete('/rooms/{room}/costs/{cost}', [RoomController::class, 'destroyCost'])->name('rooms.costs.destroy');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/rooms/{room}/payments', [PaymentController::class, 'store'])->name('rooms.payments.store');
    Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
    Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::resource('categories', ExpenseCategoryController::class)->except(['show', 'create']);
    });
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);

    Route::resource('partners', PartnerController::class);
    Route::post('/partners/{partner}/withdrawals', [PartnerController::class, 'storeWithdrawal'])->name('partners.withdrawals.store');
    Route::delete('/partners/{partner}/withdrawals/{withdrawal}', [PartnerController::class, 'destroyWithdrawal'])->name('partners.withdrawals.destroy');

    Route::get('/reports/profit', [ProfitController::class, 'index'])->name('reports.profit');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::get('/backup/database', [BackupController::class, 'downloadDatabase'])->name('backup.database');
    Route::put('/settings/material-types/{materialType}', [SettingsController::class, 'updateMaterialType'])->name('settings.material-types.update');

    Route::resource('debts', DebtController::class)->except(['show', 'create']);
    Route::post('/debts/{debt}/toggle-paid', [DebtController::class, 'togglePaid'])->name('debts.toggle-paid');

    Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index');
    Route::get('/logs/export', [ActivityLogController::class, 'export'])->name('logs.export');
    Route::delete('/logs', [ActivityLogController::class, 'purge'])->name('logs.purge');

    Route::get('/backup/csv', [BackupController::class, 'downloadCsvArchive'])->name('backup.csv');
});
