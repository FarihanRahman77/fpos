<?php

// Loaded inside Route::middleware('auth')->prefix('admin') in routes/web.php (URLs start with /admin, names with admin.).

use App\Http\Controllers\Admin\CustomerProgramController;
use Illuminate\Support\Facades\Route;

Route::prefix('/customer-programs')->name('admin.customers.')->group(function () {
    Route::get('/settings', [CustomerProgramController::class, 'settings'])->name('settings');
    Route::post('/settings', [CustomerProgramController::class, 'saveSettings'])->name('settings.save');

    Route::get('/loyalty', [CustomerProgramController::class, 'loyalty'])->name('loyalty');
    Route::get('/loyalty/list', [CustomerProgramController::class, 'loyaltyList'])->name('loyalty.list');
    Route::get('/loyalty/{partyId}', [CustomerProgramController::class, 'loyaltyHistory'])->whereNumber('partyId')->name('loyalty.history');
    Route::post('/loyalty/{partyId}/adjust', [CustomerProgramController::class, 'adjust'])->whereNumber('partyId')->name('loyalty.adjust');

    Route::get('/vat-report', [CustomerProgramController::class, 'vatReport'])->name('vat');
    Route::get('/vat-report/data', [CustomerProgramController::class, 'vatData'])->name('vat.data');
    Route::get('/vat-report/excel', [CustomerProgramController::class, 'vatExcel'])->name('vat.excel');
});
