<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServicePriceLimit;
use App\Models\ServiceTimeUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicePriceLimitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Services/Index', [
            'limits' => ServicePriceLimit::with('timeUnit')->get()->map(fn ($l) => [
                'id' => $l->id,
                'time_unit_id' => $l->time_unit_id,
                'max_price' => $l->max_price,
                'time_unit' => $l->timeUnit ? [
                    'id' => $l->timeUnit->id,
                    'name_ru' => $l->timeUnit->getTranslation('name', 'ru'),
                ] : null,
            ]),
            'timeUnits' => ServiceTimeUnit::where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name_ru' => $u->getTranslation('name', 'ru'),
                    'name_en' => $u->getTranslation('name', 'en', false) ?: '',
                ]),
            'active_tab' => 'price-limits',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'time_unit_id' => ['required', 'integer', 'exists:service_time_units,id'],
            'max_price' => ['required', 'integer', 'min:1'],
        ]);

        ServicePriceLimit::create($data);

        return back()->with('success', 'Лимит цены создан.');
    }

    public function update(Request $request, ServicePriceLimit $priceLimit): RedirectResponse
    {
        $data = $request->validate([
            'time_unit_id' => ['sometimes', 'integer', 'exists:service_time_units,id'],
            'max_price' => ['sometimes', 'integer', 'min:1'],
        ]);

        $priceLimit->update($data);

        return back()->with('success', 'Лимит цены обновлён.');
    }

    public function destroy(ServicePriceLimit $priceLimit): RedirectResponse
    {
        $priceLimit->delete();

        return back()->with('success', 'Лимит цены удалён.');
    }
}
