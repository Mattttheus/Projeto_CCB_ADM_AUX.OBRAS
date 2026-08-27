<?php
require_once("config/conexao.php");

$nome = "Matheus Vinicius";
$email = "matheus.suporte@auxiliarobras.com.br";
$senha = password_hash("Mudar@123", PASSWORD_DEFAULT);
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
            ON DUPLICATE KEY UPDATE role = ?, tipo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssss', $nome, $email, $senha, $perfil, $perfil, $perfil, $perfil);
} elseif ($hasRole) {
    $sql = "INSERT INTO usuarios (nome, email, senha, role) VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE role = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssss', $nome, $email, $senha, $perfil, $perfil);
} else {
    $sql = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE tipo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssss', $nome, $email, $senha, $perfil, $perfil);
}

if ($stmt->execute()) {
    echo "<b>Usuário Suporte cadastrado/atualizado com sucesso!</b><br>";
    echo "E-mail: " . $email . "<br>";
    echo "Senha provisória: Mudar@123";
} else {
    echo "Erro ao cadastrar no banco: " . $conn->error;
}
?>