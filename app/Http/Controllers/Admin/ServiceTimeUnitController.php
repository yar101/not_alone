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
            'timeUnits'  => ServiceTimeUnit::orderBy('sort_order')->get(['id', 'name', 'sort_order', 'is_active']),
            'active_tab' => 'time-units',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active'  => ['boolean'],
        ]);

        ServiceTimeUnit::create($data);

        return back()->with('success', 'Единица времени создана.');
    }

    public function update(Request $request, ServiceTimeUnit $timeUnit): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $timeUnit->update($data);

        return back()->with('success', 'Единица времени обновлена.');
    }

    public function destroy(ServiceTimeUnit $timeUnit): RedirectResponse
    {
        $timeUnit->delete();

        return back()->with('success', 'Единица времени удалена.');
    }
}
