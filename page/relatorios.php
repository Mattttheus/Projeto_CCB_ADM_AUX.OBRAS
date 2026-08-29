<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Application\Report\PdfReport;
use App\Core\Auth;
use App\Core\Csrf;
use App\Infrastructure\Persistence\MySqlReportRepository;

Auth::requireUser();
$repository = new MySqlReportRepository($conn);
$userId = (int) $_SESSION['usuario_id'];
$hasFullAccess = Auth::hasFullProjectAccess();
$projects = $repository->projectsForUser($userId, $hasFullAccess);
$projectId = filter_var($_REQUEST['obra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$type = (string) ($_REQUEST['tipo'] ?? 'orcamento');
$types = ['orcamento' => 'Pedido de orçamento', 'financeiro' => 'Relatório financeiro', 'atividades' => 'Relatório de atividades', 'compras' => 'Relatório de compras'];
$success = '';
$error = '';

try {
    if ($projectId && !Auth::canAccessProject($conn, $projectId)) throw new RuntimeException('Você não tem acesso a esta obra.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::validate($_POST['_token'] ?? null);
        if (!$projectId || !isset($types[$type])) throw new InvalidArgumentException('Selecione uma obra e um relatório válido.');
        $report = $repository->report($projectId, $type);
        if (($_POST['action'] ?? '') === 'baixar_pdf') {
            $pdf = (new PdfReport())->render($report['title'], $report['columns'], $report['rows']);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="relatorio-' . $type . '-obra-' . $projectId . '.pdf"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;
        }
        if (($_POST['action'] ?? '') === 'enviar_email') {
            $recipient = filter_var(trim((string) ($_POST['destinatario'] ?? '')), FILTER_VALIDATE_EMAIL);
            if (!$recipient) throw new InvalidArgumentException('Informe um e-mail de destino válido.');
            $html = '<h2>' . htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') . '</h2><table border="1" cellpadding="6" cellspacing="0"><thead><tr>';
            foreach ($report['columns'] as $column) $html .= '<th>' . htmlspecialchars($column, ENT_QUOTES, 'UTF-8') . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($report['rows'] as $row) { $html .= '<tr>'; foreach ($row as $cell) $html .= '<td>' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>'; $html .= '</tr>'; }
            $html .= '</tbody></table>';
            $statement = $conn->prepare("INSERT INTO fila_emails (destinatario, assunto, mensagem_html, status, tentativas) VALUES (?, ?, ?, 'pendente', 0)");
            if (!$statement) throw new RuntimeException('A fila de e-mails não está disponível.');
            $subject = $report['title'];
            $statement->bind_param('sss', $recipient, $subject, $html);
            if (!$statement->execute()) throw new RuntimeException('Não foi possível incluir o relatório na fila.');
            $statement->close();
            $success = 'Relatório incluído na fila de e-mails.';
        }
    }
} catch (Throwable $exception) { $error = $exception->getMessage(); }
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatórios | Auxiliar Obras</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/corporate-calendar.css" rel="stylesheet"></head>
<body><header class="app-header"><div class="container-xl d-flex justify-content-between align-items-center"><div><div class="eyebrow">Documentos gerenciais</div><h1 class="page-title">Relatórios da obra</h1></div><a href="financeiro.php" class="btn btn-outline-light">Financeiro</a></div></header>
<main class="container-xl py-4 py-lg-5"><section class="surface-card p-3 p-lg-4"><h2 class="section-heading h5">Gerar ou enviar relatório</h2><p class="section-caption">O relatório considera somente os dados da obra selecionada.</p><?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post" class="row g-3"><?= Csrf::input() ?><div class="col-md-5"><label for="obra_id" class="form-label">Obra</label><select id="obra_id" name="obra_id" class="form-select" required><option value="">Selecione a obra</option><?php foreach ($projects as $project): ?><option value="<?= (int) $project['id'] ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= htmlspecialchars($project['nome']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label for="tipo" class="form-label">Relatório</label><select id="tipo" name="tipo" class="form-select"><?php foreach ($types as $key => $label): ?><option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label for="destinatario" class="form-label">Enviar para</label><input id="destinatario" type="email" name="destinatario" class="form-control" placeholder="email@empresa.com"></div><div class="col-12 d-flex gap-2"><button name="action" value="baixar_pdf" class="btn btn-brand">Baixar PDF</button><button name="action" value="enviar_email" class="btn btn-outline-primary">Enviar por e-mail</button></div></form></section></main></body></html>