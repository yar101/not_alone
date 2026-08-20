<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InterestCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterestCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ru' => ['required', 'string', 'max:80', function ($attr, $val, $fail) {
                if (DB::table('interest_categories')->whereRaw("name->>'ru' = ?", [$val])->exists()) {
                    $fail('Такая категория уже существует.');
                }
            }],
            'name_en' => 'nullable|string|max:80',
        ]);

        $max = InterestCategory::max('sort_order') ?? 0;
        InterestCategory::create([
            'name' => array_filter(['ru' => $data['name_ru'], 'en' => $data['name_en'] ?? null]),
            'sort_order' => $max + 1,
        ]);

        return back()->with('success', 'Категория создана.');
    }

    public function update(Request $request, InterestCategory $interestCategory): RedirectResponse
    {
        $data = $request->validate([
            'name_ru' => ['required', 'string', 'max:80', function ($attr, $val, $fail) use ($interestCategory) {
                if (DB::table('interest_categories')->whereRaw("name->>'ru' = ?", [$val])->where('id', '!=', $interestCategory->id)->exists()) {
                    $fail('Такая категория уже существует.');
                }
            }],
            'name_en' => 'nullable|string|max:80',
        ]);

        $interestCategory->setTranslation('name', 'ru', $data['name_ru']);
        if (! empty($data['name_en'])) {
            $interestCategory->setTranslation('name', 'en', $data['name_en']);
        }
        $interestCategory->save();

        return back()->with('success', 'Категория обновлена.');
    }

    public function destroy(InterestCategory $interestCategory): RedirectResponse
    {
        $interestCategory->delete();

        return back()->with('success', 'Категория удалена.');
    }
}
