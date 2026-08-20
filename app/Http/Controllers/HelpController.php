<?php

namespace App\Http\Controllers;

use App\Models\HelpCategory;
use Illuminate\Http\JsonResponse;

class HelpController extends Controller
{
    public function index(): JsonResponse
    {
        $locale = app()->getLocale();

        $categories = HelpCategory::with(['articles' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'title' => $cat->getTranslation('title', $locale),
                'questions' => collect($cat->articles)->map(fn ($art) => [
                    'id' => $art->id,
                    'q' => $art->getTranslation('title', $locale),
                    'a' => $art->getTranslation('content', $locale),
                ])->values()->toArray(),
            ]);

        return response()->json([
            'categories' => $categories,
        ]);
    }
}
