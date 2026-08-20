<?php

namespace App\Http\Controllers\Idol;

use App\Http\Controllers\Controller;
use App\Models\IdolApplication;
use App\Models\IdolArticleVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('idolApplication');
        $application = $user->idolApplication;

        $phase = 'none';
        $sessionData = null;
        $cooldownUntil = null;

        if ($user->is_idol || ($application && $application->status === 'approved')) {
            $phase = 'approved';
        } elseif ($application && $application->status === 'pending') {
            $phase = 'pending';
        } elseif ($application && $application->status === 'rejected') {
            $phase = 'rejected';
        } elseif ($user->idol_quiz_passed_at) {
            $phase = 'photo';
        } else {
            $activeSession = $user->idolQuizSessions()->where('status', 'active')->latest()->first();
            if ($activeSession) {
                $phase = 'quiz';
                $sessionData = $activeSession->toFrontendData();
            } elseif ($user->idol_quiz_cooldown_until && now()->lt($user->idol_quiz_cooldown_until)) {
                $phase = 'cooldown';
                $cooldownUntil = $user->idol_quiz_cooldown_until->toIso8601String();
            }
        }

        $activeVersion = IdolArticleVersion::active()->first();
        $articleHtml = $activeVersion?->html ?? '';

        return Inertia::render('Idol/Apply', [
            'phase' => $phase,
            'session' => $sessionData,
            'cooldown_until' => $cooldownUntil,
            'rejection_reason' => $application?->rejection_reason,
            'quiz_passed' => (bool) $user->idol_quiz_passed_at,
            'article_html' => $articleHtml,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->idol_quiz_passed_at) {
            return back()->withErrors(['error' => 'Сначала пройдите тест.']);
        }

        $existing = $user->idolApplication;
        if ($existing) {
            if ($existing->status === 'rejected') {
                $existing->delete();
            } else {
                return back()->withErrors(['error' => 'Заявка уже подана.']);
            }
        }

        $validated = $request->validate([
            'face_photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $path = $request->file('face_photo')->store('idol-photos');

        IdolApplication::create([
            'user_id' => $user->id,
            'face_photo_path' => $path,
            'status' => 'pending',
        ]);

        return redirect()->route('idol.apply')->with('success', 'Заявка подана!');
    }
}
