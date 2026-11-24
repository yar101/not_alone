<?php

namespace App\Services\Mandarin;

use App\Contracts\MandarinGatewayInterface;
use App\Exceptions\MandarinGatewayException;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MandarinGateway implements MandarinGatewayInterface
{
    protected string $mid;

    protected string $secret;

    protected string $apiUrl;

    protected string $selfEmployedUrl;

    protected ?string $selfEmployedToken;

    protected ?string $nominalAccount;

    protected bool $isSandbox;

    public function __construct()
    {
        $this->mid = (string) config('services.mandarin.mid', '');
        $this->secret = (string) config('services.mandarin.secret', '');
        $this->apiUrl = rtrim((string) config('services.mandarin.api_url', 'https://secure-app.mandarin.io'), '/');
        $this->selfEmployedUrl = rtrim((string) config('services.mandarin.self_employed_url', 'https://api.psp.io/self-employed-2/v1'), '/');
        $this->selfEmployedToken = config('services.mandarin.self_employed_token');
        $this->nominalAccount = config('services.mandarin.nominal_account');
        $this->isSandbox = (bool) config('services.mandarin.is_sandbox', true);
    }

    /**
     * Создание инвойса для пополнения синего баланса (покупка CoinPack).
     *
     * {@inheritdoc}
     */
    public function createTopUpInvoice(User $user, float $amount, string $returnUrl, array $metadata = []): array
    {
        $payload = [
            'payment' => [
                'orderId' => 'topup_'.uniqid('', true),
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'customer' => [
                'email' => $user->email,
                'name' => $user->name,
            ],
            'custom_values' => array_merge($metadata, [
                'user_id' => $user->id,
            ]),
            'urls' => [
                'return' => $returnUrl,
            ],
        ];

        $response = $this->request('POST', "{$this->apiUrl}/api/v1/public/invoices/", $payload);

        return [
            'id' => (string) ($response['id'] ?? $response['payment']['orderId'] ?? ''),
            'payment_url' => (string) ($response['userWebLink'] ?? ''),
            'qr_code_url' => $response['qrCode'] ?? null,
            'status' => (string) ($response['status'] ?? 'pending'),
        ];
    }

    /**
     * Привязка 12-значного ИНН самозанятого через Mandarin API в ФНС («Мой налог»).
     *
     * {@inheritdoc}
     */
    public function bindSelfEmployedInn(string $inn, ?string $callbackUrl = null): array
    {
        $payload = [
            'tin' => trim($inn),
        ];

        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }

        $response = $this->request('POST', "{$this->selfEmployedUrl}/tin/bind", $payload, true);

        return [
            'bind_id' => (string) ($response['id'] ?? $response['bind_id'] ?? ''),
            'inn' => trim($inn),
            'status' => (string) ($response['status'] ?? 'pending'),
            'message' => $response['message'] ?? null,
        ];
    }

    /**
     * Проверка статуса НПД и годового лимита (2.4 млн ₽).
     *
     * {@inheritdoc}
     */
    public function checkSelfEmployedStatus(string $inn): array
    {
        $cleanInn = trim($inn);
        $response = $this->request('GET', "{$this->selfEmployedUrl}/tin/{$cleanInn}/status", [], true);

        $status = (string) ($response['status'] ?? 'inactive');
        $isSelfEmployed = ($status === 'active' || ($response['is_self_employed'] ?? false) === true);
        $annualIncome = (float) ($response['annual_income'] ?? $response['income_total'] ?? 0.0);
        $limitExceeded = $annualIncome >= 2400000.00;

        return [
            'is_self_employed' => $isSelfEmployed,
            'status' => $status,
            'limit_exceeded' => $limitExceeded,
            'annual_income' => $annualIncome,
        ];
    }

    /**
     * Регистрация чека самозанятого в ФНС («Мой налог») на 100% услуги по 422-ФЗ.
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
        $payload = [
            'tin' => trim($inn),
            'amount' => number_format($amount, 2, '.', ''),
            'service_name' => $serviceName,
            'client_contact' => $clientContact,
            'external_id' => $operationId,
        ];

        $response = $this->request('POST', "{$this->selfEmployedUrl}/cheques/register", $payload, true);

        return [
            'receipt_id' => (string) ($response['id'] ?? $response['cheque_id'] ?? ''),
            'receipt_url' => (string) ($response['url'] ?? $response['receipt_url'] ?? ''),
            'registered_at' => (string) ($response['created_at'] ?? now()->toIso8601String()),
        ];
    }

    /**
     * Выплата по СБП с номинального счёта платформы.
     *
     * {@inheritdoc}
     */
    public function executePayout(User $idol, float $amount, array $requisites, string $idempotencyKey): array
    {
        $payload = [
            'payment' => [
                'orderId' => $idempotencyKey,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'recipient' => [
                'phone' => $requisites['phone'] ?? '',
                'bank_id' => $requisites['bank_id'] ?? '',
                'bik' => $requisites['bik'] ?? null,
            ],
            'sender' => [
                'account' => $this->nominalAccount,
            ],
        ];

        $response = $this->request('POST', "{$this->apiUrl}/api/v1/payouts/sbp", $payload);

        $status = match ($response['status'] ?? '') {
            'success', 'settled' => 'settled',
            'pending', 'processing' => 'pending_bank',
            default => 'rejected',
        };

        return [
            'payout_id' => (string) ($response['id'] ?? $idempotencyKey),
            'status' => $status,
            'message' => $response['error'] ?? $response['message'] ?? null,
        ];
    }

    /**
     * Опрос статуса операции по ID.
     *
     * {@inheritdoc}
     */
    public function getOperationStatus(string $gatewayOperationId): array
    {
        $response = $this->request('GET', "{$this->apiUrl}/api/v1/operations/{$gatewayOperationId}");

        $status = match ($response['status'] ?? '') {
            'success', 'settled', 'paid' => 'settled',
            'pending', 'processing' => 'pending_bank',
            default => 'rejected',
        };

        return [
            'operation_id' => $gatewayOperationId,
            'status' => $status,
            'raw' => $response,
        ];
    }

    /**
     * Возврат средств по ст. 32 ЗОЗПП на исходную карту/СБП.
     *
     * {@inheritdoc}
     */
    public function refundTopUp(string $invoiceId, float $amount, string $reason): array
    {
        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'reason' => $reason,
        ];

        $response = $this->request('POST', "{$this->apiUrl}/api/v1/invoices/{$invoiceId}/refund", $payload);

        return [
            'refund_id' => (string) ($response['id'] ?? ''),
            'status' => (string) ($response['status'] ?? 'succeeded'),
        ];
    }

    /**
     * Выполнение HTTP-запроса к API Mandarin с обработкой ошибок.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function request(string $method, string $url, array $data = [], bool $isSelfEmployed = false): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($isSelfEmployed) {
            $token = $this->selfEmployedToken ?: $this->secret;
            $headers['Authorization'] = "Bearer {$token}";
            $headers['MID'] = $this->mid;
        } else {
            $headers['Authorization'] = 'Basic '.base64_encode("{$this->mid}:{$this->secret}");
        }

        try {
            /** @var Response $response */
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->send($method, $url, ['json' => $data]);

            if ($response->failed()) {
                Log::channel('payments')->error('Mandarin API HTTP Error', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new MandarinGatewayException(
                    "Ошибка Mandarin API: HTTP {$response->status()}",
                    $response->status(),
                    $response->json()
                );
            }

            return (array) $response->json();
        } catch (MandarinGatewayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('payments')->error('Mandarin API Connection Exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            throw new MandarinGatewayException(
                "Сбой сетевого соединения со шлюзом Mandarin: {$e->getMessage()}",
                502,
                null,
                $e
            );
        }
    }
}
