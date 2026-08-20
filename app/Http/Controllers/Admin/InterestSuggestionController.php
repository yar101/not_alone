<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InterestSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterestSuggestionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');
        $search = $request->get('search', '');

        $suggestions = InterestSuggestion::with('user:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Interests/Suggestions', [
            'suggestions' => $suggestions,
            'currentStatus' => $status,
            'pendingCount' => InterestSuggestion::where('status', 'pending')->count(),
            'search' => $search,
        ]);
    }

    public function implement(InterestSuggestion $interestSuggestion): RedirectResponse
    {
        $interestSuggestion->update(['status' => 'implemented']);

        return back()->with('success', 'Отмечено как реализованное.');
    }

    public function approve(InterestSuggestion $interestSuggestion): RedirectResponse
    {
        $interestSuggestion->update(['status' => 'approved']);

        return back()->with('success', 'Предложение одобрено.');
    }

    public function reject(InterestSuggestion $interestSuggestion): RedirectResponse
    {
        $interestSuggestion->update(['status' => 'rejected']);

        return back()->with('success', 'Предложение отклонено.');
    }

    public function reopen(InterestSuggestion $interestSuggestion): RedirectResponse
    {
        $interestSuggestion->update(['status' => 'pending']);

        return back()->with('success', 'Предложение возвращено на рассмотрение.');
    }

    public function destroy(InterestSuggestion $interestSuggestion): RedirectResponse
    {
        $interestSuggestion->delete();

        return back()->with('success', 'Предложение удалено.');
    }
}
