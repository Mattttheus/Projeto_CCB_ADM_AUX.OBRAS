<?php
$host = "localhost";
$user = "root";
$pass = "4605"; // Coloque sua senha do MySQL local se houver
$db   = "auxiliar_obras";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>