<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\AdminLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceModerationController extends Controller
{
    public function index(): Response
    {
        $services = Service::with(['user', 'category', 'timeUnit'])
            ->where('status', 'pending')
            ->paginate(30)
            ->through(fn(Service $s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'price'      => $s->price,
                'created_at' => $s->created_at,
                'category'   => $s->category?->name,
                'time_unit'  => $s->timeUnit?->name,
                'user'       => $s->user ? [
                    'id'         => $s->user->id,
                    'name'       => $s->user->name,
                    'email'      => $s->user->email,
                    'avatar_url' => $s->user->avatar_url,
                ] : null,
            ]);

        return Inertia::render('Admin/Services/Index', [
            'moderation_services' => $services,
            'active_tab'          => 'moderation',
        ]);
    }

    public function approve(Service $service): RedirectResponse
    {
        $service->update([
            'status'       => 'approved',
            'moderated_by' => auth('admin')->id(),
            'moderated_at' => now(),
        ]);

        AdminLogService::log(auth('admin')->id(), 'approve_service', 'service', $service->id);

        return back()->with('success', 'Услуга одобрена.');
    }

    public function reject(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $service->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'moderated_by'     => auth('admin')->id(),
            'moderated_at'     => now(),
        ]);

        AdminLogService::log(
            auth('admin')->id(),
            'reject_service',
            'service',
            $service->id,
            ['reason' => $validated['rejection_reason']]
        );

        return back()->with('success', 'Услуга отклонена.');
    }
}
