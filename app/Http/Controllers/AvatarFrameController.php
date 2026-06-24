<?php

namespace App\Http\Controllers;

use App\Models\AvatarFrame;
use App\Services\AvatarFrames\ConditionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarFrameController extends Controller
{
    /**
     * Получить список всех рамок и статус пользователя.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Получаем все активные рамки
        $frames = AvatarFrame::where('is_active', true)
            ->orderBy('type') // Сначала базовые, потом платные, потом ачивки
            ->get()
            ->map(function ($f) {
                $f->image_url = Storage::url($f->image_path);
                
                // Добавляем текстовое описание условия для фронта
                if ($f->type === 'achievement' && $f->condition_class) {
                    $condition = ConditionRegistry::get($f->condition_class);
                    $f->condition_description = $condition ? $condition->getDescription() : '';
                }

                return $f;
            });

        // Получаем ID рамок, которые юзер уже открыл (заслужил или купил)
        $unlockedFrameIds = $user->avatarFrames()->pluck('avatar_frames.id')->toArray();

        return response()->json([
            'frames' => $frames,
            'unlocked_ids' => $unlockedFrameIds,
            'active_frame_path' => $user->active_frame_path
        ]);
    }

    /**
     * Надеть рамку.
     */
    public function equip(Request $request, AvatarFrame $avatarFrame)
    {
        $user = $request->user();

        // Проверяем, есть ли у юзера доступ к этой рамке
        // Базовые (free) доступны всегда
        if ($avatarFrame->type !== 'free') {
            $owns = $user->avatarFrames()->where('avatar_frame_id', $avatarFrame->id)->exists();
            if (!$owns) {
                return response()->json(['message' => 'Вы еще не открыли эту рамку.'], 403);
            }
        }

        $user->update([
            'active_frame_path' => $avatarFrame->image_path
        ]);

        return response()->json([
            'message' => 'Рамка надета!', 
            'active_frame_path' => $user->active_frame_path
        ]);
    }

    /**
     * Снять текущую рамку.
     */
    public function unequip(Request $request)
    {
        $user = $request->user();
        
        $user->update([
            'active_frame_path' => null
        ]);

        return response()->json(['message' => 'Рамка снята.']);
    }
}
