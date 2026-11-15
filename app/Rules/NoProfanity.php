<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoProfanity implements ValidationRule
{
    /**
     * Homoglyphs mapping Latin characters / numbers to Cyrillic characters for detecting obfuscated Russian profanity.
     */
    private const HOMOGLYPHS_RU = [
        'a' => 'а', 'b' => 'в', 'c' => 'с', 'e' => 'е', 'h' => 'н',
        'k' => 'к', 'm' => 'м', 'o' => 'о', 'p' => 'р', 't' => 'т',
        'x' => 'х', 'y' => 'у',
        '0' => 'о', '1' => 'и', '3' => 'з', '4' => 'ч', '6' => 'б',
    ];

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $rawLower = mb_strtolower($value, 'UTF-8');

        // 1. Check English profanity on the raw text and stripped text
        $stripped = preg_replace('/[*\-_\.\+\=\~\`\^\#\@\!]/u', '', $rawLower);
        if ($this->hasEnglishProfanity($rawLower) || $this->hasEnglishProfanity($stripped)) {
            $fail('Текст в поле :attribute содержит недопустимую или оскорбительную лексику.');

            return;
        }

        // 2. Check Russian profanity on raw text
        if ($this->hasRussianProfanity($rawLower) || $this->hasRussianProfanity($stripped)) {
            $fail('Текст в поле :attribute содержит недопустимую или оскорбительную лексику.');

            return;
        }

        // 3. Normalize for Cyrillic homoglyphs (e.g. х*у*й with latin 'y' or masked chars)
        $normalizedRussian = strtr($stripped, self::HOMOGLYPHS_RU);
        if ($this->hasRussianProfanity($normalizedRussian)) {
            $fail('Текст в поле :attribute содержит недопустимую или оскорбительную лексику.');
        }
    }

    private function hasRussianProfanity(string $text): bool
    {
        // Root regex patterns for Russian obscenities
        $patterns = [
            // Spaced-out profanities (e.g. "х у й", "п и з д а", "б л я т ь", "п и д о р")
            '/(?:^|[^а-яё])(?:х\s*[уy]\s*[йяеи]|п\s*[иеё]\s*з\s*д|б\s*л\s*[яа]\s*[дт]|п\s*и\s*д\s*[аое]\s*р)(?:$|[^а-яё])/ui',
            // Хуй / хуе / хуя / хули
            '/(?:^|[^а-яё])(?:х[уy][йяеёию][а-яё]*|х[уy]л[еи]|о[хx][уy][еёэ]н[а-яё]*|о[хx][уy][еёэ][а-яё]*|по[хx][уy][йяеёи][а-яё]*|на[хx][уy][йя][а-яё]*|до[хx][уy][яеёи][а-яё]*|ни[хx][уy][яеёи][а-яё]*)(?:$|[^а-яё])/ui',
            // Пизд
            '/(?:^|[^а-яё])(?:п[иеё]зд[а-яё]*|расп[иеё]зд[а-яё]*|сп[иеё]зд[а-яё]*|отп[иеё]зд[а-яё]*|доп[иеё]зд[а-яё]*|вып[иеё]зд[а-яё]*|вп[иеё]зд[а-яё]*|прип[иеё]зд[а-яё]*|подп[иеё]зд[а-яё]*|проп[иеё]зд[а-яё]*)(?:$|[^а-яё])/ui',
            // Еб / ёб
            '/(?:^|[^а-яё])(?:[её]б[а-яё]*|эб[аеыуия][а-яё]*|в[ыи][еёэ]б[а-яё]*|за[еёэ]б[а-яё]*|на[еёэ]б[а-яё]*|про[еёэ]б[а-яё]*|по[еёэ]б[а-яё]*|до[еёэ]б[а-яё]*|при[еёэ]б[а-яё]*|с[ъь][еёэ]б[а-яё]*|от[ъь]?[еёэ]б[а-яё]*|пере[еёэ]б[а-яё]*|под[ъь]?[еёэ]б[а-яё]*)(?:$|[^а-яё])/ui',
            // Бля / блять / блядь
            '/(?:^|[^а-яё])(?:бля|бл[яа][дт][а-яё]*|бл[яа]дин[а-яё]*|бл[яа]дств[а-яё]*)(?:$|[^а-яё])/ui',
            // Мудак / пидор / шлюха / гондон
            '/(?:^|[^а-яё])(?:муд[аое][кч][а-яё]*|пид[аое]р[а-яё]*|пид[аое]рас[а-яё]*|шлюх[а-яё]*|г[ао]нд[оа]н[а-яё]*)(?:$|[^а-яё])/ui',
            // Сука (только в контексте ругательства или в изоляции)
            '/(?:^|[^а-яё])(?:с[уy]к[аеиуы]|с[уy]к[ое]й|с[уy]чк[аеиоу]|с[уy]чк[ое]й|с[уy]ч[аеиоу]р[а-яё]*)(?:$|[^а-яё])/ui',
            // Говно / дерьмо
            '/(?:^|[^а-яё])(?:г[оа]вн[а-яё]*|г[оа]вен[а-яё]*|дерьм[а-яё]*)(?:$|[^а-яё])/ui',
        ];

        foreach ($patterns as $pattern) {
            if ($pattern !== '' && preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function hasEnglishProfanity(string $text): bool
    {
        $patterns = [
            '/\b(fuck|fucker|fucking|fucked|fucks|fuk|fukk)\b/i',
            '/\b(shit|shits|shitty|bullshit)\b/i',
            '/\b(bitch|bitches|bitching)\b/i',
            '/\b(asshole|arsehole|dumbass)\b/i',
            '/\b(cunt|cunts)\b/i',
            '/\b(dick|dickhead|dicks)\b/i',
            '/\b(pussy|pussies)\b/i',
            '/\b(whore|whores|slut|sluts)\b/i',
            '/\b(faggot|faggots|fag|fags)\b/i',
            '/\b(nigger|niggers|nigga|niggas)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
