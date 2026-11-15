<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoLinks implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        // 1. Detect protocols (http://, https://, ftp://, etc.)
        if (preg_match('/[a-z]{2,10}:\/\//i', $value)) {
            $fail('Поле :attribute не должно содержать ссылок или URL-адресов.');

            return;
        }

        // 2. Detect messengers & social links
        $socialRegex = '/\b(t\.me|telegram\.me|vk\.com|vk\.cc|instagram\.com|instagr\.am|discord\.gg|discord\.com\/invite|wa\.me|bit\.ly|x\.com|t\.co)(\/|\b|$)/ui';
        if (preg_match($socialRegex, $value)) {
            $fail('Поле :attribute не должно содержать ссылок на мессенджеры и социальные сети.');

            return;
        }

        // 3. Detect common domain extensions with word boundaries or paths
        $domainRegex = '/\b[a-z0-9\-_а-яё]+\.(ru|com|net|org|io|me|xyz|app|site|online|biz|info|top|dev|su|by|kz|ua|cc|co|tv|fm|ai|tech|store|pro|link|ly|рф|онлайн|gg)\b/ui';
        if (preg_match($domainRegex, $value)) {
            $fail('Поле :attribute не должно содержать внешних ссылок и доменов.');

            return;
        }

        // 4. Detect IPv4 addresses
        if (preg_match('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $value)) {
            $fail('Поле :attribute не должно содержать IP-адресов.');

            return;
        }

        // 5. Detect social handles (@username)
        if (preg_match('/(?<!\w)@[a-z0-9_\.]{3,}/i', $value)) {
            $fail('Поле :attribute не должно содержать контактов или упоминаний аккаунтов (@username).');

            return;
        }
    }
}
