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
            'limits'     => ServicePriceLimit::with('timeUnit:id,name')->get(),
            'timeUnits'  => ServiceTimeUnit::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'active_tab' => 'price-limits',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'time_unit_id' => ['required', 'integer', 'exists:service_time_units,id'],
            'max_price'    => ['required', 'integer', 'min:1'],
        ]);

        ServicePriceLimit::create($data);

        return back()->with('success', 'Лимит цены создан.');
    }

    public function update(Request $request, ServicePriceLimit $priceLimit): RedirectResponse
    {
        $data = $request->validate([
            'time_unit_id' => ['sometimes', 'integer', 'exists:service_time_units,id'],
            'max_price'    => ['sometimes', 'integer', 'min:1'],
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
