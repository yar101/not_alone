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
        return Inertia::render('Admin/BanReasons/Index', [
            'chat_block_reasons' => BanReason::forChatBlock()->get(),
            'user_ban_reasons'   => BanReason::forUserBan()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'type'  => 'required|in:chat_block,user_ban',
        ]);

        $maxOrder = BanReason::where('type', $data['type'])->max('sort_order') ?? -1;
        $data['sort_order'] = $maxOrder + 1;

        BanReason::create($data);

        return back();
    }

    public function update(Request $request, BanReason $banReason)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $banReason->update($data);

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
            'items'             => 'required|array',
            'items.*.id'        => 'required|integer|exists:ban_reasons,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            BanReason::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back();
    }
}
