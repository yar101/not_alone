<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminLogService;
use App\Services\IdolRatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IdolRatingController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'min:-100', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        IdolRatingService::adjust($user, 'admin_manual', $data['delta'], $data['note'] ?? null);

        AdminLogService::log(
            auth('admin')->id(),
            'rating_adjust',
            'user',
            $user->id,
            ['delta' => $data['delta'], 'note' => $data['note'] ?? null]
        );

        return back()->with('success', 'Рейтинг изменён. Новое значение: '.$user->fresh()->rating);
    }
}
