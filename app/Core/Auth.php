<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
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

        $role = $_SESSION['role'] ?? $_SESSION['tipo'] ?? null;

        if ($role !== 'admin') {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }

    public static function hasFullProjectAccess(): bool
    {
        $role = strtolower((string) ($_SESSION['role'] ?? $_SESSION['tipo'] ?? ''));

        return in_array($role, ['admin', 'suporte'], true);
    }

    public static function canAccessProject(\mysqli $connection, int $projectId): bool
    {
        self::requireUser();

        if ($projectId < 1) {
            return false;
        }

        if (self::hasFullProjectAccess()) {
            return true;
        }

        $userId = (int) ($_SESSION['usuario_id'] ?? 0);
        $hasAccess = false;
        $statement = $connection->prepare(
            'SELECT 1 FROM obra_responsaveis WHERE obra_id = ? AND usuario_id = ? LIMIT 1'
        );

        if ($statement) {
            $statement->bind_param('ii', $projectId, $userId);
            $statement->execute();
            $hasAccess = $statement->get_result()->num_rows > 0;
            $statement->close();
        }

        return $hasAccess;
    }
}
