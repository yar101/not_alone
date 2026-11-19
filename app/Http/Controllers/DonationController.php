<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientFundsException;
use App\Http\Requests\Donation\SendDonationRequest;
use App\Models\Donation;
use App\Models\User;
use App\Services\DonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function __construct(
        protected DonationService $donationService
    ) {}

    public function store(SendDonationRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $donor = $request->user();

        try {
            $feeCoveredPlatform = (bool) ($validated['fee_covered_platform'] ?? $validated['fee_covered_by_donor'] ?? false);
            $feeCoveredWithdrawal = (bool) ($validated['fee_covered_withdrawal'] ?? false);

            $donation = $this->donationService->send(
                donor: $donor,
                idol: $user,
                amount: (float) $validated['amount'],
                feeCoveredByDonor: $feeCoveredPlatform,
                message: $validated['message'] ?? null,
                idempotencyKey: $validated['idempotency_key'] ?? $request->header('X-Idempotency-Key'),
                feeCoveredWithdrawal: $feeCoveredWithdrawal,
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Донат успешно отправлен!',
                    'donation' => [
                        'id' => $donation->id,
                        'amount' => (float) $donation->amount,
                        'amount_charged' => (float) $donation->amount_charged,
                        'created_at' => $donation->created_at?->toIso8601String(),
                    ],
                ]);
            }

            return back()->with('success', 'Донат успешно отправлен!');
        } catch (InsufficientFundsException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['amount' => $e->getMessage()]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    public function index(Request $request, User $user): JsonResponse
    {
        $authId = auth()->id();
        $isOwner = $authId === $user->id;
        $isAdmin = auth('admin')->check();

        if (! $isOwner && ! $isAdmin) {
            abort(403, 'Просмотр списка донатов доступен только владельцу профиля.');
        }

        $donations = Donation::with('donor:id,name,avatar_path,gender')
            ->where('idol_id', $user->id)
            ->latest('id')
            ->paginate(15)
            ->through(fn (Donation $d) => [
                'id' => $d->id,
                'donor' => $d->donor ? [
                    'id' => $d->donor->id,
                    'name' => $d->donor->name,
                    'avatar_url' => $d->donor->avatar_url,
                    'gender' => $d->donor->gender,
                ] : [
                    'id' => null,
                    'name' => 'Удалённый пользователь',
                    'avatar_url' => null,
                    'gender' => null,
                ],
                'amount' => (float) $d->amount,
                'amount_charged' => (float) $d->amount_charged,
                'platform_fee' => (float) $d->platform_fee,
                'withdrawal_fee' => (float) ($d->withdrawal_fee ?? 0.0),
                'fee_covered_by_donor' => (bool) $d->fee_covered_by_donor,
                'fee_covered_withdrawal' => (bool) $d->fee_covered_withdrawal,
                'message' => $d->message,
                'created_at' => $d->created_at?->format('d.m.Y H:i'),
                'created_at_iso' => $d->created_at?->toIso8601String(),
            ]);

        return response()->json($donations);
    }
}
