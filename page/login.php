<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Csrf;

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
    Csrf::validate($_POST['_token'] ?? null);
    $email = trim($_POST['email'] ?? ''); //[cite: 5]
    $senha = $_POST['senha'] ?? ''; //[cite: 5]

    if (empty($email) || empty($senha)) { //[cite: 5]
        $erro = "Preencha todos os campos."; //[cite: 5]
    } else {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?"); //[cite: 5]
        $stmt->bind_param("s", $email); //[cite: 5]
        $stmt->execute(); //[cite: 5]
        $usuario = $stmt->get_result()->fetch_assoc(); //[cite: 5]

        if ($usuario && password_verify($senha, $usuario['senha'])) { //[cite: 5]
            session_regenerate_id(true); //[cite: 5]
            $_SESSION['usuario_id']   = $usuario['id']; //[cite: 5]
            $_SESSION['usuario']      = $usuario['nome']; //[cite: 2, 5]
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['empresa_id']   = $usuario['empresa_id']; //[cite: 5]
            $_SESSION['tipo']         = $usuario['tipo']; //[cite: 5]
            $_SESSION['role']         = $usuario['role'] ?? $usuario['tipo'];

            header("Location: dashboard.php"); //[cite: 5]
            exit;
        } else {
            $erro = "E-mail ou senha inválidos."; //[cite: 5]
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
    <title>Login - Auxiliar Obras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background: #f5f6fa;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card {
        width: 100%;
        max-width: 400px;
        padding: 25px;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    </style>
</head>

<body>
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
