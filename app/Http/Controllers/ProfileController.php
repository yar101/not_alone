<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Order;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('settings.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->wallet && (float) $user->wallet->balance < 0) {
            return back()->withErrors(['password' => 'Невозможно удалить аккаунт при отрицательном балансе кошелька. Пожалуйста, погасите задолженность.']);
        }

        if ($user->wallet && (float) $user->wallet->held_balance > 0) {
            return back()->withErrors(['password' => 'Невозможно удалить аккаунт при наличии средств в заморозке.']);
        }

        $hasActiveOrders = Order::where(function ($q) use ($user) {
            $q->where('customer_id', $user->id)->orWhere('idol_id', $user->id);
        })->whereIn('status', [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Paid, OrderStatus::Disputed])->exists();

        if ($hasActiveOrders) {
            return back()->withErrors(['password' => 'Невозможно удалить аккаунт при наличии активных заказов.']);
        }

        Auth::logout();

        $user->anonymize();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
