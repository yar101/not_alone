<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewEpithet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReviewEpithetController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Reviews/Epithets', [
            'epithets' => ReviewEpithet::all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $data['sort_order'] = (ReviewEpithet::withoutGlobalScopes()->max('sort_order') ?? -1) + 1;

        ReviewEpithet::create($data);

        return back();
    }

    public function update(Request $request, ReviewEpithet $epithet)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $epithet->update($data);

        return back();
    }

    public function destroy(ReviewEpithet $epithet)
    {
        $epithet->delete();

        return back();
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:review_epithets,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                ReviewEpithet::withoutGlobalScopes()
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        Cache::forget('review_epithets_list');

        return back();
    }
}
