<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InterestCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InterestCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ru' => 'required|string|max:80|unique:interest_categories,name_ru',
        ]);
        $max = InterestCategory::max('sort_order') ?? 0;
        InterestCategory::create(['name_ru' => $data['name_ru'], 'sort_order' => $max + 1]);
        return back()->with('success', 'Категория создана.');
    }

    public function update(Request $request, InterestCategory $interestCategory): RedirectResponse
    {
        $data = $request->validate([
            'name_ru' => "required|string|max:80|unique:interest_categories,name_ru,{$interestCategory->id}",
        ]);
        $interestCategory->update($data);
        return back()->with('success', 'Категория обновлена.');
    }

    public function destroy(InterestCategory $interestCategory): RedirectResponse
    {
        $interestCategory->delete();
        return back()->with('success', 'Категория удалена.');
    }
}
