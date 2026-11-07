<?php

namespace App\Providers;

use App\Services\AppSettings;
use App\Services\BrandPalette;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AppSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.app', 'layouts.guest'], function ($view): void {
            $settings = app(AppSettings::class);
            $view->with([
                'systemName' => $settings->systemName(),
                'churchName' => $settings->churchName(),
                'brandCss' => app(BrandPalette::class)->css($settings->primaryColor()),
                'logoUrl' => $settings->logoUrl(),
                'faviconUrl' => $settings->faviconUrl(),
                'faviconType' => $settings->faviconMimeType(),
                'showSidebarLogo' => $settings->showLogoInSidebar(),
            ]);
        });

        RateLimiter::for('swalasms', fn (): Limit => Limit::perMinute(
            max(1, (int) config('services.swalasms.requests_per_minute', 30)),
        )->by('swalasms'));
    }
}
