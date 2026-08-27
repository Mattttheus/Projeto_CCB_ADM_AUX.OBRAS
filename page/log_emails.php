<?php
session_start();
if (empty($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once("../config/conexao.php");

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
    <style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f4f6f9;
    }

    .card-custom {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }

    .badge-soft-success {
        background-color: #dcfce7;
        color: #166534;
    }

    .badge-soft-warning {
        background-color: #fef9c3;
        color: #854d0e;
    }

    .badge-soft-danger {
        background-color: #fee2e2;
        color: #991b1b;
    }
    </style>
</head>

<body class="p-4">

    <div class="container-fluid">
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
                <div class="card card-custom p-3 border-start border-4 border-primary">
                    <small class="text-muted fw-bold">TOTAL PROCESSADOS</small>
                    <h3 class="fw-bold mb-0 text-dark"><?= $totais['total'] ?? 0 ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-3 border-start border-4 border-warning">
                    <small class="text-muted fw-bold">PENDENTES NA FILA</small>
                    <h3 class="fw-bold mb-0 text-warning"><?= $totais['pendentes'] ?? 0 ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-3 border-start border-4 border-success">
                    <small class="text-muted fw-bold">ENVIADOS COM SUCESSO</small>
                    <h3 class="fw-bold mb-0 text-success"><?= $totais['enviados'] ?? 0 ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-3 border-start border-4 border-danger">
                    <small class="text-muted fw-bold">FALHAS / ERROS</small>
                    <h3 class="fw-bold mb-0 text-danger"><?= $totais['erros'] ?? 0 ?></h3>
                </div>
            </div>
        </div>

        <!-- TABELA DE LOGS -->
        <div class="card card-custom">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>#ID</th>
                                <th>Destinatário</th>
                                <th>Assunto</th>
                                <th>Status</th>
                                <th>Tentativas</th>
                                <th>Data Criação</th>
                                <th>Último Erro</th>
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
                                <td><?= date('d/m/Y H:i:s', strtotime($log['data_criacao'])) ?></td>
                                <td class="text-danger">
                                    <small><?= htmlspecialchars($log['erro_mensagem'] ?? '-') ?></small></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhum registro na fila até o
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