<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BanReason;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BanReasonController extends Controller
{
    public function index()
    {
        $mapFn = fn ($reason) => array_merge(
            $reason->toArray(),
            [
                'label_ru' => $reason->getTranslation('label', 'ru'),
                'label_en' => $reason->getTranslation('label', 'en', false) ?: '',
            ]
        );

        return Inertia::render('Admin/BanReasons/Index', [
            'chat_block_reasons' => BanReason::forChatBlock()->get()->map($mapFn),
            'user_ban_reasons' => BanReason::forUserBan()->get()->map($mapFn),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label_ru' => 'required|string|max:255',
            'label_en' => 'nullable|string|max:255',
            'type' => 'required|in:chat_block,user_ban',
        ]);

        $maxOrder = BanReason::where('type', $data['type'])->max('sort_order') ?? -1;

        $reason = new BanReason;
        $reason->type = $data['type'];
        $reason->sort_order = $maxOrder + 1;
        $reason->setTranslation('label', 'ru', $data['label_ru']);
        if (! empty($data['label_en'])) {
            $reason->setTranslation('label', 'en', $data['label_en']);
        }
        $reason->save();

        return back();
    }

    public function update(Request $request, BanReason $banReason)
    {
        $data = $request->validate([
            'label_ru' => 'required|string|max:255',
            'label_en' => 'nullable|string|max:255',
        ]);

        $banReason->setTranslation('label', 'ru', $data['label_ru']);
        if (isset($data['label_en']) && $data['label_en'] !== '') {
            $banReason->setTranslation('label', 'en', $data['label_en']);
        } else {
            $banReason->forgetTranslation('label', 'en');
        }
        $banReason->save();

        return back();
    }

    public function destroy(BanReason $banReason)
    {
        $banReason->delete();

        return back();
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:ban_reasons,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            BanReason::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back();
    }
}
