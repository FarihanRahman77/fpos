<?php

namespace App\Providers;
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

    }
}
