<?php

declare(strict_types=1);

$envFile = __DIR__ . '/../.env';
if (is_file($envFile) && is_readable($envFile)) {
    $environment = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($environment)) {
        foreach ($environment as $key => $value) {
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }
}

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

    $host   = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'auxiliar_obras';
    $user   = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
    $pass   = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
    $port   = (int) (getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306);

} else {

    $host   = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '';
    $dbname = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: '';
    $user   = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: '';
    $pass   = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
    $port   = (int) (getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306);

}

if ($host === '' || $dbname === '' || $user === '' || getenv('DB_PASSWORD') === false) {
    error_log('Configuração do banco incompleta: defina DB_HOST, DB_NAME, DB_USER e DB_PASSWORD.');
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados. Verifique as configurações do ambiente.');
}


/*
|--------------------------------------------------------------------------
| PDO
|--------------------------------------------------------------------------
*/

try {

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );

} catch (PDOException $e) {
    error_log('Erro na conexão PDO: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados. Verifique as configurações do ambiente.');

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
    $dbname,
    $port
);


if ($conn->connect_errno) {
    error_log('Erro na conexão MySQLi: ' . $conn->connect_error);
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados. Verifique as configurações do ambiente.');

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

/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO DO EMAIL
|--------------------------------------------------------------------------
*/

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USER', getenv('MAIL_USER') ?: 'seu_login_smtp_brevo');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'sua_chave_smtp_brevo');

?>