<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsPublicController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('News/Index');
    }

    public function feed(Request $request): JsonResponse
    {
        $sortDir = $request->get('sort', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = News::published()
            ->orderByDesc('is_pinned')
            ->orderBy('published_at', $sortDir);

        if ($search = $request->get('search')) {
            $query->where(fn ($q) =>
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%")
            );
        }

        $paginated = $query->paginate(8, [
            'id', 'title', 'excerpt', 'body', 'image',
            'is_pinned', 'published_at',
        ]);

        return response()->json([
            'data'         => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    public function show(News $news): Response
    {
        abort_if(!$news->published_at || $news->published_at->isFuture(), 404);

        $news->increment('views_count');

        return Inertia::render('News/Show', [
            'item' => $news,
        ]);
    }
}
