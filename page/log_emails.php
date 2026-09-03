<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../config/mailer.php';

use App\Core\Auth;
use App\Core\Csrf;

Auth::requireAdmin();

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::validate($_POST['_token'] ?? null);
        $emailId = filter_var($_POST['email_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $action = (string) ($_POST['action'] ?? '');
        if (!$emailId || !in_array($action, ['reenviar', 'enviar_agora'], true)) {
            throw new InvalidArgumentException('Solicitação de e-mail inválida.');
        }
        if ($action === 'reenviar') {
            $statement = $conn->prepare("UPDATE fila_emails SET status = 'pendente', tentativas = 0, erro_mensagem = NULL, data_envio = NULL WHERE id = ? AND status = 'erro'");
            if (!$statement) {
                throw new RuntimeException('Não foi possível preparar o reenvio do e-mail.');
            }
            $statement->bind_param('i', $emailId);
            $statement->execute();
            $success = $statement->affected_rows > 0
                ? 'E-mail incluído novamente na fila de envio.'
                : 'O e-mail não está em estado de erro ou não foi encontrado.';
            $statement->close();
        } else {
            $statement = $conn->prepare("SELECT destinatario, assunto, mensagem_html FROM fila_emails WHERE id = ? AND status IN ('pendente', 'erro') LIMIT 1");
            if (!$statement) {
                throw new RuntimeException('Não foi possível localizar o e-mail na fila.');
            }
            $statement->bind_param('i', $emailId);
            $statement->execute();
            $email = $statement->get_result()->fetch_assoc();
            $statement->close();
            if (!$email) {
                throw new RuntimeException('O e-mail já foi enviado ou não foi encontrado.');
            }

            try {
                enviarEmailFila($email['destinatario'], $email['assunto'], $email['mensagem_html']);

                $sent = $conn->prepare("UPDATE fila_emails SET status = 'enviado', data_envio = NOW(), erro_mensagem = NULL WHERE id = ?");
                if (!$sent) throw new RuntimeException('Não foi possível atualizar o status do e-mail.');
                $sent->bind_param('i', $emailId);
                $sent->execute();
                $sent->close();
                $success = 'E-mail enviado com sucesso.';
            } catch (Throwable $exception) {
                $message = substr($exception->getMessage(), 0, 1000);
                $failed = $conn->prepare("UPDATE fila_emails SET tentativas = tentativas + 1, erro_mensagem = ?, status = CASE WHEN tentativas + 1 >= 3 THEN 'erro' ELSE 'pendente' END WHERE id = ?");
                if ($failed) {
                    $failed->bind_param('si', $message, $emailId);
                    $failed->execute();
                    $failed->close();
                }
                throw new RuntimeException('Não foi possível enviar o e-mail. Verifique o último erro.');
            }
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

// Consultar Estatísticas da Fila
$totais = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros
FROM fila_emails")->fetch_assoc();

// Buscar os últimos 50 registros
$logFila = $conn->query("SELECT * FROM fila_emails ORDER BY id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor da Fila de E-mails - Auxiliar Obras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/app-shell.css" rel="stylesheet">
</head>

<body class="app-monitor-body p-4">

    <div class="container-fluid">
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1"><i class="bi bi-mailbox2 text-primary me-2"></i> Fila de Disparo de
                    E-mails</h4>
                <p class="text-muted small mb-0">Monitoramento do processamento assíncrono em segundo plano (Cron Job).
                </p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i
                    class="bi bi-arrow-left"></i> Voltar</a>
        </div>

        <!-- CARDS KPI DE STATUS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card monitor-card p-3 border-start border-4 border-primary">
                    <small class="text-muted fw-bold">TOTAL PROCESSADOS</small>
                    <h3 class="fw-bold mb-0 text-dark"><?= $totais['total'] ?? 0 ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card monitor-card p-3 border-start border-4 border-warning">
                    <small class="text-muted fw-bold">PENDENTES NA FILA</small>
                    <h3 class="fw-bold mb-0 text-warning"><?= $totais['pendentes'] ?? 0 ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card monitor-card p-3 border-start border-4 border-success">
                    <small class="text-muted fw-bold">ENVIADOS COM SUCESSO</small>
                    <h3 class="fw-bold mb-0 text-success"><?= $totais['enviados'] ?? 0 ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card monitor-card p-3 border-start border-4 border-danger">
                    <small class="text-muted fw-bold">FALHAS / ERROS</small>
                    <h3 class="fw-bold mb-0 text-danger"><?= $totais['erros'] ?? 0 ?></h3>
                </div>
            </div>
        </div>

        <!-- TABELA DE LOGS -->
        <div class="card monitor-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-compact">
                        <thead class="table-light">
                            <tr>
                                <th>#ID</th>
                                <th>Destinatário</th>
                                <th>Assunto</th>
                                <th>Status</th>
                                <th>Tentativas</th>
                                <th>Data Criação</th>
                                <th>Último Erro</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logFila && $logFila->num_rows > 0): ?>
                            <?php while ($log = $logFila->fetch_assoc()): 
                                $badge = 'badge-soft-warning';
                                if ($log['status'] === 'enviado') $badge = 'badge-soft-success';
                                if ($log['status'] === 'erro') $badge = 'badge-soft-danger';
                            ?>
                            <tr>
                                <td class="fw-bold">#<?= $log['id'] ?></td>
                                <td><?= htmlspecialchars($log['destinatario']) ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($log['assunto']) ?></td>
                                <td><span class="badge <?= $badge ?>"><?= strtoupper($log['status']) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= $log['tentativas'] ?>/3</span>
                                </td>
                                <td><?= !empty($log['created_at']) ? date('d/m/Y H:i:s', strtotime($log['created_at'])) : '-' ?>
                                </td>
                                <td class="text-danger">
                                    <small><?= htmlspecialchars($log['erro_mensagem'] ?? '-') ?></small>
                                </td>
                                <td class="text-end">
                                    <?php if (in_array($log['status'], ['pendente', 'erro'], true)): ?>
                                    <form method="post" class="d-inline">
                                        <?= Csrf::input() ?>
                                        <input type="hidden" name="action" value="enviar_agora">
                                        <input type="hidden" name="email_id" value="<?= (int) $log['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Enviar agora</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($log['status'] === 'erro'): ?>
                                    <form method="post" class="d-inline">
                                        <?= Csrf::input() ?>
                                        <input type="hidden" name="action" value="reenviar">
                                        <input type="hidden" name="email_id" value="<?= (int) $log['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Reenviar</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Nenhum registro na fila até o
                                    momento.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>

</html>