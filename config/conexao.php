<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO DO BANCO
|--------------------------------------------------------------------------
*/

$serverName = $_SERVER['SERVER_NAME'] ?? '';
$serverAddr = $_SERVER['SERVER_ADDR'] ?? '';

$is_local = (
    $serverName === 'localhost'
    || $serverName === '127.0.0.1'
    || $serverAddr === '127.0.0.1'
);


/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO LOCAL / ONLINE
|--------------------------------------------------------------------------
*/

if ($is_local) {

    $host   = 'localhost';
    $dbname = 'auxiliar_obras';
    $user   = 'root';
    $pass   = '4605';

} else {

    $host   = 'sqlXXX.infinityfree.com';
    $dbname = 'if0_41646147_auxiliar_obras';
    $user   = 'if0_41646147';
    $pass   = '4605';

}


/*
|--------------------------------------------------------------------------
| PDO
|--------------------------------------------------------------------------
*/

try {

    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );

} catch (PDOException $e) {

    die(
        'Erro na conexão PDO: '
        . $e->getMessage()
    );

}


/*
|--------------------------------------------------------------------------
| MYSQLI
|--------------------------------------------------------------------------
|
| Compatibilidade com o Dashboard atual.
|--------------------------------------------------------------------------
*/

$conn = new mysqli(
    $host,
    $user,
    $pass,
    $dbname
);


if ($conn->connect_errno) {

    die(
        'Erro na conexão MySQLi: '
        . $conn->connect_error
    );

}


/*
|--------------------------------------------------------------------------
| UTF-8
|--------------------------------------------------------------------------
*/

$conn->set_charset('utf8mb4');


/*
|--------------------------------------------------------------------------
| TESTE
|--------------------------------------------------------------------------
|
| Se chegou até aqui, temos:
|
| $pdo  -> PDO
| $conn -> MySQLi
|
|--------------------------------------------------------------------------
*/

?>