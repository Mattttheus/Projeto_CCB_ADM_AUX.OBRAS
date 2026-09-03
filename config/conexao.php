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

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}

/*
|--------------------------------------------------------------------------
| CLASSES DE COMPATIBILIDADE POSTGRESQL (SUPABASE) <-> MYSQLI
|--------------------------------------------------------------------------
*/

if (!class_exists('PgsqlResultAdapter')) {
    class PgsqlResultAdapter {
        private array $rows;
        private int $pointer = 0;
        public int $num_rows;

        public function __construct(array $rows) {
            $this->rows = array_values($rows);
            $this->num_rows = count($this->rows);
        }

        public function fetch_assoc(): ?array {
            if ($this->pointer < $this->num_rows) {
                return $this->rows[$this->pointer++];
            }
            return null;
        }

        public function fetch_all(int $mode = MYSQLI_ASSOC): array {
            return $this->rows;
        }
    }
}

if (!class_exists('PgsqlStmtAdapter')) {
    class PgsqlStmtAdapter {
        private PDOStatement $stmt;
        private PDO $pdo;
        private object $adapter;
        private array $params = [];
        private ?array $lastResults = null;
        public ?string $error = null;

        public function __construct(PDOStatement $stmt, PDO $pdo, object $adapter) {
            $this->stmt = $stmt;
            $this->pdo = $pdo;
            $this->adapter = $adapter;
        }

        public function bind_param(string $types, mixed &...$vars): bool {
            $this->params = [];
            foreach ($vars as &$var) {
                $this->params[] = &$var;
            }
            return true;
        }

        public function execute(): bool {
            try {
                $success = $this->stmt->execute($this->params);
                if ($success) {
                    if ($this->stmt->columnCount() > 0) {
                        $this->lastResults = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    try {
                        $this->adapter->insert_id = (int) $this->pdo->lastInsertId();
                    } catch (\Throwable $t) {}
                }
                return $success;
            } catch (\Throwable $e) {
                $this->error = $e->getMessage();
                $this->adapter->error = $e->getMessage();
                return false;
            }
        }

        public function get_result(): PgsqlResultAdapter|bool {
            if ($this->lastResults !== null) {
                return new PgsqlResultAdapter($this->lastResults);
            }
            return new PgsqlResultAdapter([]);
        }

        public function close(): bool {
            return true;
        }
    }
}

if (!class_exists('PgsqlMysqliAdapter')) {
    class PgsqlMysqliAdapter {
        private PDO $pdo;
        public int $connect_errno = 0;
        public ?string $connect_error = null;
        public ?string $error = null;
        public int $insert_id = 0;

        public function __construct(PDO $pdo) {
            $this->pdo = $pdo;
        }

        public function set_charset(string $charset): bool {
            return true;
        }

        public function real_escape_string(string $string): string {
            return addslashes($string);
        }

        public static function translateSql(string $sql): string {
            $sql = preg_replace(
                '/ON DUPLICATE KEY UPDATE\s+valor_orcado\s*=\s*VALUES\(valor_orcado\),\s*atualizado_em\s*=\s*CURRENT_TIMESTAMP/i',
                'ON CONFLICT (obra_id) DO UPDATE SET valor_orcado = EXCLUDED.valor_orcado, atualizado_em = CURRENT_TIMESTAMP',
                $sql
            );
            $sql = str_ireplace('CURDATE()', 'CURRENT_DATE', $sql);
            $sql = preg_replace(
                '/GROUP_CONCAT\s*\(\s*([^O]+)\s+ORDER BY\s+([^\s]+)\s+SEPARATOR\s+[\'"]([^\'"]+)[\'"]\s*\)/i',
                'STRING_AGG($1, \'$3\' ORDER BY $2)',
                $sql
            );
            if (preg_match('/SHOW TABLES LIKE [\'"]([^\'"]+)[\'"]/i', $sql, $m)) {
                $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = '{$m[1]}'";
            }
            if (preg_match('/SHOW COLUMNS FROM ([^\s;]+)/i', $sql, $m)) {
                $table = trim($m[1], '`');
                $sql = "SELECT column_name AS \"Field\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '{$table}'";
            }
            return $sql;
        }

        public function query(string $sql): PgsqlResultAdapter|bool {
            $translated = self::translateSql($sql);
            try {
                $stmt = $this->pdo->query($translated);
                if (!$stmt) {
                    $this->error = implode(' ', $this->pdo->errorInfo());
                    return false;
                }
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->error = null;
                return new PgsqlResultAdapter($rows);
            } catch (\Throwable $e) {
                $this->error = $e->getMessage();
                return false;
            }
        }

        public function prepare(string $sql): PgsqlStmtAdapter|bool {
            $translated = self::translateSql($sql);
            try {
                $stmt = $this->pdo->prepare($translated);
                if (!$stmt) {
                    $this->error = implode(' ', $this->pdo->errorInfo());
                    return false;
                }
                return new PgsqlStmtAdapter($stmt, $this->pdo, $this);
            } catch (\Throwable $e) {
                $this->error = $e->getMessage();
                return false;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO DO BANCO (SUPABASE POSTGRESQL / MYSQL)
|--------------------------------------------------------------------------
*/

$dbUrl  = getenv('SUPABASE_DATABASE_URL') ?: getenv('DATABASE_URL') ?: getenv('POSTGRES_URL');
$driver = strtolower(getenv('DB_DRIVER') ?: '');

$host   = 'localhost';
$port   = 3306;
$user   = '';
$pass   = '';
$dbname = '';

if ($dbUrl) {
    $parsedUrl = parse_url($dbUrl);
    if (is_array($parsedUrl)) {
        $host   = $parsedUrl['host'] ?? 'localhost';
        $port   = (int) ($parsedUrl['port'] ?? 5432);
        $user   = $parsedUrl['user'] ?? '';
        $pass   = isset($parsedUrl['pass']) ? urldecode($parsedUrl['pass']) : '';
        $dbname = ltrim($parsedUrl['path'] ?? 'postgres', '/');
        $driver = $driver ?: 'pgsql';
    }
} else {
    $host   = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'auxiliar_obras';
    $user   = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
    $pass   = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
    $port   = (int) (getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306);
}

if (!$driver) {
    if ($port === 5432 || $port === 6543 || str_contains($host, 'supabase.co') || str_contains($host, 'supabase.com')) {
        $driver = 'pgsql';
    } else {
        $driver = 'mysql';
    }
}

if ($host === '' || $dbname === '' || $user === '' || getenv('DB_PASSWORD') === false && !$dbUrl) {
    error_log('Configuração do banco incompleta: defina DB_HOST, DB_NAME, DB_USER e DB_PASSWORD.');
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados. Verifique as configurações do ambiente.');
}

/*
|--------------------------------------------------------------------------
| PDO & CONEXÃO
|--------------------------------------------------------------------------
*/

try {
    if ($driver === 'pgsql' || $driver === 'postgres') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]);
        $conn = new PgsqlMysqliAdapter($pdo);
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]);

        $conn = new mysqli($host, $user, $pass, $dbname, $port);
        if ($conn->connect_errno) {
            error_log('Erro na conexão MySQLi: ' . $conn->connect_error);
            http_response_code(500);
            exit('Não foi possível conectar ao banco de dados. Verifique as configurações do ambiente.');
        }
        $conn->set_charset('utf8mb4');
    }
} catch (PDOException $e) {
    error_log('Erro na conexão PDO: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados. Verifique as configurações do ambiente.');
}


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