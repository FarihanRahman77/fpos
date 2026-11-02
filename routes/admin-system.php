<?php

// Loaded inside Route::middleware('auth')->prefix('admin') in routes/web.php (URLs start with /admin, names with admin.).

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\SmsController;
use App\Http\Controllers\Admin\SystemSettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('system')->name('admin.system.')->group(function () {
    // Activity log
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity');
    Route::get('/activity-log/list', [ActivityLogController::class, 'list'])->name('activity.list');
    Route::get('/activity-log/{id}', [ActivityLogController::class, 'show'])->whereNumber('id')->name('activity.show');
    Route::post('/activity-log/retention', [ActivityLogController::class, 'saveRetention'])->name('activity.retention');
    Route::post('/activity-log/prune', [ActivityLogController::class, 'prunePost'])->name('activity.prune');

    // Backups
    Route::get('/backups', [BackupController::class, 'index'])->name('backups');
    Route::get('/backups/list', [BackupController::class, 'list'])->name('backups.list');
    Route::post('/backups/run', [BackupController::class, 'runNow'])->name('backups.run');
    Route::post('/backups/settings', [BackupController::class, 'saveSettings'])->name('backups.settings');
    Route::get('/backups/{file}/download', [BackupController::class, 'download'])->where('file', '[A-Za-z0-9._-]+')->name('backups.download');
    Route::post('/backups/{file}/delete', [BackupController::class, 'destroy'])->where('file', '[A-Za-z0-9._-]+')->name('backups.delete');

    // Email & SMS settings
    Route::get('/email-sms', [SystemSettingController::class, 'index'])->name('email-sms');
    Route::post('/email-sms/mail', [SystemSettingController::class, 'saveMail'])->name('email-sms.mail');
    Route::post('/email-sms/mail/test', [SystemSettingController::class, 'testMail'])->name('email-sms.mail.test');
    Route::post('/email-sms/sms', [SystemSettingController::class, 'saveSms'])->name('email-sms.sms');
    Route::post('/email-sms/sms/test', [SmsController::class, 'test'])->name('email-sms.sms.test');
    Route::post('/sms/balance', [SmsController::class, 'balancePost'])->name('sms.balance');

    // SMS: due reminders + log
    Route::get('/sms/due-reminders', [SmsController::class, 'dueIndex'])->name('sms.due');
    Route::get('/sms/due-reminders/list', [SmsController::class, 'dueList'])->name('sms.due.list');
    Route::post('/sms/due-reminders/send', [SmsController::class, 'dueSend'])->name('sms.due.send');
    Route::get('/sms/log', [SmsController::class, 'logIndex'])->name('sms.log');
    Route::get('/sms/log/list', [SmsController::class, 'logList'])->name('sms.log.list');
});
