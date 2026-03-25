<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): RedirectResponse
    {
        return redirect('/');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();
        if ($user->is_banned) {
            $active = $user->banned_until === null || $user->banned_until->isFuture();
            if ($active) {
                Auth::guard('web')->logout();
                $msg = $user->banned_until
                    ? "Аккаунт заблокирован ({$this->formatBanRemaining($user->banned_until)}). Причина: {$user->ban_reason}"
                    : "Аккаунт заблокирован навсегда. Причина: {$user->ban_reason}";
                throw ValidationException::withMessages(['email' => $msg]);
            }
            // Ban expired — lift it automatically
            $user->update(['is_banned' => false, 'banned_until' => null, 'banned_at' => null, 'ban_reason' => null, 'banned_by' => null]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('profile', absolute: false));
    }

    private function formatBanRemaining(\Carbon\Carbon $until): string
    {
        $secs = max(0, now()->diffInSeconds($until));
        if ($secs >= 86400) return 'на ' . floor($secs / 86400) . ' дн';
        if ($secs >= 3600)  return 'на ' . floor($secs / 3600) . ' ч ' . floor(($secs % 3600) / 60) . ' мин';
        return 'на ' . max(1, floor($secs / 60)) . ' мин';
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
