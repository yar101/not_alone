<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MandarinGatewayException extends RuntimeException
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $responsePayload;

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function __construct(
        string $message = 'Ошибка взаимодействия с платёжным шлюзом Mandarin',
        int $code = 502,
        ?array $responsePayload = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->responsePayload = $responsePayload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }
}
