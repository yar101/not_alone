<?php

namespace App\Http\Controllers\Admin;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\IdolApplication;
use App\Notifications\IdolApprovedNotification;
use App\Notifications\IdolRejectedNotification;
use App\Services\AdminLogService;
use App\Traits\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    use SafeBroadcast;

    public function index(Request $request)
    {
        $query = IdolApplication::with(['user', 'reviewer'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->paginate(20)->withQueryString()->through(fn ($app) => [
            'id' => $app->id,
            'user' => $app->user ? [
                'id' => $app->user->id,
                'name' => $app->user->name,
                'email' => $app->user->email,
                'avatar_url' => $app->user->avatar_url,
            ] : [
                'id' => null,
                'name' => 'Удалённый пользователь',
                'email' => '',
                'avatar_url' => null,
            ],
            'face_photo_url' => $app->face_photo_path ? Storage::url($app->face_photo_path) : null,
            'status' => $app->status,
            'rejection_reason' => $app->rejection_reason,
            'created_at' => $app->created_at->toIso8601String(),
            'reviewed_at' => $app->reviewed_at?->toIso8601String(),
            'reviewer' => $app->reviewer ? ['name' => $app->reviewer->name] : null,
        ]);

        return Inertia::render('Admin/Applications/Index', [
            'applications' => $applications,
            'filter_status' => $request->status,
        ]);
    }

    public function show(IdolApplication $application)
    {
        $application->load(['user', 'reviewer']);

        return Inertia::render('Admin/Applications/Show', [
            'application' => [
                'id' => $application->id,
                'user' => $application->user ? [
                    'id' => $application->user->id,
                    'name' => $application->user->name,
                    'email' => $application->user->email,
                    'avatar_url' => $application->user->avatar_url,
                    'created_at' => $application->user->created_at->toIso8601String(),
                ] : [
                    'id' => null,
                    'name' => 'Удалённый пользователь',
                    'email' => '',
                    'avatar_url' => null,
                    'created_at' => null,
                ],
                'face_photo_url' => $application->face_photo_path ? Storage::url($application->face_photo_path) : null,
                'status' => $application->status,
                'rejection_reason' => $application->rejection_reason,
                'created_at' => $application->created_at->toIso8601String(),
                'reviewed_at' => $application->reviewed_at?->toIso8601String(),
                'reviewer' => $application->reviewer ? ['name' => $application->reviewer->name] : null,
            ],
        ]);
    }

    public function approve(Request $request, IdolApplication $application)
    {
        $adminId = auth('admin')->id();
        $alreadyHandled = false;

        DB::transaction(function () use ($application, $adminId, &$alreadyHandled) {
            $lockedApp = IdolApplication::where('id', $application->id)->lockForUpdate()->first();
            if (! $lockedApp || $lockedApp->status !== 'pending') {
                $alreadyHandled = true;

                return;
            }

            $lockedApp->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            $lockedApp->user?->update(['is_idol' => true]);

            if ($lockedApp->user) {
                DB::afterCommit(function () use ($lockedApp) {
                    try {
                        $lockedApp->user?->notify(new IdolApprovedNotification);
                    } catch (\Throwable $e) {
                        Log::warning('IdolApprovedNotification failed: '.$e->getMessage());
                    }
                    $this->safeBroadcast(new NewNotification('private', $lockedApp->user_id));
                });
            }

            AdminLogService::log($adminId, 'approve_application', 'application', $lockedApp->id);
        });

        if ($alreadyHandled) {
            return back()->withErrors(['error' => 'Заявка уже обработана.']);
        }

        return back()->with('success', 'Заявка одобрена.');
    }

    public function reject(Request $request, IdolApplication $application)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'reset_quiz' => 'boolean',
        ]);

        $adminId = auth('admin')->id();
        $alreadyHandled = false;

        DB::transaction(function () use ($application, $validated, $request, $adminId, &$alreadyHandled) {
            $lockedApp = IdolApplication::where('id', $application->id)->lockForUpdate()->first();
            if (! $lockedApp || $lockedApp->status !== 'pending') {
                $alreadyHandled = true;

                return;
            }

            $lockedApp->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            if ($request->boolean('reset_quiz') && $lockedApp->user) {
                $user = $lockedApp->user;
                $user->idolQuizSessions()->where('status', 'active')
                    ->update(['status' => 'failed', 'completed_at' => now()]);
                $user->update(['idol_quiz_passed_at' => null, 'idol_quiz_cooldown_until' => null]);
            }

            if ($lockedApp->user) {
                DB::afterCommit(function () use ($lockedApp, $validated) {
                    try {
                        $lockedApp->user?->notify(new IdolRejectedNotification($validated['rejection_reason']));
                    } catch (\Throwable $e) {
                        Log::warning('IdolRejectedNotification failed: '.$e->getMessage());
                    }
                    $this->safeBroadcast(new NewNotification('private', $lockedApp->user_id));
                });
            }

            AdminLogService::log(
                $adminId,
                'reject_application',
                'application',
                $lockedApp->id,
                ['reason' => $validated['rejection_reason']]
            );
        });

        if ($alreadyHandled) {
            return back()->withErrors(['error' => 'Заявка уже обработана.']);
        }

        return back()->with('success', 'Заявка отклонена.');
    }
}
