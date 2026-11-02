<?php

// Loaded inside Route::middleware('auth')->prefix('admin') in routes/web.php (URLs start with /admin, names with admin.).

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AccountingReportController;

// Accounting statements (Trial Balance, Profit & Loss, Balance Sheet) and year-end closing.
Route::controller(AccountingReportController::class)->prefix('accounting')->group(function () {
    Route::get('/trial-balance', 'trialBalance')->middleware('main.branch')->name('admin.accounting.trial-balance');
    Route::get('/trial-balance/data', 'trialBalanceData')->middleware('main.branch')->name('admin.accounting.trial-balance.data');
    Route::get('/trial-balance/pdf', 'trialBalancePdf')->middleware('main.branch')->name('admin.accounting.trial-balance.pdf');
    Route::get('/trial-balance/excel', 'trialBalanceExcel')->middleware('main.branch')->name('admin.accounting.trial-balance.excel');

    Route::get('/profit-loss', 'profitLoss')->name('admin.accounting.profit-loss');
    Route::get('/profit-loss/data', 'profitLossData')->name('admin.accounting.profit-loss.data');
    Route::get('/profit-loss/pdf', 'profitLossPdf')->name('admin.accounting.profit-loss.pdf');
    Route::get('/profit-loss/excel', 'profitLossExcel')->name('admin.accounting.profit-loss.excel');

    Route::get('/balance-sheet', 'balanceSheet')->middleware('main.branch')->name('admin.accounting.balance-sheet');
    Route::get('/balance-sheet/data', 'balanceSheetData')->middleware('main.branch')->name('admin.accounting.balance-sheet.data');
    Route::get('/balance-sheet/pdf', 'balanceSheetPdf')->middleware('main.branch')->name('admin.accounting.balance-sheet.pdf');
    Route::get('/balance-sheet/excel', 'balanceSheetExcel')->middleware('main.branch')->name('admin.accounting.balance-sheet.excel');

    Route::get('/fiscal-years', 'fiscalYears')->middleware('main.branch')->name('admin.accounting.fiscal-years');
    Route::get('/fiscal-years/list', 'fiscalYearList')->middleware('main.branch')->name('admin.accounting.fiscal-years.list');
    Route::post('/fiscal-years', 'fiscalYearStore')->middleware('main.branch')->name('admin.accounting.fiscal-years.store');
    Route::get('/fiscal-years/{id}', 'fiscalYearShow')->whereNumber('id')->middleware('main.branch')->name('admin.accounting.fiscal-years.show');
    Route::get('/fiscal-years/{id}/preview', 'fiscalYearPreview')->whereNumber('id')->middleware('main.branch')->name('admin.accounting.fiscal-years.preview');
    Route::post('/fiscal-years/{id}/close', 'fiscalYearClose')->whereNumber('id')->middleware('main.branch')->name('admin.accounting.fiscal-years.close');
    Route::post('/fiscal-years/{id}/reopen', 'fiscalYearReopen')->whereNumber('id')->middleware('main.branch')->name('admin.accounting.fiscal-years.reopen');
    Route::post('/fiscal-years/{id}/delete', 'fiscalYearDelete')->whereNumber('id')->middleware('main.branch')->name('admin.accounting.fiscal-years.delete');
});
