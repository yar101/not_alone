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
                'order_auto_complete_delay' => (float) PlatformSetting::get('order_auto_complete_delay', 72),
                'order_dispute_window_minutes' => (int) PlatformSetting::get('order_dispute_window_minutes', 60),
                'content_pack_price_min' => (int) PlatformSetting::get('content_pack_price_min', 100),
                'content_pack_price_max' => (int) PlatformSetting::get('content_pack_price_max', 10000),
                'moderate_new_packs' => (bool) (int) PlatformSetting::get('moderate_new_packs', 1),
                'moderate_existing_packs' => (bool) (int) PlatformSetting::get('moderate_existing_packs', 0),
                'deposit_fee_percent' => (float) PlatformSetting::get('deposit_fee_percent', config('services.payments.deposit_fee_percent', 4.0)),
                'withdrawal_fee_percent' => (float) PlatformSetting::get('withdrawal_fee_percent', config('services.payments.withdrawal_fee_percent', 4.0)),
                'platform_fee_percent' => (float) PlatformSetting::get('platform_fee_percent', config('services.payments.platform_fee_percent', 10.0)),
                'order_customer_service_fee_percent' => (float) PlatformSetting::get('order_customer_service_fee_percent', 5.0),
                'donation_min_amount' => (int) PlatformSetting::get('donation_min_amount', 1),
                'donation_max_amount' => (int) PlatformSetting::get('donation_max_amount', 15000),
                'donation_daily_limit' => (int) PlatformSetting::get('donation_daily_limit', 10),
            ],
            'rating_deltas' => [
                'review_5star' => (float) PlatformSetting::get('rating_delta_review_5star', 0.8),
                'review_4star' => (float) PlatformSetting::get('rating_delta_review_4star', 0.4),
                'review_2star' => (float) PlatformSetting::get('rating_delta_review_2star', -0.5),
                'review_1star' => (float) PlatformSetting::get('rating_delta_review_1star', -1.2),
                'order_completed' => (float) PlatformSetting::get('rating_delta_order_completed', 0.2),
                'order_refunded' => (float) PlatformSetting::get('rating_delta_order_refunded', -10.0),
                'report_accepted' => (float) PlatformSetting::get('rating_delta_report_accepted', -2.0),
                'review_dispute_approved' => (float) PlatformSetting::get('rating_delta_review_dispute_approved', 0.6),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rating_low_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'order_auto_complete_delay' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'order_dispute_window_minutes' => ['sometimes', 'integer', 'min:1', 'max:10080'],
            'content_pack_price_min' => ['required', 'integer', 'min:1'],
            'content_pack_price_max' => ['required', 'integer', 'gt:content_pack_price_min'],
            'moderate_new_packs' => ['required', 'boolean'],
            'moderate_existing_packs' => ['required', 'boolean'],
            'deposit_fee_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'withdrawal_fee_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'platform_fee_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'order_customer_service_fee_percent' => ['sometimes', 'numeric', 'min:0', 'max:50'],
            'donation_min_amount' => ['sometimes', 'numeric', 'min:1'],
            'donation_max_amount' => ['sometimes', 'numeric', 'gte:donation_min_amount'],
            'donation_daily_limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'rating_deltas.review_5star' => ['required', 'numeric', 'min:0', 'max:10'],
            'rating_deltas.review_4star' => ['required', 'numeric', 'min:0', 'max:10'],
            'rating_deltas.review_2star' => ['required', 'numeric', 'min:-10', 'max:0'],
            'rating_deltas.review_1star' => ['required', 'numeric', 'min:-10', 'max:0'],
            'rating_deltas.order_completed' => ['required', 'numeric', 'min:0', 'max:10'],
            'rating_deltas.order_refunded' => ['sometimes', 'numeric', 'min:-50', 'max:0'],
            'rating_deltas.report_accepted' => ['required', 'numeric', 'min:-10', 'max:0'],
            'rating_deltas.review_dispute_approved' => ['required', 'numeric', 'min:0', 'max:10'],
        ]);

        PlatformSetting::set('rating_low_threshold', $data['rating_low_threshold']);
        PlatformSetting::set('order_auto_complete_delay', $data['order_auto_complete_delay']);
        if (isset($data['order_dispute_window_minutes'])) {
            PlatformSetting::set('order_dispute_window_minutes', $data['order_dispute_window_minutes']);
        }
        PlatformSetting::set('content_pack_price_min', $data['content_pack_price_min']);
        PlatformSetting::set('content_pack_price_max', $data['content_pack_price_max']);
        PlatformSetting::set('moderate_new_packs', $data['moderate_new_packs'] ? '1' : '0');
        PlatformSetting::set('moderate_existing_packs', $data['moderate_existing_packs'] ? '1' : '0');
        PlatformSetting::set('deposit_fee_percent', $data['deposit_fee_percent']);
        PlatformSetting::set('withdrawal_fee_percent', $data['withdrawal_fee_percent']);
        PlatformSetting::set('platform_fee_percent', $data['platform_fee_percent']);
        if (isset($data['order_customer_service_fee_percent'])) {
            PlatformSetting::set('order_customer_service_fee_percent', $data['order_customer_service_fee_percent']);
        }
        if (isset($data['donation_min_amount'])) {
            PlatformSetting::set('donation_min_amount', $data['donation_min_amount']);
        }
        if (isset($data['donation_max_amount'])) {
            PlatformSetting::set('donation_max_amount', $data['donation_max_amount']);
        }
        if (isset($data['donation_daily_limit'])) {
            PlatformSetting::set('donation_daily_limit', $data['donation_daily_limit']);
        }
        foreach ($data['rating_deltas'] as $event => $delta) {
            PlatformSetting::set('rating_delta_'.$event, $delta);
        }

        \App\Services\AdminLogService::log(
            auth('admin')->id(),
            'update_platform_settings',
            'platform_setting',
            null,
            $data
        );

        return back()->with('success', 'Настройки сохранены.');
    }
}
