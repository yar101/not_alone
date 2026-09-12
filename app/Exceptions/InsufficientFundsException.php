<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public function __construct(string $message = 'Недостаточно средств на балансе кошелька', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
