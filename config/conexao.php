<?php
// Configuração da Conexão com o Banco de Dados no InfinityFree

$host = 'sql213.infinityfree.com';
$dbname = 'if0_41646147_root';
$user = 'if0_41646147';
$pass = 'oYAOwqysRFVzrO'; // Copie exatamente como no painel ou altere no ícone de lápis se precisar
$port = 3306;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}