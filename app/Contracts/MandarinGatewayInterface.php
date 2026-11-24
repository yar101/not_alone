<?php

namespace App\Contracts;

use App\Models\User;

interface MandarinGatewayInterface
{
    /**
     * Создание инвойса/платёжной сессии для пополнения баланса (покупка CoinPack).
     *
     * @param  User  $user  Пользователь-клиент
     * @param  float  $amount  Сумма в рублях (1 🪙 = 1.00 ₽)
     * @param  string  $returnUrl  URL возврата после оплаты
     * @param  array<string, mixed>  $metadata  Дополнительные метаданные (coin_pack_id и т.д.)
     * @return array{id: string, payment_url: string, qr_code_url: ?string, status: string}
     */
    public function createTopUpInvoice(User $user, float $amount, string $returnUrl, array $metadata = []): array;

    /**
     * Запрос на привязку 12-значного ИНН самозанятого айдола через Mandarin API в ФНС («Мой налог»).
     *
     * @param  string  $inn  12-значный ИНН физлица РФ
     * @param  string|null  $callbackUrl  URL для вебхука подтверждения привязки
     * @return array{bind_id: string, inn: string, status: string, message: ?string}
     */
    public function bindSelfEmployedInn(string $inn, ?string $callbackUrl = null): array;

    /**
     * Проверка статуса плательщика НПД и годового лимита (2.4 млн ₽) в ФНС («Мой налог»).
     *
     * @param  string  $inn  12-значный ИНН физлица РФ
     * @return array{is_self_employed: bool, status: string, limit_exceeded: bool, annual_income: float}
     */
    public function checkSelfEmployedStatus(string $inn): array;

    /**
     * Регистрация чека самозанятого по 422-ФЗ в ФНС («Мой налог») в момент закрытия сделки.
     *
     * @param  string  $inn  ИНН исполнителя (айдола)
     * @param  float  $amount  100% стоимости услуги
     * @param  string  $serviceName  Наименование оказанной услуги
     * @param  string|null  $clientContact  Email или телефон клиента
     * @param  string|null  $operationId  ID операции в системе
     * @return array{receipt_id: string, receipt_url: string, registered_at: string}
     */
    public function registerSelfEmployedReceipt(
        string $inn,
        float $amount,
        string $serviceName,
        ?string $clientContact = null,
        ?string $operationId = null
    ): array;

    /**
     * Выплата заработанных средств айдолу через СБП с номинального счёта платформы.
     *
     * @param  User  $idol  Айдол-получатель
     * @param  float  $amount  Сумма чистой выплаты
     * @param  array{phone: string, bank_id: string, bik?: string|null}  $requisites  Реквизиты СБП
     * @param  string  $idempotencyKey  Уникальный ключ идемпотентности
     * @return array{payout_id: string, status: string, message: ?string}
     */
    public function executePayout(User $idol, float $amount, array $requisites, string $idempotencyKey): array;

    /**
     * Опрос статуса операции по ID в шлюзе Mandarin (для Outbox-реконсиляции и верификации вебхуков).
     *
     * @param  string  $gatewayOperationId  ID операции в Mandarin
     * @return array{operation_id: string, status: string, raw: array<string, mixed>}
     */
    public function getOperationStatus(string $gatewayOperationId): array;

    /**
     * Возврат неиспользованного аванса клиенту на исходную карту/СБП по ст. 32 ЗОЗПП.
     *
     * @param  string  $invoiceId  ID исходного платежа
     * @param  float  $amount  Сумма возврата
     * @param  string  $reason  Причина возврата
     * @return array{refund_id: string, status: string}
     */
    public function refundTopUp(string $invoiceId, float $amount, string $reason): array;
}
