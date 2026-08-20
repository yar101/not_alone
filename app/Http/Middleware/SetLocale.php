<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->getLocale($request);
        $available = array_keys(config('app.available_locales', ['ru' => 'Русский']));

        if (in_array($locale, $available)) {
            App::setLocale($locale);
            if (! $request->has('locale')) {
                Session::put('locale', $locale);
            }
        }

        return $next($request);
    }

    private function getLocale(Request $request): string
    {
        if (Session::has('locale')) {
            return Session::get('locale');
        }

        if ($request->has('locale')) {
            return $request->get('locale');
        }

        if (auth()->check() && isset(auth()->user()->locale)) {
            return auth()->user()->locale;
        }

        $available = array_keys(config('app.available_locales', ['ru' => 'Русский']));
        $browser = $request->getPreferredLanguage($available);
        if ($browser) {
            return $browser;
        }

        return config('app.locale', 'ru');
    }
}
