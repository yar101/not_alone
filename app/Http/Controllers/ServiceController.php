<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServicePriceLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $idol = $request->user();
        abort_if(!$idol->is_idol, 403);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'category_id'  => ['required', 'integer', 'exists:service_categories,id'],
            'time_unit_id' => ['required', 'integer', 'exists:service_time_units,id'],
            'price'        => ['required', 'integer', 'min:1', 'max:999999'],
        ]);

        $this->validatePriceLimit($idol, $data['time_unit_id'], $data['price']);

        Service::create([
            ...$data,
            'user_id'   => $idol->id,
            'is_active' => true,
            'status'    => 'pending',
        ]);

        return back()->with('service_pending', true);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        abort_if($service->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:120'],
            'category_id'  => ['sometimes', 'integer', 'exists:service_categories,id'],
            'time_unit_id' => ['sometimes', 'integer', 'exists:service_time_units,id'],
            'price'        => ['sometimes', 'integer', 'min:1', 'max:999999'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $timeUnitId = $data['time_unit_id'] ?? $service->time_unit_id;
        $price      = $data['price'] ?? $service->price;

        if (isset($data['price']) || isset($data['time_unit_id'])) {
            $this->validatePriceLimit($request->user(), $timeUnitId, $price);
        }

        $service->update($data);

        return back()->with('success', 'Услуга обновлена.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        abort_if($service->user_id !== $request->user()->id, 403);
        $service->delete();

        return back()->with('success', 'Услуга удалена.');
    }

    private function validatePriceLimit($idol, int $timeUnitId, int $price): void
    {
        $threshold = (int) PlatformSetting::get('rating_low_threshold', 30);
        $rating    = (int) ($idol->idol_rating ?? 50);

        if ($rating < $threshold) {
            $limit = ServicePriceLimit::where('time_unit_id', $timeUnitId)->first();
            if ($limit && $price > $limit->max_price) {
                throw ValidationException::withMessages([
                    'price' => 'При вашем рейтинге цена не может превышать ' . $limit->max_price . ' ₽ за эту единицу времени.',
                ]);
            }
        }
    }
}
