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

        $applications = $query->paginate(20)->through(fn ($app) => [
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
            'face_photo_url' => Storage::url($app->face_photo_path),
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
                'face_photo_url' => Storage::url($application->face_photo_path),
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
        if ($application->status !== 'pending') {
            return back()->withErrors(['error' => 'Заявка уже обработана.']);
        }

        $adminId = auth('admin')->id();

        DB::transaction(function () use ($application, $adminId) {
            $application->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            $application->user?->update(['is_idol' => true]);
        });

        if ($application->user) {
            $application->user->notify(new IdolApprovedNotification);
            $this->safeBroadcast(new NewNotification('private', $application->user->id));
        }

        AdminLogService::log($adminId, 'approve_application', 'application', $application->id);

        return back()->with('success', 'Заявка одобрена.');
    }

    public function reject(Request $request, IdolApplication $application)
    {
        if ($application->status !== 'pending') {
            return back()->withErrors(['error' => 'Заявка уже обработана.']);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'reset_quiz' => 'boolean',
        ]);

        $adminId = auth('admin')->id();

        DB::transaction(function () use ($application, $validated, $request, $adminId) {
            $application->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            if ($request->boolean('reset_quiz') && $application->user) {
                $user = $application->user;
                $user->idolQuizSessions()->where('status', 'active')
                    ->update(['status' => 'failed', 'completed_at' => now()]);
                $user->update(['idol_quiz_passed_at' => null, 'idol_quiz_cooldown_until' => null]);
            }
        });

        if ($application->user) {
            $application->user->notify(new IdolRejectedNotification($validated['rejection_reason']));
            $this->safeBroadcast(new NewNotification('private', $application->user->id));
        }

        AdminLogService::log(
            $adminId,
            'reject_application',
            'application',
            $application->id,
            ['reason' => $validated['rejection_reason']]
        );

        return back()->with('success', 'Заявка отклонена.');
    }
}
