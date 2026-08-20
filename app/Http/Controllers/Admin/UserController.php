<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()->with('bannedBy');

        if ($q = $request->q) {
            $query->where(fn($qb) =>
                $qb->where('name', 'ilike', "%{$q}%")
                   ->orWhere('email', 'ilike', "%{$q}%")
            );
        }

        if ($request->filled('is_idol')) {
            $query->where('is_idol', $request->boolean('is_idol'));
        }
        if ($request->filled('is_banned')) {
            $query->where('is_banned', $request->boolean('is_banned'));
        }
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }
        if ($request->filled('registered_from')) {
            $query->whereDate('created_at', '>=', $request->registered_from);
        }
        if ($request->filled('registered_to')) {
            $query->whereDate('created_at', '<=', $request->registered_to);
        }

        $users = $query->orderByDesc('created_at')->paginate(30)->withQueryString()
            ->through(fn(User $u) => [
                'id'                       => $u->id,
                'name'                     => $u->name,
                'email'                    => $u->email,
                'avatar_url'               => $u->avatar_url,
                'gender'                   => $u->gender,
                'is_idol'                  => $u->is_idol,
                'rating'                   => $u->rating,
                'is_banned'                => $u->is_banned,
                'banned_until'             => $u->banned_until,
                'ban_reason'               => $u->ban_reason,
                'bannedBy'                 => $u->bannedBy ? ['name' => $u->bannedBy->name] : null,
                'idol_quiz_cooldown_until' => $u->idol_quiz_cooldown_until,
                'created_at'               => $u->created_at,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users'  => $users,
            'filter' => $request->only(['q', 'is_idol', 'is_banned', 'gender', 'registered_from', 'registered_to']),
        ]);
    }

    public function updateCooldown(Request $request, User $user): RedirectResponse
    {
        $request->validate(['minutes' => 'required|integer']);

        $base = $user->idol_quiz_cooldown_until && $user->idol_quiz_cooldown_until->isFuture()
            ? $user->idol_quiz_cooldown_until
            : now();

        $newCooldown = $base->copy()->addMinutes($request->integer('minutes'));

        $user->update([
            'idol_quiz_cooldown_until' => $newCooldown->isPast() ? null : $newCooldown,
        ]);

        AdminLogService::log(
            auth('admin')->id(),
            'cooldown_update',
            'user',
            $user->id,
            ['minutes' => $request->integer('minutes')]
        );

        return back();
    }

    public function clearCooldown(User $user): RedirectResponse
    {
        $user->update(['idol_quiz_cooldown_until' => null]);

        AdminLogService::log(auth('admin')->id(), 'cooldown_clear', 'user', $user->id);

        return back();
    }

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
            $query->whereDate('birth_date', '<=', now()->subYears((int) $request->age_from)->toDateString());
        }

        if ($request->filled('age_to')) {
            $query->whereDate('birth_date', '>=', now()->subYears((int) $request->age_to + 1)->addDay()->toDateString());
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

    public function show(User $user): Response
    {
        $user->load(['bannedBy', 'idolApplication', 'services.category', 'services.timeUnit']);
        $ratingLogs = $user->ratingLogs()->orderByDesc('created_at')->limit(50)->get();

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'avatar_url'         => $user->avatar_url,
                'is_idol'            => $user->is_idol,
                'rating'             => $user->rating,
                'gender'             => $user->gender,
                'age'                => $user->age,
                'created_at'         => $user->created_at,
                'idol_quiz_passed_at'=> $user->idol_quiz_passed_at,
                'is_banned'          => $user->is_banned,
                'banned_at'          => $user->banned_at,
                'banned_until'       => $user->banned_until,
                'ban_reason'         => $user->ban_reason,
                'banned_by'          => $user->bannedBy ? ['name' => $user->bannedBy->name] : null,
                'application'        => $user->idolApplication ? [
                    'status'           => $user->idolApplication->status,
                    'rejection_reason' => $user->idolApplication->rejection_reason,
                    'created_at'       => $user->idolApplication->created_at,
                ] : null,
                'rating_logs' => $ratingLogs->map(fn($log) => [
                    'event'      => $log->event,
                    'delta'      => $log->delta,
                    'note'       => $log->note,
                    'created_at' => $log->created_at,
                ]),
                'services' => $user->services->map(fn($s) => [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'category'  => $s->category?->name,
                    'time_unit' => $s->timeUnit?->name,
                    'price'     => $s->price,
                    'is_active' => $s->is_active,
                    'status'    => $s->status,
                ]),
            ],
        ]);
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason'       => 'required|string|max:500',
            'banned_until' => 'nullable|date|after:now',
        ]);

        $user->update([
            'is_banned'    => true,
            'banned_at'    => now(),
            'banned_until' => $validated['banned_until'] ?? null,
            'ban_reason'   => $validated['reason'],
            'banned_by'    => auth('admin')->id(),
        ]);

        // Immediately invalidate all active sessions of the banned user
        DB::table('sessions')->where('user_id', $user->id)->delete();

        AdminLogService::log(
            auth('admin')->id(),
            'ban_user',
            'user',
            $user->id,
            ['reason' => $validated['reason'], 'until' => $validated['banned_until'] ?? null]
        );

        return back()->with('success', 'Пользователь заблокирован.');
    }

    public function unban(User $user): RedirectResponse
    {
        $user->update([
            'is_banned'    => false,
            'banned_at'    => null,
            'banned_until' => null,
            'ban_reason'   => null,
            'banned_by'    => null,
        ]);

        AdminLogService::log(auth('admin')->id(), 'unban_user', 'user', $user->id);

        return back()->with('success', 'Пользователь разблокирован.');
    }

    public function resetQuizProgress(User $user): RedirectResponse
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
