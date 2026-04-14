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
                'rating_low_threshold'        => PlatformSetting::get('rating_low_threshold', 30),
                'content_pack_price_min'      => (int)  PlatformSetting::get('content_pack_price_min', 100),
                'content_pack_price_max'      => (int)  PlatformSetting::get('content_pack_price_max', 10000),
                'moderate_new_packs'          => (bool) (int) PlatformSetting::get('moderate_new_packs', 1),
                'moderate_existing_packs'     => (bool) (int) PlatformSetting::get('moderate_existing_packs', 0),
            ],
            'rating_deltas' => [
                'review_5star'            => (float) PlatformSetting::get('rating_delta_review_5star', 0.8),
                'review_4star'            => (float) PlatformSetting::get('rating_delta_review_4star', 0.4),
                'review_2star'            => (float) PlatformSetting::get('rating_delta_review_2star', -0.5),
                'review_1star'            => (float) PlatformSetting::get('rating_delta_review_1star', -1.2),
                'order_completed'         => (float) PlatformSetting::get('rating_delta_order_completed', 0.2),
                'report_accepted'         => (float) PlatformSetting::get('rating_delta_report_accepted', -2.0),
                'review_dispute_approved' => (float) PlatformSetting::get('rating_delta_review_dispute_approved', 0.6),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rating_low_threshold'                      => ['required', 'integer', 'min:0', 'max:100'],
            'content_pack_price_min'                    => ['required', 'integer', 'min:1'],
            'content_pack_price_max'                    => ['required', 'integer', 'gt:content_pack_price_min'],
            'moderate_new_packs'                        => ['required', 'boolean'],
            'moderate_existing_packs'                   => ['required', 'boolean'],
            'rating_deltas.review_5star'            => ['required', 'numeric', 'min:0', 'max:10'],
            'rating_deltas.review_4star'            => ['required', 'numeric', 'min:0', 'max:10'],
            'rating_deltas.review_2star'            => ['required', 'numeric', 'min:-10', 'max:0'],
            'rating_deltas.review_1star'            => ['required', 'numeric', 'min:-10', 'max:0'],
            'rating_deltas.order_completed'         => ['required', 'numeric', 'min:0', 'max:10'],
            'rating_deltas.report_accepted'         => ['required', 'numeric', 'min:-10', 'max:0'],
            'rating_deltas.review_dispute_approved' => ['required', 'numeric', 'min:0', 'max:10'],
        ]);

        PlatformSetting::set('rating_low_threshold',    $data['rating_low_threshold']);
        PlatformSetting::set('content_pack_price_min', $data['content_pack_price_min']);
        PlatformSetting::set('content_pack_price_max', $data['content_pack_price_max']);
        PlatformSetting::set('moderate_new_packs',     $data['moderate_new_packs'] ? '1' : '0');
        PlatformSetting::set('moderate_existing_packs', $data['moderate_existing_packs'] ? '1' : '0');

        foreach ($data['rating_deltas'] as $event => $delta) {
            PlatformSetting::set('rating_delta_' . $event, $delta);
        }

        return back()->with('success', 'Настройки сохранены.');
    }
}
