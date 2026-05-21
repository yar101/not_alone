<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = HelpCategory::with(['articles' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cat) => array_merge(
                $cat->toArray(),
                [
                    'title_ru' => $cat->getTranslation('title', 'ru'),
                    'title_en' => $cat->getTranslation('title', 'en', false) ?: '',
                    'articles' => collect($cat->articles)->map(fn ($art) => array_merge(
                        $art->toArray(),
                        [
                            'title_ru' => $art->getTranslation('title', 'ru'),
                            'title_en' => $art->getTranslation('title', 'en', false) ?: '',
                            'content_ru' => $art->getTranslation('content', 'ru', false) ?: '',
                            'content_en' => $art->getTranslation('content', 'en', false) ?: '',
                        ]
                    ))->values()->toArray(),
                ]
            ));

        return Inertia::render('Admin/Help/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_ru' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
        ]);

        $maxOrder = HelpCategory::max('sort_order') ?? -1;

        $category = new HelpCategory();
        $category->sort_order = $maxOrder + 1;
        $category->setTranslation('title', 'ru', $data['title_ru']);
        if (!empty($data['title_en'])) {
            $category->setTranslation('title', 'en', $data['title_en']);
        }
        $category->save();

        return back()->with('success', 'Раздел создан.');
    }

    public function update(Request $request, HelpCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'title_ru' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
        ]);

        $category->setTranslation('title', 'ru', $data['title_ru']);
        if (!empty($data['title_en'])) {
            $category->setTranslation('title', 'en', $data['title_en']);
        } else {
            $category->forgetTranslation('title', 'en');
        }
        $category->save();

        return back()->with('success', 'Раздел обновлен.');
    }

    public function destroy(HelpCategory $category): RedirectResponse
    {
        $category->delete();
        return back()->with('success', 'Раздел удален.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:help_categories,id',
        ])['ids'];

        foreach ($ids as $order => $id) {
            HelpCategory::where('id', $id)->update(['sort_order' => $order]);
        }

        return back();
    }
}
