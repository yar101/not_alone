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
                'rating_low_threshold' => PlatformSetting::get('rating_low_threshold', 30),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rating_low_threshold' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        PlatformSetting::set('rating_low_threshold', $data['rating_low_threshold']);

        return back()->with('success', 'Настройки сохранены.');
    }
}
