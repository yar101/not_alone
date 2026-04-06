<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    public function index(): Response
    {
        $news = News::orderByDesc('created_at')
            ->get(['id', 'title', 'body', 'excerpt', 'image',
                   'is_pinned', 'views_count', 'published_at', 'created_at']);

        return Inertia::render('Admin/News/Index', [
            'news' => $news,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'excerpt'      => 'nullable|string|max:500',
            'image'        => 'nullable|file|image|max:4096',
            'is_pinned'    => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $data['published_at'] = $data['published_at'] ?: null;
        $data['is_pinned']    = (bool) ($data['is_pinned'] ?? false);

        if ($request->hasFile('image')) {
            $data['image'] = '/storage/' . $request->file('image')->store('news', 'public');
        } else {
            unset($data['image']);
        }

        News::create($data);

        return back()->with('success', 'Новость добавлена.');
    }

    public function update(Request $request, News $news): RedirectResponse
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'excerpt'      => 'nullable|string|max:500',
            'image'        => 'nullable|file|image|max:4096',
            'is_pinned'    => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $data['published_at'] = $data['published_at'] ?: null;
        $data['is_pinned']    = (bool) ($data['is_pinned'] ?? false);

        if ($request->hasFile('image')) {
            if ($news->image && str_starts_with($news->image, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $news->image));
            }
            $data['image'] = '/storage/' . $request->file('image')->store('news', 'public');
        } else {
            unset($data['image']);
        }

        $news->update($data);

        return back()->with('success', 'Новость обновлена.');
    }

    public function destroy(News $news): RedirectResponse
    {
        if ($news->image && str_starts_with($news->image, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $news->image));
        }

        $news->delete();

        return back()->with('success', 'Новость удалена.');
    }

}
