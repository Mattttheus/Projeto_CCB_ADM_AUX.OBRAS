<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Provisionamento por URL desativado. Use o gerenciamento de usuários.');
}

Csrf::validate($_POST['_token'] ?? null);
$nome = trim((string) ($_POST['nome'] ?? ''));
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$senhaInformada = (string) ($_POST['senha'] ?? '');
$perfil = 'suporte';

if ($nome === '' || !$email || strlen($senhaInformada) < 12) {
    http_response_code(422);
    exit('Informe nome, e-mail e uma senha com ao menos 12 caracteres.');
}

$senha = password_hash($senhaInformada, PASSWORD_DEFAULT);

// Verifica se a tabela usa 'role' ou 'tipo' ou ambas
$columns = [];
$res = $conn->query("SHOW COLUMNS FROM usuarios");
while ($row = $res->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$hasRole = in_array('role', $columns);
$hasTipo = in_array('tipo', $columns);

if ($hasRole && $hasTipo) {
    $sql = "INSERT INTO usuarios (nome, email, senha, role, tipo) VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nome = ?, senha = ?, role = ?, tipo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssss', $nome, $email, $senha, $perfil, $perfil, $nome, $senha, $perfil, $perfil);
} elseif ($hasRole) {
    $sql = "INSERT INTO usuarios (nome, email, senha, role) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nome = ?, senha = ?, role = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssss', $nome, $email, $senha, $perfil, $nome, $senha, $perfil);
} else {
    $sql = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nome = ?, senha = ?, tipo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssss', $nome, $email, $senha, $perfil, $nome, $senha, $perfil);
}

if ($stmt->execute()) {
    echo '<b>Usuário de suporte cadastrado ou atualizado com sucesso.</b>';
} else {
    echo "Erro ao cadastrar no banco: " . $conn->error;
}