<?php

namespace App\Providers;

use App\Models\SystemSetting;
use App\Support\PublicUpload;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('*', function ($view) {
            $request = request();

            if (! $request->attributes->has('app_shared_view_data')) {
                $settings = null;

                try {
                    if (Schema::hasTable('system_settings')) {
                        $settings = SystemSetting::current();
                    }
                } catch (\Throwable) {
                    $settings = null;
                }

                $businessName = $settings?->business_name ?: config('app.name', 'Laundry System');
                $uploadedLogo = PublicUpload::url($settings?->business_logo);

                if ($uploadedLogo) {
                    $businessLogo = $uploadedLogo;

                    // Append settings updated timestamp to force-refresh when settings change
                    try {
                        $settingsStamp = $settings?->updated_at?->timestamp ?? time();
                    } catch (\Throwable $e) {
                        $settingsStamp = time();
                    }

                    $businessLogo .= (str_contains($businessLogo, '?') ? '&' : '?').'ts='.$settingsStamp;
                } else {
                    $default = asset('logo.png');
                    try {
                        $stamp = file_exists(public_path('logo.png')) ? filemtime(public_path('logo.png')) : time();
                    } catch (\Throwable $e) {
                        $stamp = time();
                    }

                    try {
                        $settingsStamp = $settings?->updated_at?->timestamp ?? time();
                    } catch (\Throwable $e) {
                        $settingsStamp = time();
                    }

                    $businessLogo = $default.'?v='.$stamp.'&ts='.$settingsStamp;
                }

                $request->attributes->set('app_shared_view_data', [
                    'appSettings' => $settings,
                    'appSystemName' => config('app.name', 'SKL Management System'),
                    'appBusinessName' => $businessName,
                    'appBusinessLogo' => $businessLogo,
                    'appPrimaryColor' => $settings?->primary_color ?: '#2E7D32',
                    'appDarkModeDefault' => (bool) ($settings?->dark_mode_default ?? false),
                ]);
            }

            $view->with($request->attributes->get('app_shared_view_data'));
        });
    }
}
