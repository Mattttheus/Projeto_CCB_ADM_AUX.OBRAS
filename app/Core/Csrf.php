<?php

declare(strict_types=1);

namespace App\Core;

class Csrf
{
    private const SESSION_KEY = '_csrf_token';


    /**
     * Retorna o token CSRF atual.
     */
    public static function token(): string
    {
        Auth::startSession();

        if (
            empty($_SESSION[self::SESSION_KEY])
            || !is_string($_SESSION[self::SESSION_KEY])
        ) {

            $_SESSION[self::SESSION_KEY] =
                bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }


    /**
     * Gera campo hidden para formulário.
     */
    public static function input(): string
    {
        $token = htmlspecialchars(
            self::token(),
            ENT_QUOTES,
            'UTF-8'
        );

        return '<input type="hidden" name="_token" value="' . $token . '">';
    }


    /**
     * Verifica o token enviado.
     */
    public static function verify(?string $token): bool
    {
        Auth::startSession();

        if (
            empty($token)
            || empty($_SESSION[self::SESSION_KEY])
        ) {
            return false;
        }

        return hash_equals(
            $_SESSION[self::SESSION_KEY],
            $token
        );
    }


    /**
     * Valida o token enviado e lança exceção quando inválido.
     */
    public static function validate(?string $token): void
    {
        if (!self::verify($token)) {
            throw new \InvalidArgumentException('Token CSRF inválido.');
        }
    }
}