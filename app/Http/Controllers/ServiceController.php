<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePriceLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function forOffer(Request $request): JsonResponse
    {
        $user = $request->user();

        $services = Service::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->with(['category:id,name', 'timeUnit:id,name'])
            ->get();

        if ($services->isEmpty()) {
            return response()->json([]);
        }

        $grouped = $services
            ->groupBy('category_id')
            ->map(fn($items, $categoryId) => [
                'category' => [
                    'id'      => $items->first()->category->id,
                    'name_ru' => $items->first()->category->getTranslation('name', 'ru'),
                    'name_en' => $items->first()->category->getTranslation('name', 'en', false) ?: null,
                ],
                'services' => $items->map(fn($s) => [
                    'id'      => $s->id,
                    'name_ru' => $s->getTranslation('name', 'ru'),
                    'name_en' => $s->getTranslation('name', 'en', false) ?: null,
                    'price'   => $s->price,
                    'time_unit_ru' => $s->timeUnit?->getTranslation('name', 'ru'),
                    'time_unit_en' => $s->timeUnit?->getTranslation('name', 'en', false) ?: null,
                ])->values(),
            ])
            ->values();

        return response()->json($grouped);
    }

    public function store(Request $request): RedirectResponse
    {
        $idol = $request->user();
        abort_if(!$idol->is_idol, 403);

        $data = $request->validate([
            'name_ru'      => ['nullable', 'string', 'max:120'],
            'name_en'      => ['nullable', 'string', 'max:120', 'regex:/^[^\x{0400}-\x{04FF}\x{0500}-\x{052F}]*$/u'],
            'category_id'  => ['required', 'integer', 'exists:service_categories,id'],
            'time_unit_id' => ['required', 'integer', 'exists:service_time_units,id'],
            'price'        => ['required', 'integer', 'min:1', 'max:999999'],
        ]);

        if (empty($data['name_ru']) && empty($data['name_en'])) {
            throw ValidationException::withMessages([
                'name_ru' => 'Укажите хотя бы одно название услуги.',
            ]);
        }

        $this->validatePriceLimit($idol, $data['time_unit_id'], $data['price']);

        Service::create([
            'name'         => array_filter(['ru' => $data['name_ru'] ?? null, 'en' => $data['name_en'] ?? null]),
            'user_id'      => $idol->id,
            'category_id'  => $data['category_id'],
            'time_unit_id' => $data['time_unit_id'],
            'price'        => $data['price'],
            'is_active'    => true,
            'status'       => 'pending',
        ]);

        return back()->with('service_pending', true);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        abort_if($service->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name_ru'      => ['sometimes', 'string', 'max:120'],
            'name_en'      => ['nullable', 'string', 'max:120', 'regex:/^[^\x{0400}-\x{04FF}\x{0500}-\x{052F}]*$/u'],
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

        if (array_key_exists('name_ru', $data) || array_key_exists('name_en', $data)) {
            $ruAfter = $data['name_ru'] ?? $service->getTranslation('name', 'ru', false);
            $enAfter = $data['name_en'] ?? $service->getTranslation('name', 'en', false);
            if (empty($ruAfter) && empty($enAfter)) {
                throw ValidationException::withMessages([
                    'name_ru' => 'Укажите хотя бы одно название услуги.',
                ]);
            }
        }

        if (isset($data['name_ru'])) {
            $service->setTranslation('name', 'ru', $data['name_ru']);
        }
        if (array_key_exists('name_en', $data)) {
            if (!empty($data['name_en'])) {
                $service->setTranslation('name', 'en', $data['name_en']);
            } else {
                $service->forgetTranslation('name', 'en');
            }
        }

        $rest = array_diff_key($data, array_flip(['name_ru', 'name_en']));
        if (!empty($rest)) {
            $service->fill($rest);
        }
        $service->save();

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
