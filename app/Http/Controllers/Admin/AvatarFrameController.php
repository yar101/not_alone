<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvatarFrame;
use App\Services\AvatarFrames\ConditionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AvatarFrameController extends Controller
{
    public function index()
    {
        $frames = AvatarFrame::latest()->get()->map(function($f) {
            $f->image_url = Storage::url($f->image_path);
            return $f;
        });

        // Отправляем список условий на фронт для селекта
        $conditions = collect(ConditionRegistry::all())->map(function($cond) {
            return [
                'key' => $cond->getKey(),
                'description' => $cond->getDescription()
            ];
        })->values();

        return Inertia::render('Admin/AvatarFrames/Index', [
            'frames' => $frames,
            'conditions' => $conditions
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|max:2048|mimes:png',
            'type' => 'required|in:free,paid,achievement,promo',
            'price' => 'numeric|min:0',
            'condition_class' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $path = $request->file('image')->store('avatar_frames');

        AvatarFrame::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'image_path' => $path,
            'type' => $validated['type'],
            'price' => $validated['price'] ?? 0,
            'condition_class' => $validated['type'] === 'achievement' ? $validated['condition_class'] : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Рамка успешно загружена');
    }

    public function update(Request $request, AvatarFrame $avatarFrame)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048|mimes:png',
            'type' => 'required|in:free,paid,achievement,promo',
            'price' => 'numeric|min:0',
            'condition_class' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            Storage::delete($avatarFrame->image_path);
            $avatarFrame->image_path = $request->file('image')->store('avatar_frames');
        }

        $avatarFrame->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'type' => $validated['type'],
            'price' => $validated['price'] ?? 0,
            'condition_class' => $validated['type'] === 'achievement' ? $validated['condition_class'] : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Рамка обновлена');
    }

    public function destroy(AvatarFrame $avatarFrame)
    {
        Storage::delete($avatarFrame->image_path);
        $avatarFrame->delete();

        return redirect()->back()->with('success', 'Рамка удалена');
    }
}
