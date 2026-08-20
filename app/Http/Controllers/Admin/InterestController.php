<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Interest;
use App\Models\InterestCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InterestController extends Controller
{
    public function index(): Response
    {
        $categories = InterestCategory::with(['interests' => fn ($q) => $q->withCount('users')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cat) => array_merge(
                $cat->toArray(),
                [
                    'name_ru' => $cat->getTranslation('name', 'ru'),
                    'name_en' => $cat->getTranslation('name', 'en', false) ?: '',
                    'interests' => collect($cat->interests)->map(fn ($i) => array_merge(
                        $i->toArray(),
                        ['name_ru' => $i->getTranslation('name', 'ru'), 'name_en' => $i->getTranslation('name', 'en', false) ?: '']
                    ))->values(),
                ]
            ));

        return Inertia::render('Admin/Interests/Index', ['categories' => $categories]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:interest_categories,id',
            'name_ru' => ['required', 'string', 'max:100', function ($attr, $val, $fail) {
                if (DB::table('interests')->whereRaw("name->>'ru' = ?", [$val])->exists()) {
                    $fail('Такой интерес уже существует.');
                }
            }],
            'name_en' => 'nullable|string|max:100',
        ]);

        $sortOrder = Interest::where('category_id', $data['category_id'])->max('sort_order') + 1;

        Interest::create([
            'category_id' => $data['category_id'],
            'name' => array_filter(['ru' => $data['name_ru'], 'en' => $data['name_en'] ?? null]),
            'sort_order' => $sortOrder,
        ]);

        return back()->with('success', 'Интерес добавлен.');
    }

    public function update(Request $request, Interest $interest): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:interest_categories,id',
            'name_ru' => ['required', 'string', 'max:100', function ($attr, $val, $fail) use ($interest) {
                if (DB::table('interests')->whereRaw("name->>'ru' = ?", [$val])->where('id', '!=', $interest->id)->exists()) {
                    $fail('Такой интерес уже существует.');
                }
            }],
            'name_en' => 'nullable|string|max:100',
        ]);

        $interest->category_id = $data['category_id'];
        $interest->setTranslation('name', 'ru', $data['name_ru']);
        if (! empty($data['name_en'])) {
            $interest->setTranslation('name', 'en', $data['name_en']);
        }
        $interest->save();

        return back()->with('success', 'Интерес обновлён.');
    }

    public function destroy(Interest $interest): RedirectResponse
    {
        $categoryId = $interest->category_id;
        $interest->delete();
        Interest::where('category_id', $categoryId)->orderBy('sort_order')
            ->get()->each(fn ($i, $idx) => $i->update(['sort_order' => $idx]));

        return back()->with('success', 'Интерес удалён.');
    }
}
