<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Csrf;

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::validate($_POST['_token'] ?? null);
        $loginAttempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'expires_at' => 0];
        if ((int) $loginAttempts['expires_at'] < time()) {
            $loginAttempts = ['count' => 0, 'expires_at' => time() + 900];
        }
        if ((int) $loginAttempts['count'] >= 5) {
            throw new RuntimeException('Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.');
        }
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $erro = "Preencha todos os campos.";
        } else {
            // Consulta no padrão PDO
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                session_regenerate_id(true);
                unset($_SESSION['login_attempts']);
                $_SESSION['usuario_id']   = $usuario['id'];
                $_SESSION['usuario']      = $usuario['nome'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['empresa_id']   = $usuario['empresa_id'];
                $_SESSION['tipo']         = $usuario['tipo'];
                $_SESSION['role']         = $usuario['role'] ?? $usuario['tipo'];

                header("Location: dashboard.php");
                exit;
            } else {
                $_SESSION['login_attempts'] = ['count' => (int) $loginAttempts['count'] + 1, 'expires_at' => (int) $loginAttempts['expires_at']];
                $erro = "E-mail ou senha inválidos.";
            }
        }
    } catch (Throwable $exception) {
        $erro = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Auxiliar Obras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/app-shell.css" rel="stylesheet">
</head>

<body class="app-login-body">
    <div class="login-card">
        <h3 class="text-center mb-4">Auxiliar Obras</h3>
        <?php if ($erro): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="POST">
            <?= Csrf::input() ?>
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>
</body>

</html>