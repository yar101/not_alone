<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && $user->is_banned) {
            $active = $user->banned_until === null || $user->banned_until->isFuture();

            if ($active) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $msg = $user->banned_until
                    ? 'Ваш аккаунт заблокирован ('.$this->formatBanRemaining($user->banned_until).').'
                    : 'Ваш аккаунт заблокирован навсегда.';

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['message' => $msg, 'error' => 'user_banned'], 403);
                }

                return redirect('/')->withErrors(['ban' => $msg]);
            }

            // Ban expired — lift it automatically
            $user->update([
                'is_banned' => false,
                'banned_until' => null,
                'banned_at' => null,
                'ban_reason' => null,
                'banned_by' => null,
            ]);
        }

        return $next($request);
    }

    private function formatBanRemaining(\Carbon\Carbon $until): string
    {
        $secs = max(0, now()->diffInSeconds($until));
        if ($secs >= 86400) {
            return 'на '.floor($secs / 86400).' дн';
        }
        if ($secs >= 3600) {
            return 'на '.floor($secs / 3600).' ч '.floor(($secs % 3600) / 60).' мин';
        }

        return 'на '.max(1, floor($secs / 60)).' мин';
    }
}
