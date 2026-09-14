<?php

namespace App\Providers;
use App\Models\Admin\Setting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

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
        $generalSetting = Session::get('generalSetting');
        if (!$generalSetting) {
            $generalSetting = Setting::where('deleted', 'No')
                ->where('status', 'Active')
                ->first();
            if ($generalSetting) {
                Session::put('generalSetting', $generalSetting);
            }
            View::share('generalSetting', $generalSetting);
        }
    }
}
