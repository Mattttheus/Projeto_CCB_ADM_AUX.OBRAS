<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Application\Notification\AdminNotificationService;

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
    header('Location: dashboard.php');
    exit;
}

$stmtProject = $conn->prepare('SELECT obra_id FROM chamados WHERE id = ? LIMIT 1');
if (!$stmtProject) {
    http_response_code(500);
    exit('Não foi possível validar o chamado.');
}
$stmtProject->bind_param('i', $chamado_id);
$stmtProject->execute();
$call = $stmtProject->get_result()->fetch_assoc();
$stmtProject->close();

if (!$call || !Auth::canAccessProject($conn, (int) $call['obra_id'])) {
    http_response_code(403);
    exit('Você não tem acesso a este chamado.');
}

$stmt = $conn->prepare("UPDATE chamados SET status = 'fechado', data_fechamento = NOW(), fechado_por = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('ii', $user_id, $chamado_id);
    if (!$stmt->execute()) {
        error_log('fechar_chamado execute failed: ' . $stmt->error);
    } else {
        try {
            (new AdminNotificationService($conn))->notifyClosedCall($chamado_id);
        } catch (Throwable $exception) {
            error_log('Falha ao enfileirar aviso de chamado concluído: ' . $exception->getMessage());
        }
    }
    $stmt->close();
} else {
    error_log('fechar_chamado prepare failed: ' . $conn->error);
}

header('Location: dashboard.php');
exit;
?>
