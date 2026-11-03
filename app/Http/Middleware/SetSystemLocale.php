<?php

namespace App\Http\Middleware;

use App\Services\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetSystemLocale
{
    public function __construct(private AppSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->settings->forget();

        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        $locale = in_array($sessionLocale, AppSettings::LOCALES, true)
            ? $sessionLocale
            : $this->settings->locale();

        App::setLocale($locale);

        $timezone = $this->settings->timezone();
        config([
            'app.timezone' => $timezone,
            'app.date_format' => $this->settings->dateFormat(),
            'app.time_format' => $this->settings->timeFormat(),
            'app.currency' => $this->settings->currency(),
        ]);
        date_default_timezone_set($timezone);

        return $next($request);
    }
}
