<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Interest;
use App\Models\InterestCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterestController extends Controller
{
    public function index(): Response
    {
        $categories = InterestCategory::with(['interests' => fn($q) => $q->withCount('users')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Interests/Index', ['categories' => $categories]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:interest_categories,id',
            'name_ru'     => 'required|string|max:100|unique:interests,name_ru',
        ]);
        $data['sort_order'] = Interest::where('category_id', $data['category_id'])->max('sort_order') + 1;
        Interest::create($data);
        return back()->with('success', 'Интерес добавлен.');
    }

    public function update(Request $request, Interest $interest): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:interest_categories,id',
            'name_ru'     => 'required|string|max:100|unique:interests,name_ru,' . $interest->id,
        ]);
        $interest->update($data);
        return back()->with('success', 'Интерес обновлён.');
    }

    public function destroy(Interest $interest): RedirectResponse
    {
        $categoryId = $interest->category_id;
        $interest->delete();
        Interest::where('category_id', $categoryId)->orderBy('sort_order')
            ->get()->each(fn($i, $idx) => $i->update(['sort_order' => $idx]));
        return back()->with('success', 'Интерес удалён.');
    }
}
