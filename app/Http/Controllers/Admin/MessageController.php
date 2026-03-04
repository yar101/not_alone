<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
                'id' => $b->id,
                'title' => $b->title,
                'body' => $b->body,
                'target' => $b->target,
                'target_user' => $b->target_user_id ? User::find($b->target_user_id)?->only('id', 'name', 'email') : null,
                'admin' => ['name' => $b->admin->name],
                'created_at' => $b->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Messages/Index', [
            'broadcasts' => $broadcasts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:10000',
            'target' => 'required|in:all,user',
            'target_user_id' => 'nullable|required_if:target,user|exists:users,id',
        ]);

        AdminBroadcast::create([
            'admin_id' => auth('admin')->id(),
            'title' => $validated['title'],
            'body' => $validated['body'],
            'target' => $validated['target'],
            'target_user_id' => $validated['target'] === 'user' ? $validated['target_user_id'] : null,
        ]);

        return back()->with('success', 'Рассылка отправлена.');
    }
}
