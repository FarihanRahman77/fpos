<?php

// Loaded inside Route::middleware('auth')->prefix('admin') in routes/web.php (URLs start with /admin, names with admin.).

use App\Http\Controllers\Admin\BusinessReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('/business-reports')->name('admin.business-reports.')->group(function () {
    Route::get('/', [BusinessReportController::class, 'index'])->name('index');
    Route::post('/cash-closing', [BusinessReportController::class, 'saveClosing'])->name('closing.save');
    Route::post('/cash-closing/clear', [BusinessReportController::class, 'clearClosing'])->name('closing.clear');
    Route::post('/budget', [BusinessReportController::class, 'saveBudget'])->name('budget.save');
    Route::get('/{report}', [BusinessReportController::class, 'show'])->name('show');
    Route::get('/{report}/data', [BusinessReportController::class, 'data'])->name('data');
    Route::get('/{report}/pdf', [BusinessReportController::class, 'pdf'])->name('pdf');
    Route::get('/{report}/excel', [BusinessReportController::class, 'excel'])->name('excel');
});
