<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Application\Activity\ActivityService;
use App\Application\Notification\AdminNotificationService;
use App\Core\Auth;
use App\Core\Csrf;
use App\Domain\Activity\ActivityStatus;
use App\Infrastructure\Persistence\MySqlActivityRepository;

Auth::requireUser();

if (!Auth::hasFullProjectAccess()) {
    header('Location: gerenciar_obra.php');
    exit;
}

$repository = new MySqlActivityRepository($conn);
$service = new ActivityService($repository);
$notifications = new AdminNotificationService($conn);
$message = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$error = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'salvar_atividade') {
        Csrf::validate($_POST['_token'] ?? null);
        $service->createProjectActivity($_POST);
        $_SESSION['flash_success'] = 'Atividade vinculada ao cronograma com sucesso.';
        header('Location: obras_calendario.php');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mudar_status') {
        Csrf::validate($_POST['_token'] ?? null);
        $activityId = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $service->changeStatus($activityId, $status);
        if ($status === ActivityStatus::COMPLETED) {
            $notifications->notifyCompletedActivity($activityId);
        }
        $_SESSION['flash_success'] = 'Status da atividade atualizado.';
        header('Location: obras_calendario.php');
        exit;
    }
} catch (Throwable $exception) { $error = $exception->getMessage(); }

$today = date('Y-m-d');
$projects = $repository->projects();
$overdueActivities = $repository->overdueActivities($today);
$activities = $repository->allProjectActivities();
function statusMeta(array $activity, string $today): array { if ($activity['status'] !== ActivityStatus::COMPLETED && $activity['data_limite'] < $today) return ['Em atraso', 'text-bg-danger']; return match ($activity['status']) { ActivityStatus::COMPLETED => ['Concluída', 'text-bg-success'], ActivityStatus::IN_PROGRESS => ['Em andamento', 'text-bg-warning'], default => ['Pendente', 'text-bg-secondary'] }; }
?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cronograma de obras | Auxiliar Obras</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="../assets/css/corporate-calendar.css" rel="stylesheet"></head>
<body>
<header class="app-header"><div class="container-xl d-flex flex-wrap gap-3 justify-content-between align-items-center"><div><div class="eyebrow">Gestão de projetos</div><h1 class="page-title">Cronograma de obras</h1></div><div class="d-flex gap-2"><a href="calendario.php" class="btn btn-outline-light"><i class="bi bi-calendar3 me-2"></i>Agenda</a><a href="dashboard.php" class="btn btn-light text-dark">Painel</a></div></div></header>
<main class="container-xl py-4 py-lg-5">
<?php if ($message): ?><div class="alert alert-success border-0 shadow-sm"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="surface-card metric mb-4 overflow-hidden"><div class="p-3 p-lg-4 d-flex justify-content-between align-items-center"><div><h2 class="section-heading h5 mb-1">Prazos que exigem atenção</h2><p class="section-caption mb-0">Atividades vencidas e ainda não concluídas.</p></div><span class="badge text-bg-danger rounded-pill px-3 py-2"><?= count($overdueActivities) ?> em atraso</span></div>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Obra</th><th>Atividade</th><th>Prazo</th><th class="text-end">Ação</th></tr></thead><tbody><?php foreach ($overdueActivities as $activity): ?><tr><td class="fw-semibold"><?= htmlspecialchars($activity['nome_obra'] ?? 'Geral') ?></td><td><?= htmlspecialchars($activity['titulo']) ?></td><td class="text-danger fw-semibold"><?= date('d/m/Y', strtotime($activity['data_limite'])) ?></td><td class="text-end"><form method="post" class="d-inline"><?= Csrf::input() ?><input type="hidden" name="action" value="mudar_status"><input type="hidden" name="id" value="<?= (int) $activity['id'] ?>"><input type="hidden" name="status" value="concluida"><button class="btn btn-sm btn-outline-success">Concluir</button></form></td></tr><?php endforeach; ?><?php if (!$overdueActivities): ?><tr><td colspan="4" class="text-center text-muted py-4">Nenhuma atividade em atraso.</td></tr><?php endif; ?></tbody></table></div></section>
<div class="row g-4"><div class="col-lg-4"><section class="surface-card p-3 p-lg-4"><h2 class="section-heading h5">Nova atividade</h2><p class="section-caption mb-4">Inclua uma entrega no cronograma da obra.</p><form method="post"><?= Csrf::input() ?><input type="hidden" name="action" value="salvar_atividade"><div class="mb-3"><label class="form-label" for="obra_id">Obra</label><select id="obra_id" name="obra_id" class="form-select"><option value="">Atividade geral</option><?php foreach ($projects as $project): ?><option value="<?= (int) $project['id'] ?>"><?= htmlspecialchars($project['nome']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="titulo">Título</label><input id="titulo" name="titulo" class="form-control" maxlength="150" required></div><div class="mb-3"><label class="form-label" for="descricao">Descrição</label><textarea id="descricao" name="descricao" class="form-control" rows="3"></textarea></div><div class="mb-3"><label class="form-label" for="data_limite">Prazo de conclusão</label><input id="data_limite" name="data_limite" type="date" class="form-control" required></div><div class="mb-4"><label class="form-label" for="status">Status inicial</label><select id="status" name="status" class="form-select"><option value="pendente">Pendente</option><option value="em_andamento">Em andamento</option><option value="concluida">Concluída</option></select></div><button class="btn btn-brand w-100"><i class="bi bi-plus-lg me-2"></i>Adicionar ao cronograma</button></form></section></div>
<div class="col-lg-8"><section class="surface-card overflow-hidden"><div class="p-3 p-lg-4 border-bottom"><h2 class="section-heading h5 mb-1">Atividades programadas</h2><p class="section-caption mb-0">Visão consolidada dos compromissos cadastrados.</p></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Obra</th><th>Atividade</th><th>Prazo</th><th>Status</th><th class="text-end">Atualizar</th></tr></thead><tbody><?php foreach ($activities as $activity): [$statusLabel, $statusClass] = statusMeta($activity, $today); ?><tr><td class="fw-semibold"><?= htmlspecialchars($activity['nome_obra'] ?? 'Geral') ?></td><td><div><?= htmlspecialchars($activity['titulo']) ?></div><?php if ($activity['descricao']): ?><small class="text-muted"><?= htmlspecialchars($activity['descricao']) ?></small><?php endif; ?></td><td><?= date('d/m/Y', strtotime($activity['data_limite'])) ?></td><td><span class="badge status <?= $statusClass ?>"><?= $statusLabel ?></span></td><td class="text-end"><?php if ($activity['status'] !== ActivityStatus::COMPLETED): ?><form method="post" class="d-inline"><?= Csrf::input() ?><input type="hidden" name="action" value="mudar_status"><input type="hidden" name="id" value="<?= (int) $activity['id'] ?>"><input type="hidden" name="status" value="concluida"><button class="btn btn-sm btn-outline-success">Concluir</button></form><?php else: ?><i class="bi bi-check2-circle text-success" aria-label="Concluída"></i><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$activities): ?><tr><td colspan="5" class="text-center text-muted py-5">Ainda não há atividades programadas.</td></tr><?php endif; ?></tbody></table></div></section></div></div>
</main></body></html>
