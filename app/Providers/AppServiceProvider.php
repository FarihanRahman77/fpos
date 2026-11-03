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
        // Password reset links: the host comes from APP_URL, never from the request's Host header (which anyone can
        // set, so the e-mailed link — with its token — could otherwise point to another site). If APP_URL holds only
        // a host (e.g. http://localhost), the install's folder (/FPOS/public) is taken from the script path.
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($user, string $token) {
            $app = rtrim((string) config('app.url'), '/');
            $base = parse_url($app, PHP_URL_PATH) ? $app : $app . request()->getBaseUrl();

            return $base . route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false);
        });

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
            $themeCss = SettingController::themeCss($generalSetting->theme_colors ?? null, $generalSetting->button_radius ?? null);
            $pdfColors = SettingController::pdfColors();
        } catch (Throwable $exception) {
            Log::warning('Unable to build the theme colors.', ['exception' => $exception]);
        }
        View::share('themeCss', $themeCss);
        View::share('pc', $pdfColors);

        // Roles & permissions: @perm('sales.create') … @endperm, or @perm(['a.view', 'b.view']) for any of them.
        \Illuminate\Support\Facades\Blade::if('perm', fn ($permission) => \App\Http\Controllers\Admin\PermissionController::allows($permission));

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
