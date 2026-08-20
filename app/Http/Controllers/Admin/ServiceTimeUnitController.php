<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceTimeUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceTimeUnitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Services/Index', [
            'timeUnits' => ServiceTimeUnit::orderBy('sort_order')->get()->map(fn ($u) => [
                'id' => $u->id,
                'name_ru' => $u->getTranslation('name', 'ru'),
                'name_en' => $u->getTranslation('name', 'en', false) ?: '',
                'sort_order' => $u->sort_order,
                'is_active' => $u->is_active,
            ]),
            'active_tab' => 'time-units',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ru' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        ServiceTimeUnit::create([
            'name' => array_filter(['ru' => $data['name_ru'], 'en' => $data['name_en'] ?? null]),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Единица времени создана.');
    }

    public function update(Request $request, ServiceTimeUnit $timeUnit): RedirectResponse
    {
        $data = $request->validate([
            'name_ru' => ['sometimes', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name_ru'])) {
            $timeUnit->setTranslation('name', 'ru', $data['name_ru']);
        }
        if (array_key_exists('name_en', $data)) {
            if (! empty($data['name_en'])) {
                $timeUnit->setTranslation('name', 'en', $data['name_en']);
            }
        }
        $timeUnit->sort_order = $data['sort_order'] ?? $timeUnit->sort_order;
        $timeUnit->is_active = $data['is_active'] ?? $timeUnit->is_active;
        $timeUnit->save();

        return back()->with('success', 'Единица времени обновлена.');
    }

    public function destroy(ServiceTimeUnit $timeUnit): RedirectResponse
    {
        $timeUnit->delete();

        return back()->with('success', 'Единица времени удалена.');
    }
}
