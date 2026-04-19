<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersonalityTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PersonalityTraitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Traits/Index', [
            'traits' => PersonalityTrait::withCount('users')->orderBy('sort_order')->get()->map(fn ($t) => array_merge(
                $t->toArray(),
                ['name_ru' => $t->getTranslation('name', 'ru'), 'name_en' => $t->getTranslation('name', 'en', false) ?: '']
            )),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ru'    => ['required', 'string', 'max:100', function ($attr, $val, $fail) {
                if (DB::table('traits')->whereRaw("name->>'ru' = ?", [$val])->exists()) $fail('Такая черта уже существует.');
            }],
            'name_en'    => 'nullable|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        PersonalityTrait::create([
            'name'       => array_filter(['ru' => $data['name_ru'], 'en' => $data['name_en'] ?? null]),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Черта добавлена.');
    }

    public function update(Request $request, PersonalityTrait $trait): RedirectResponse
    {
        $data = $request->validate([
            'name_ru'    => ['required', 'string', 'max:100', function ($attr, $val, $fail) use ($trait) {
                if (DB::table('traits')->whereRaw("name->>'ru' = ?", [$val])->where('id', '!=', $trait->id)->exists()) $fail('Такая черта уже существует.');
            }],
            'name_en'    => 'nullable|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $trait->setTranslation('name', 'ru', $data['name_ru']);
        if (!empty($data['name_en'])) {
            $trait->setTranslation('name', 'en', $data['name_en']);
        }
        $trait->sort_order = $data['sort_order'] ?? $trait->sort_order;
        $trait->save();

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
