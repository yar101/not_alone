<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    public function switch(Request $request)
    {
        $locale = $request->input('locale');
        $available = array_keys(config('app.available_locales', []));

        if (in_array($locale, $available)) {
            Session::put('locale', $locale);
            if (auth()->check()) {
                auth()->user()->update(['locale' => $locale]);
            }
        }

        return Redirect::back();
    }
}
