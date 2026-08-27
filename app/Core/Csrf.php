<?php
declare(strict_types=1);

namespace App\Core;

use DomainException;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        Auth::startSession();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function validate(?string $token): void
    {
        Auth::startSession();
        $expected = $_SESSION[self::KEY] ?? '';
        if (!is_string($token) || $expected === '' || !hash_equals($expected, $token)) {
            throw new DomainException('Solicitação inválida. Atualize a página e tente novamente.');
        }
    }

    public static function input(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
