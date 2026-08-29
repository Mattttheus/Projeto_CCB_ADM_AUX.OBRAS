<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;

Auth::requireAdmin();

$nome = "Matheus Vinicius";
$email = "matheus.suporte@auxiliarobras.com.br";
$senha = password_hash("4605", PASSWORD_DEFAULT);
$perfil = "suporte";

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
    echo "<b>Usuário Suporte cadastrado/atualizado com sucesso!</b><br>";
    echo "E-mail: " . $email . "<br>";
    echo "Senha provisória: 4605";
} else {
    echo "Erro ao cadastrar no banco: " . $conn->error;
}
