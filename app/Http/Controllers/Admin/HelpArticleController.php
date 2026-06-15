<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HelpArticleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:help_categories,id',
            'title_ru'    => 'required|string|max:255',
            'title_en'    => 'nullable|string|max:255',
            'content_ru'  => 'required|string',
            'content_en'  => 'nullable|string',
        ]);

        $maxOrder = HelpArticle::where('category_id', $data['category_id'])->max('sort_order') ?? -1;

        $article = new HelpArticle();
        $article->category_id = $data['category_id'];
        $article->sort_order = $maxOrder + 1;
        
        $article->setTranslation('title', 'ru', $data['title_ru']);
        if (!empty($data['title_en'])) {
            $article->setTranslation('title', 'en', $data['title_en']);
        }

        $article->setTranslation('content', 'ru', $data['content_ru']);
        if (!empty($data['content_en'])) {
            $article->setTranslation('content', 'en', $data['content_en']);
        }

        $article->save();

        return back()->with('success', 'Подраздел создан.');
    }

    public function update(Request $request, HelpArticle $article): RedirectResponse
    {
        $data = $request->validate([
            'title_ru'   => 'required|string|max:255',
            'title_en'   => 'nullable|string|max:255',
            'content_ru' => 'required|string',
            'content_en' => 'nullable|string',
        ]);

        $article->setTranslation('title', 'ru', $data['title_ru']);
        if (!empty($data['title_en'])) {
            $article->setTranslation('title', 'en', $data['title_en']);
        } else {
            $article->forgetTranslation('title', 'en');
        }

        $article->setTranslation('content', 'ru', $data['content_ru']);
        if (!empty($data['content_en'])) {
            $article->setTranslation('content', 'en', $data['content_en']);
        } else {
            $article->forgetTranslation('content', 'en');
        }

        $article->save();

        return back()->with('success', 'Подраздел обновлен.');
    }

    public function destroy(HelpArticle $article): RedirectResponse
    {
        $categoryId = $article->category_id;
        $article->delete();

        // Reorder remaining articles in category
        HelpArticle::where('category_id', $categoryId)
            ->orderBy('sort_order')
            ->get()
            ->each(fn ($art, $idx) => $art->update(['sort_order' => $idx]));

        return back()->with('success', 'Подраздел удален.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:help_articles,id',
        ])['ids'];

        foreach ($ids as $order => $id) {
            HelpArticle::where('id', $id)->update(['sort_order' => $order]);
        }

        return back();
    }
}
