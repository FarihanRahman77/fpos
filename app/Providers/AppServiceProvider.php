<?php

namespace App\Providers;
use App\Http\Controllers\Admin\SettingController;
use App\Models\Admin\Setting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $generalSetting = null;

        try {
            if (Schema::hasTable('settings')) {
                $generalSetting = Setting::where('deleted', 'No')->where('status', 'Active')->first();
            } else {
                Log::warning('The settings table is unavailable; general settings were not loaded.');
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to load general settings during application boot.', [
                'exception' => $exception,
            ]);
        }

        if ($generalSetting) {
            Session::put('generalSetting', $generalSetting);
        }
        View::share('generalSetting', $generalSetting);

        // Settings > Theme Colors: the CSS override printed by the admin header / login page,
        // and the hex palette ($pc) used by the PDF and print templates.
        $themeCss = '';
        $pdfColors = [];
        try {
            $themeCss = SettingController::themeCss($generalSetting->theme_colors ?? null);
            $pdfColors = SettingController::pdfColors();
        } catch (Throwable $exception) {
            Log::warning('Unable to build the theme colors.', ['exception' => $exception]);
        }
        View::share('themeCss', $themeCss);
        View::share('pc', $pdfColors);

        // System > Email & SMS: SMTP settings saved in the app override .env (never breaks boot).
        try {
            \App\Http\Controllers\Admin\SystemSettingController::applyMailConfig();
        } catch (Throwable $exception) {
            Log::warning('Unable to apply the mail settings.', ['exception' => $exception]);
        }

        // System > Activity Log: record logins and logouts.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            \App\Http\Controllers\Admin\ActivityLogController::event('Logged in', (int) $event->user->getAuthIdentifier(), [], 'login');
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            if ($event->user) {
                \App\Http\Controllers\Admin\ActivityLogController::event('Logged out', (int) $event->user->getAuthIdentifier(), [], 'logout');
            }
        });

    }
}
