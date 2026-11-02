<?php

// Loaded inside Route::middleware('auth')->prefix('admin') in routes/web.php (URLs start with /admin, names with admin.).

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceDeviceController;
use App\Http\Controllers\Admin\PayslipController;
use Illuminate\Support\Facades\Route;

// Attendance: daily sheet, monthly report, import, shifts / rules / devices, send to salary
Route::prefix('/attendance')->name('admin.attendance.')->group(function () {
    Route::get('/daily', [AttendanceController::class, 'daily'])->name('daily');
    Route::get('/daily/data', [AttendanceController::class, 'dailyData'])->name('daily.data');
    Route::post('/daily', [AttendanceController::class, 'saveDaily'])->name('daily.save');

    Route::get('/monthly', [AttendanceController::class, 'monthly'])->name('monthly');
    Route::get('/monthly/data', [AttendanceController::class, 'monthlyData'])->name('monthly.data');
    Route::get('/monthly/pdf', [AttendanceController::class, 'monthlyPdf'])->name('monthly.pdf');
    Route::get('/monthly/excel', [AttendanceController::class, 'monthlyExcel'])->name('monthly.excel');

    Route::get('/salary/preview', [AttendanceController::class, 'salaryPreview'])->name('salary.preview');
    Route::post('/salary', [AttendanceController::class, 'sendToSalary'])->name('salary.send');

    Route::get('/import', [AttendanceController::class, 'import'])->name('import');
    Route::post('/import/upload', [AttendanceController::class, 'importUpload'])->name('import.upload');
    Route::post('/import/preview', [AttendanceController::class, 'importPreview'])->name('import.preview');
    Route::post('/import/commit', [AttendanceController::class, 'importCommit'])->name('import.commit');

    Route::get('/setup', [AttendanceController::class, 'setup'])->name('setup');
    Route::get('/shifts', [AttendanceController::class, 'shiftList'])->name('shifts');
    Route::post('/shifts', [AttendanceController::class, 'saveShift'])->name('shifts.save');
    Route::post('/shifts/{id}/delete', [AttendanceController::class, 'deleteShift'])->whereNumber('id')->name('shifts.delete');
    Route::post('/settings', [AttendanceController::class, 'saveSettings'])->name('settings.save');

    Route::get('/devices', [AttendanceDeviceController::class, 'list'])->name('devices');
    Route::post('/devices', [AttendanceDeviceController::class, 'save'])->name('devices.save');
    Route::post('/devices/{id}/delete', [AttendanceDeviceController::class, 'delete'])->whereNumber('id')->name('devices.delete');
    Route::post('/devices/{id}/test', [AttendanceDeviceController::class, 'test'])->whereNumber('id')->name('devices.test');
    Route::post('/devices/{id}/sync', [AttendanceDeviceController::class, 'sync'])->whereNumber('id')->name('devices.sync');
});

// Payslips of processed salary
Route::prefix('/payslips')->name('admin.payslips.')->group(function () {
    Route::get('/', [PayslipController::class, 'index'])->name('index');
    Route::get('/list', [PayslipController::class, 'list'])->name('list');
    Route::get('/all/pdf', [PayslipController::class, 'bulkPdf'])->name('bulk-pdf');
    Route::post('/email', [PayslipController::class, 'emailBulk'])->name('email-bulk');
    Route::get('/{id}/pdf', [PayslipController::class, 'pdf'])->whereNumber('id')->name('pdf');
    Route::post('/{id}/email', [PayslipController::class, 'email'])->whereNumber('id')->name('email');
});
