<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $perPage = 8;

        $query = News::published()
            ->orderByDesc('is_pinned')
            ->orderBy('published_at', $sortDir)
            ->orderBy('id', $sortDir);

        if ($search = $request->get('search')) {
            $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn ($q) => $q->where('title', $likeOp, "%{$search}%")
                ->orWhere('excerpt', $likeOp, "%{$search}%")
                ->orWhere('body', $likeOp, "%{$search}%")
            );
        }

        $paginated = $query->cursorPaginate($perPage, [
            'id', 'title', 'excerpt', 'body', 'image',
            'is_pinned', 'published_at',
        ]);

        return response()->json([
            'data' => $paginated->items(),
            'has_more' => $paginated->hasMorePages(),
            'next_cursor' => $paginated->nextCursor()?->encode(),
            'current_page' => (int) $request->input('page', 1),
            'last_page' => $paginated->hasMorePages() ? 999999 : (int) $request->input('page', 1),
        ]);
    }

    public function show(News $news): Response
    {
        abort_if(! $news->published_at || $news->published_at->isFuture(), 404);

        $news->increment('views_count');

        return Inertia::render('News/Show', [
            'item' => $news,
        ]);
    }
}
