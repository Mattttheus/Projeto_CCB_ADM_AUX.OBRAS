<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| AUTOLOAD
|--------------------------------------------------------------------------
*/

spl_autoload_register(
    static function (string $class): void {

        $prefix = 'App\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr(
            $class,
            strlen($prefix)
        );

        $file = __DIR__
            . DIRECTORY_SEPARATOR
            . str_replace(
                '\\',
                DIRECTORY_SEPARATOR,
                $relativeClass
            )
            . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
);


/*
|--------------------------------------------------------------------------
| CONEXÃO COM BANCO
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/conexao.php';


/*
|--------------------------------------------------------------------------
| SESSÃO
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/Core/Auth.php';

\App\Core\Auth::startSession();
\App\Core\Auth::sendSecurityHeaders();
