<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;

Auth::requireAdmin();

$error = "";
$success = "";
$nome = '';
$email = '';
$tipo = 'user';
$ativo = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
    Csrf::validate($_POST['_token'] ?? null);
    $nome  = trim($_POST['nome'] ?? ''); //[cite: 1]
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL); //[cite: 1]
    $senha = $_POST['senha'] ?? ''; //[cite: 1]
    $tipo  = trim($_POST['tipo'] ?? 'user');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $allowedRoles = ['admin', 'engenheiro', 'mestre_obras', 'user'];
    if (!in_array($tipo, $allowedRoles, true)) {
        $tipo = 'user';
    }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }

    if (!$email) {
        $error = 'E-mail inválido.';
    } elseif (empty($nome) || empty($senha)) {
        $error = 'Preencha todos os campos.';
    } else {
        $stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE email = ?"); //[cite: 1]
        $stmtCheck->bind_param("s", $email); //[cite: 1]
        $stmtCheck->execute(); //[cite: 1]
        
        if ($stmtCheck->get_result()->num_rows > 0) { //[cite: 1]
            $error = 'E-mail já cadastrado.'; //[cite: 1]
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT); //[cite: 1]
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, role, tipo, ativo) VALUES (?, ?, ?, ?, ?, ?)"); //[cite: 1]
            $stmt->bind_param("sssssi", $nome, $email, $senhaHash, $tipo, $tipo, $ativo); //[cite: 1]
            
            if ($stmt->execute()) { //[cite: 1]
                $success = 'Usuário interno cadastrado com sucesso!'; //[cite: 1]
                $nome = '';
                $email = '';
                $tipo = 'user';
                $ativo = 1;
            } else {
                $error = 'Erro ao cadastrar usuário.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Cadastro - Auxiliar Obras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h4 class="card-title text-center mb-4">Cadastro Interno de Usuário</h4>
                        <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?>
                        </div><?php endif; ?>
                        <form method="POST">
                            <?= Csrf::input() ?>
                            <div class="mb-3">
                                <label class="form-label">Nome Completo</label>
                                <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>"
                                    class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>"
                                    class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipo de usuário</label>
                                <select name="tipo" class="form-select" required>
                                    <option value="user" <?= $tipo === 'user' ? 'selected' : '' ?>>Usuário</option>
                                    <option value="engenheiro" <?= $tipo === 'engenheiro' ? 'selected' : '' ?>>
                                        Engenheiro</option>
                                    <option value="mestre_obras" <?= $tipo === 'mestre_obras' ? 'selected' : '' ?>>
                                        Mestre de Obras</option>
                                    <option value="admin" <?= $tipo === 'admin' ? 'selected' : '' ?>>Administrador
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Senha</label>
                                <input type="password" name="senha" class="form-control" required>
                            </div>
                            <div class="form-check mb-3">
                                <input type="checkbox" name="ativo" value="1" class="form-check-input" id="ativo"
                                    <?= $ativo ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ativo">Liberar acesso imediatamente</label>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Cadastrar</button>
                            <a href="dashboard.php" class="btn btn-link w-100 text-center mt-2">Voltar ao painel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>