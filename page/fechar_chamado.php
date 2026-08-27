<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;

Auth::requireUser();
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Falha na conexão: verifique config/conexao.php');
}

$chamado_id = isset($_POST['chamado_id']) ? (int)$_POST['chamado_id'] : 0;
$user_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

try {
    Csrf::validate($_POST['_token'] ?? null);
} catch (Throwable $exception) {
    http_response_code(419);
    exit($exception->getMessage());
}

if ($chamado_id <= 0) {
    // nothing to do
    $redir = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    header('Location: ' . $redir);
    exit;
}

$stmt = $conn->prepare("UPDATE chamados SET status = 'fechado', data_fechamento = NOW(), fechado_por = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('ii', $user_id, $chamado_id);
    if (!$stmt->execute()) {
        error_log('fechar_chamado execute failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    error_log('fechar_chamado prepare failed: ' . $conn->error);
}

$redir = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header('Location: ' . $redir);
exit;
?>
