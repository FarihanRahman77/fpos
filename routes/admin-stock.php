<?php

// Loaded inside Route::middleware('auth')->prefix('admin') in routes/web.php (URLs start with /admin, names with admin.).

use App\Http\Controllers\Admin\StockController;
use Illuminate\Support\Facades\Route;

Route::prefix('/stock')->name('admin.stock.')->group(function () {
    Route::get('/products', [StockController::class, 'products'])->name('products');

    Route::get('/transfers', [StockController::class, 'transfers'])->name('transfers');
    Route::get('/transfers/list', [StockController::class, 'transferList'])->name('transfers.list');
    Route::get('/transfers/{id}', [StockController::class, 'transferShow'])->whereNumber('id')->name('transfers.show');
    Route::post('/transfers', [StockController::class, 'transferStore'])->name('transfers.store');
    Route::post('/transfers/{id}/receive', [StockController::class, 'transferReceive'])->whereNumber('id')->name('transfers.receive');
    Route::post('/transfers/{id}/cancel', [StockController::class, 'transferCancel'])->whereNumber('id')->name('transfers.cancel');

    Route::get('/adjustments', [StockController::class, 'adjustments'])->name('adjustments');
    Route::get('/adjustments/list', [StockController::class, 'adjustmentList'])->name('adjustments.list');
    Route::get('/adjustments/{id}', [StockController::class, 'adjustmentShow'])->whereNumber('id')->name('adjustments.show');
    Route::post('/adjustments', [StockController::class, 'adjustmentStore'])->name('adjustments.store');
    Route::post('/adjustments/{id}/cancel', [StockController::class, 'adjustmentCancel'])->whereNumber('id')->name('adjustments.cancel');

    Route::get('/batches', [StockController::class, 'batches'])->name('batches');
    Route::get('/batches/list', [StockController::class, 'batchList'])->name('batches.list');
    Route::post('/batches/{id}/expiry', [StockController::class, 'batchExpiry'])->whereNumber('id')->name('batches.expiry');
});
