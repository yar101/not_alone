<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FanOutAdminBroadcast;
use App\Models\AdminBroadcast;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MessageController extends Controller
{
    public function index()
    {
        $broadcasts = AdminBroadcast::with('admin')
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id'             => $b->id,
                'title'          => $b->title,
                'body'           => $b->body,
                'target'         => $b->target,
                'target_user'    => $b->target_user_id ? User::find($b->target_user_id)?->only('id', 'name', 'email') : null,
                'target_filters' => $b->target_filters,
                'admin'          => ['name' => $b->admin->name],
                'created_at'     => $b->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Messages/Index', [
            'broadcasts' => $broadcasts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'body'           => 'required|string|max:10000',
            'target'         => 'required|in:all,user,filtered',
            'target_user_id' => 'nullable|required_if:target,user|exists:users,id',
            'target_filters' => 'nullable|required_if:target,filtered|array',
        ]);

        $broadcast = AdminBroadcast::create([
            'admin_id'       => auth('admin')->id(),
            'title'          => $validated['title'],
            'body'           => $validated['body'],
            'target'         => $validated['target'],
            'target_user_id' => $validated['target'] === 'user' ? $validated['target_user_id'] : null,
            'target_filters' => $validated['target'] === 'filtered' ? $validated['target_filters'] : null,
        ]);

        FanOutAdminBroadcast::dispatch($broadcast);

        return back()->with('success', 'Рассылка поставлена в очередь.');
    }
}
