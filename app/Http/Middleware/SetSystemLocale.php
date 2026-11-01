<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetSystemLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Setting::getValue('system_language', config('app.locale'));

        App::setLocale(in_array($locale, ['en', 'sw'], true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
