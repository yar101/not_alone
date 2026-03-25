<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersonalityTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonalityTraitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Traits/Index', [
            'traits' => PersonalityTrait::withCount('users')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ru'    => 'required|string|max:100|unique:traits,name_ru',
            'sort_order' => 'integer|min:0',
        ]);
        PersonalityTrait::create($data);
        return back()->with('success', 'Черта добавлена.');
    }

    public function update(Request $request, PersonalityTrait $trait): RedirectResponse
    {
        $data = $request->validate([
            'name_ru'    => 'required|string|max:100|unique:traits,name_ru,' . $trait->id,
            'sort_order' => 'integer|min:0',
        ]);
        $trait->update($data);
        return back()->with('success', 'Черта обновлена.');
    }

    public function destroy(PersonalityTrait $trait): RedirectResponse
    {
        $trait->delete();

        PersonalityTrait::orderBy('sort_order')->get()
            ->each(fn($t, $i) => $t->update(['sort_order' => $i]));

        return back()->with('success', 'Черта удалена.');
    }
}
