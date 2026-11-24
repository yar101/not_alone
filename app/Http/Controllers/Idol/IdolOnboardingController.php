<?php

namespace App\Http\Controllers\Idol;

use App\Contracts\MandarinGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\TaxIdCooldown;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IdolOnboardingController extends Controller
{
    /**
     * Список популярных банков СБP РФ.
     */
    public const SBP_BANKS = [
        ['id' => '100000000004', 'name' => 'Сбербанк'],
        ['id' => '100000000008', 'name' => 'Т-Банк (Тинькофф)'],
        ['id' => '100000000005', 'name' => 'ВТБ'],
        ['id' => '100000000007', 'name' => 'Альфа-Банк'],
        ['id' => '100000000015', 'name' => 'Райффайзенбанк'],
        ['id' => '100000000011', 'name' => 'Газпромбанк'],
        ['id' => '100000000027', 'name' => 'Озон Банк'],
        ['id' => '100000000029', 'name' => 'Яндекс Банк'],
        ['id' => '100000000006', 'name' => 'Промсвязьбанк'],
        ['id' => '100000000016', 'name' => 'Совкомбанк'],
    ];

    public function __construct(
        protected MandarinGatewayInterface $mandarinGateway
    ) {}

    /**
     * Страница онбординга самозанятого и настройки выплат по СБП.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->is_idol) {
            return redirect()->route('idol.apply')->with('error', 'Настройка выплат доступна только Айдолам.');
        }

        $activeCooldown = null;
        if ($user->inn) {
            $activeCooldown = TaxIdCooldown::where('inn', $user->inn)
                ->where('cooldown_until', '>', now())
                ->first();
        }
        if (! $activeCooldown) {
            $activeCooldown = TaxIdCooldown::where('user_id', $user->id)
                ->where('cooldown_until', '>', now())
                ->first();
        }

        return Inertia::render('Profile/IdolOnboarding', [
            'inn' => $user->inn,
            'taxStatus' => $user->tax_status ?? 'none',
            'taxVerifiedAt' => $user->tax_verified_at?->toIso8601String(),
            'payoutPhone' => $user->payout_phone,
            'sbpBankId' => $user->sbp_bank_id,
            'isPayoutReady' => (bool) $user->is_payout_ready,
            'cooldownUntil' => $activeCooldown?->cooldown_until?->toIso8601String(),
            'cooldownReason' => $activeCooldown?->reason,
            'sbpBanks' => self::SBP_BANKS,
        ]);
    }

    /**
     * Привязка 12-значного ИНН самозанятого айдола.
     */
    public function submitInn(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->is_idol) {
            abort(403, 'Только айдолы могут привязывать ИНН самозанятого.');
        }

        $validated = $request->validate([
            'inn' => ['required', 'string', 'digits:12'],
        ], [
            'inn.required' => 'Введите ваш 12-значный ИНН физлица РФ.',
            'inn.digits' => 'ИНН физлица должен состоять ровно из 12 цифр.',
        ]);

        $inn = trim($validated['inn']);

        // Валидация контрольной суммы
        if (! $this->validateInnChecksum($inn)) {
            return back()->withErrors(['inn' => 'Неверная контрольная сумма ИНН. Проверьте правильность введённых цифр.']);
        }

        // Проверка уникальности
        $alreadyAssigned = User::where('inn', $inn)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($alreadyAssigned) {
            return back()->withErrors(['inn' => 'Данный ИНН уже привязан к другому профилю на платформе.']);
        }

        // Проверка 365-дневного кулдауна
        $cooldown = TaxIdCooldown::where('inn', $inn)
            ->where('cooldown_until', '>', now())
            ->first();

        if ($cooldown) {
            return back()->withErrors([
                'inn' => 'По данному ИНН действует кулдаун 365 дней (до '.$cooldown->cooldown_until->format('d.m.Y').').',
            ]);
        }

        // Запрос привязки в Mandarin Gateway
        try {
            $this->mandarinGateway->bindSelfEmployedInn($inn);
            $taxCheck = $this->mandarinGateway->checkSelfEmployedStatus($inn);
        } catch (\Throwable $e) {
            return back()->withErrors(['inn' => 'Ошибка взаимодействия с ФНС: '.$e->getMessage()]);
        }

        if ($taxCheck['limit_exceeded'] ?? false) {
            return back()->withErrors([
                'inn' => 'Превышен годовой лимит дохода самозанятого (2.4 млн ₽ по 422-ФЗ). Привязка невозможна.',
            ]);
        }

        $isVerified = ($taxCheck['is_self_employed'] ?? false) && ($taxCheck['status'] ?? '') === 'active';

        $user->forceFill([
            'inn' => $inn,
            'tax_status' => $isVerified ? 'self_employed' : 'pending_binding',
            'tax_verified_at' => $isVerified ? now() : null,
            'is_payout_ready' => $isVerified && ! empty($user->payout_phone) && ! empty($user->sbp_bank_id),
        ])->save();

        if ($isVerified) {
            return back()->with('success', 'Статус самозанятого успешно подтверждён в ФНС («Мой налог»)!');
        }

        return back()->with('success', 'Запрос на привязку отправлен в «Мой налог». Подтвердите партнёрство в приложении.');
    }

    /**
     * Сохранение реквизитов для выплат по СБП.
     */
    public function savePayoutRequisites(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->is_idol) {
            abort(403, 'Только айдолы могут указывать реквизиты выплат.');
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(\+7|7|8)?[0-9]{10}$/'],
            'bank_id' => ['required', 'string'],
        ], [
            'phone.required' => 'Укажите номер телефона, привязанный к СБП.',
            'phone.regex' => 'Номер телефона должен быть в российском формате (+79XXXXXXXXX).',
            'bank_id.required' => 'Выберите банк получателя для СБП.',
        ]);

        $rawPhone = preg_replace('/[^0-9]/', '', $validated['phone']);
        if (strlen($rawPhone) === 11 && ($rawPhone[0] === '7' || $rawPhone[0] === '8')) {
            $formattedPhone = '+7'.substr($rawPhone, 1);
        } elseif (strlen($rawPhone) === 10) {
            $formattedPhone = '+7'.$rawPhone;
        } else {
            $formattedPhone = '+'.$rawPhone;
        }

        $bankExists = collect(self::SBP_BANKS)->contains('id', $validated['bank_id']);
        if (! $bankExists) {
            return back()->withErrors(['bank_id' => 'Выбранный банк не поддерживается Системой быстрых платежей.']);
        }

        $user->forceFill([
            'payout_phone' => $formattedPhone,
            'sbp_bank_id' => $validated['bank_id'],
            'payout_requisites_changed_at' => now(),
            'is_payout_ready' => $user->tax_status === 'self_employed',
        ])->save();

        return back()->with('success', 'Реквизиты СБП успешно сохранены!');
    }

    /**
     * Ручная синхронизация статуса плательщика НПД с ФНС («Мой налог»).
     */
    public function syncTaxStatus(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->is_idol || empty($user->inn)) {
            return back()->withErrors(['inn' => 'Сначала укажите и сохраните ваш ИНН.']);
        }

        try {
            $taxCheck = $this->mandarinGateway->checkSelfEmployedStatus($user->inn);
        } catch (\Throwable $e) {
            return back()->withErrors(['inn' => 'Ошибка опроса ФНС: '.$e->getMessage()]);
        }

        if ($taxCheck['limit_exceeded'] ?? false) {
            $user->forceFill([
                'tax_status' => 'rejected',
                'is_payout_ready' => false,
            ])->save();

            return back()->withErrors([
                'inn' => 'Превышен годовой лимит дохода самозанятого (2.4 млн ₽). Выплаты заблокированы до разрешения в ФНС.',
            ]);
        }

        $isVerified = ($taxCheck['is_self_employed'] ?? false) && ($taxCheck['status'] ?? '') === 'active';

        $user->forceFill([
            'tax_status' => $isVerified ? 'self_employed' : 'rejected',
            'tax_verified_at' => $isVerified ? ($user->tax_verified_at ?? now()) : null,
            'is_payout_ready' => $isVerified && ! empty($user->payout_phone) && ! empty($user->sbp_bank_id),
        ])->save();

        if ($isVerified) {
            return back()->with('success', 'Статус плательщика НПД активен и подтверждён!');
        }

        return back()->withErrors(['inn' => 'В ФНС «Мой налог» статус не подтверждён. Убедитесь, что приняли партнёрство в приложении.']);
    }

    /**
     * Проверка контрольной суммы 12-значного российского ИНН физического лица.
     */
    public function validateInnChecksum(string $inn): bool
    {
        if (strlen($inn) !== 12 || ! ctype_digit($inn)) {
            return false;
        }

        // Тестовый валидный ИНН для dev/local
        if (app()->environment('local', 'testing') && $inn === '123456789012') {
            return true;
        }

        $weights11 = [7, 2, 4, 10, 3, 5, 9, 4, 6, 8, 0];
        $weights12 = [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8, 0];

        $sum11 = 0;
        for ($i = 0; $i < 11; $i++) {
            $sum11 += ((int) $inn[$i]) * $weights11[$i];
        }
        $check11 = ($sum11 % 11) % 10;

        $sum12 = 0;
        for ($i = 0; $i < 11; $i++) {
            $sum12 += ((int) $inn[$i]) * $weights12[$i];
        }
        $check12 = ($sum12 % 11) % 10;

        return ((int) $inn[10] === $check11) && ((int) $inn[11] === $check12);
    }
}
