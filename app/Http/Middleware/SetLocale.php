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
        $available = array_keys(config('app.available_locales', ['ru' => 'Русский', 'en' => 'English']));
        $default = config('app.locale', 'ru');

        if (auth()->check() && ! empty(auth()->user()->locale) && in_array(auth()->user()->locale, $available, true)) {
            $locale = auth()->user()->locale;
        } elseif (Session::has('locale') && in_array(Session::get('locale'), $available, true)) {
            $locale = Session::get('locale');
        } else {
            $locale = $default;
        }

        App::setLocale($locale);
        Session::put('locale', $locale);

        return $next($request);
    }
}
