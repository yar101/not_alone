<?php

namespace App\Http\Controllers;

use App\Models\UserReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'reported_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$userId])],
            'reason' => ['required', Rule::in(['spam', 'inappropriate', 'fraud', 'harassment', 'other'])],
            'details' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $hasPending = UserReport::where('reporter_id', $userId)
            ->where('reported_id', $validated['reported_id'])
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return back()->withErrors([
                'reported_id' => 'Вы уже отправили жалобу на этого пользователя. Она находится на рассмотрении.',
            ]);
        }

        UserReport::create([
            ...$validated,
            'reporter_id' => $userId,
        ]);

        return back()->with('success', 'Жалоба отправлена.');
    }
}
