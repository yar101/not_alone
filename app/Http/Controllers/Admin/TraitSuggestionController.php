<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TraitSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TraitSuggestionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');
        $search = $request->get('search', '');

        $suggestions = TraitSuggestion::with('user:id,name')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($search, fn($q) => $q->where('name', 'ilike', "%{$search}%"))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Traits/Suggestions', [
            'suggestions'  => $suggestions,
            'currentStatus' => $status,
            'pendingCount' => TraitSuggestion::where('status', 'pending')->count(),
            'search'       => $search,
        ]);
    }

    public function implement(TraitSuggestion $traitSuggestion): RedirectResponse
    {
        $traitSuggestion->update(['status' => 'implemented']);
        return back()->with('success', 'Отмечено как реализованное.');
    }

    public function approve(TraitSuggestion $traitSuggestion): RedirectResponse
    {
        $traitSuggestion->update(['status' => 'approved']);
        return back()->with('success', 'Предложение одобрено.');
    }

    public function reject(TraitSuggestion $traitSuggestion): RedirectResponse
    {
        $traitSuggestion->update(['status' => 'rejected']);
        return back()->with('success', 'Предложение отклонено.');
    }

    public function reopen(TraitSuggestion $traitSuggestion): RedirectResponse
    {
        $traitSuggestion->update(['status' => 'pending']);
        return back()->with('success', 'Предложение возвращено на рассмотрение.');
    }

    public function destroy(TraitSuggestion $traitSuggestion): RedirectResponse
    {
        $traitSuggestion->delete();
        return back()->with('success', 'Предложение удалено.');
    }
}
