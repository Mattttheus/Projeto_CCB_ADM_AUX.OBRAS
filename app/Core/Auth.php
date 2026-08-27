<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
            session_start();
        }
    }

    public static function requireUser(): void
    {
        self::startSession();
        if (empty($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireUser();
        if (($_SESSION['role'] ?? $_SESSION['tipo'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
    }
}
