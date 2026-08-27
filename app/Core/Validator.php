<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class Validator
{
    public static function email(mixed $value): string
    {
        $email = filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        return $email;
    }

    public static function requiredText(mixed $value, string $field, int $maxLength = 255): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $maxLength) {
            throw new InvalidArgumentException("Informe {$field} com até {$maxLength} caracteres.");
        }
        return $text;
    }

    /** @param list<string> $allowed */
    public static function oneOf(mixed $value, array $allowed, string $field): string
    {
        $value = (string) $value;
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("{$field} inválido.");
        }
        return $value;
    }
}
