<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = User::query()->select(['id', 'name', 'email', 'is_idol', 'gender', 'birth_date', 'avatar_path', 'created_at']);

        if ($q = $request->q) {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'ilike', "%{$q}%")
                   ->orWhere('email', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('is_idol')) {
            $query->where('is_idol', (bool) $request->is_idol);
        }

        if ($gender = $request->gender) {
            $query->where('gender', $gender);
        }

        if ($request->filled('age_from')) {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(birth_date)) >= ?', [(int) $request->age_from]);
        }

        if ($request->filled('age_to')) {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(birth_date)) <= ?', [(int) $request->age_to]);
        }

        if ($request->filled('registered_from')) {
            $query->whereDate('created_at', '>=', $request->registered_from);
        }

        if ($request->filled('registered_to')) {
            $query->whereDate('created_at', '<=', $request->registered_to);
        }

        $paginator = $query->orderByDesc('created_at')->paginate(20);

        $data = $paginator->map(fn(User $u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'is_idol'    => $u->is_idol,
            'gender'     => $u->gender,
            'age'        => $u->age,
            'avatar_url' => $u->avatar_url,
            'created_at' => $u->created_at,
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

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
