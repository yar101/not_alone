<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'rating_low_threshold'    => PlatformSetting::get('rating_low_threshold', 30),
                'would_buy_cooldown_days' => PlatformSetting::get('would_buy_cooldown_days', 7),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rating_low_threshold'    => ['required', 'integer', 'min:0', 'max:100'],
            'would_buy_cooldown_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        PlatformSetting::set('rating_low_threshold',    $data['rating_low_threshold']);
        PlatformSetting::set('would_buy_cooldown_days', $data['would_buy_cooldown_days']);

        return back()->with('success', 'Настройки сохранены.');
    }
}
