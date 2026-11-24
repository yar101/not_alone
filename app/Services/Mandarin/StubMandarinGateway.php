<?php

namespace App\Services\Mandarin;

use App\Contracts\MandarinGatewayInterface;
use App\Exceptions\MandarinGatewayException;
use App\Models\User;
use Illuminate\Support\Str;

class StubMandarinGateway implements MandarinGatewayInterface
{
    /**
     * Создание инвойса/платежной сессии для пополнения баланса.
     *
     * {@inheritdoc}
     */
    public function createTopUpInvoice(User $user, float $amount, string $returnUrl, array $metadata = []): array
    {
        if ($amount <= 0) {
            throw new MandarinGatewayException('Сумма пополнения должна быть больше 0', 422);
        }

        $invoiceId = 'stub_inv_'.Str::lower(Str::random(16));
        $baseUrl = rtrim((string) (config('app.url') ?? 'http://localhost'), '/');

        return [
            'id' => $invoiceId,
            'payment_url' => "{$baseUrl}/stub-mandarin/pay/{$invoiceId}",
            'qr_code_url' => "{$baseUrl}/stub-mandarin/qr/{$invoiceId}",
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'RUB',
            'metadata' => $metadata,
        ];
    }

    /**
     * Привязка 12-значного ИНН самозанятого.
     *
     * {@inheritdoc}
     */
    public function bindSelfEmployedInn(string $inn, ?string $callbackUrl = null): array
    {
        $cleanInn = trim($inn);

        if (strlen($cleanInn) !== 12 || ! ctype_digit($cleanInn)) {
            throw new MandarinGatewayException('ИНН физлица РФ должен содержать ровно 12 цифр', 422);
        }

        if ($cleanInn === '000000000000') {
            throw new MandarinGatewayException('Тестовый ИНН 000000000000 отклонён ФНС', 422);
        }

        return [
            'bind_id' => 'stub_bind_'.Str::lower(Str::random(12)),
            'inn' => $cleanInn,
            'status' => 'bound',
            'message' => 'ИНН успешно привязан в тестовом режиме (эмуляция ФНС «Мой налог»)',
        ];
    }

    /**
     * Проверка статуса самозанятого и годового лимита в ФНС.
     *
     * {@inheritdoc}
     */
    public function checkSelfEmployedStatus(string $inn): array
    {
        $cleanInn = trim($inn);

        // Спец-сценарии для тестов по последним цифрам:
        // * ...0000 -> не самозанятый
        // * ...9999 -> превышен лимит 2.4 млн руб
        if (str_ends_with($cleanInn, '0000')) {
            return [
                'is_self_employed' => false,
                'status' => 'inactive',
                'limit_exceeded' => false,
                'annual_income' => 0.00,
            ];
        }

        if (str_ends_with($cleanInn, '9999')) {
            return [
                'is_self_employed' => true,
                'status' => 'active',
                'limit_exceeded' => true,
                'annual_income' => 2550000.00,
            ];
        }

        return [
            'is_self_employed' => true,
            'status' => 'active',
            'limit_exceeded' => false,
            'annual_income' => 150000.00,
        ];
    }

    /**
     * Регистрация чека самозанятого по 422-ФЗ.
     *
     * {@inheritdoc}
     */
    public function registerSelfEmployedReceipt(
        string $inn,
        float $amount,
        string $serviceName,
        ?string $clientContact = null,
        ?string $operationId = null
    ): array {
        if ($amount <= 0) {
            throw new MandarinGatewayException('Сумма чека должна быть больше 0', 422);
        }

        $receiptId = 'stub_rcpt_'.Str::lower(Str::random(14));
        $hash = md5($inn.$amount.microtime());

        return [
            'receipt_id' => $receiptId,
            'receipt_url' => "https://npd.nalog.ru/receipt/stub-{$hash}",
            'registered_at' => now()->toIso8601String(),
            'inn' => $inn,
            'amount' => $amount,
            'service_name' => $serviceName,
        ];
    }

    /**
     * Выплата через СБП.
     *
     * {@inheritdoc}
     */
    public function executePayout(User $idol, float $amount, array $requisites, string $idempotencyKey): array
    {
        if ($amount <= 0) {
            throw new MandarinGatewayException('Сумма выплаты должна быть больше 0', 422);
        }

        $phone = $requisites['phone'] ?? '';

        // Тестовые сценарии по маске телефона:
        // * ...0000 -> отказ банка
        // * ...9999 -> межбанковский таймаут СБП (PendingBank)
        if (str_ends_with($phone, '0000')) {
            return [
                'payout_id' => 'stub_payout_'.Str::lower(Str::random(14)),
                'status' => 'rejected',
                'message' => 'Счёт получателя СБП заблокирован банком (тестовый отказ)',
            ];
        }

        if (str_ends_with($phone, '9999')) {
            return [
                'payout_id' => 'stub_payout_'.Str::lower(Str::random(14)),
                'status' => 'pending_bank',
                'message' => 'Платёж передан в клиринг СБП (межбанковская задержка)',
            ];
        }

        return [
            'payout_id' => 'stub_payout_'.Str::lower(Str::random(14)),
            'status' => 'settled',
            'message' => 'Выплата по СБП успешно проведена с номинального счёта',
        ];
    }

    /**
     * Опрос статуса операции.
     *
     * {@inheritdoc}
     */
    public function getOperationStatus(string $gatewayOperationId): array
    {
        if (str_contains($gatewayOperationId, 'rejected')) {
            $status = 'rejected';
        } elseif (str_contains($gatewayOperationId, 'pending')) {
            $status = 'pending_bank';
        } else {
            $status = 'settled';
        }

        return [
            'operation_id' => $gatewayOperationId,
            'status' => $status,
            'raw' => [
                'stub' => true,
                'checked_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Возврат средств по ст. 32 ЗОЗПП.
     *
     * {@inheritdoc}
     */
    public function refundTopUp(string $invoiceId, float $amount, string $reason): array
    {
        return [
            'refund_id' => 'stub_ref_'.Str::lower(Str::random(14)),
            'status' => 'succeeded',
            'amount' => $amount,
            'reason' => $reason,
        ];
    }
}
