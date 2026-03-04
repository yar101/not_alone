<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function resetQuizProgress(User $user)
    {
        $user->idolQuizSessions()->where('status', 'active')->update(['status' => 'failed', 'completed_at' => now()]);
        $user->update([
            'idol_quiz_passed_at' => null,
            'idol_quiz_cooldown_until' => null,
        ]);
        $user->idolApplication()->whereIn('status', ['pending', 'rejected'])->delete();

        return back()->with('success', 'Прогресс пользователя сброшен.');
    }
}
