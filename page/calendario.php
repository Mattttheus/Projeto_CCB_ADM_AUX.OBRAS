<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Application\Activity\ActivityService;
use App\Core\Auth;
use App\Core\Csrf;
use App\Infrastructure\Persistence\MySqlActivityRepository;

Auth::requireUser();

if (!Auth::hasFullProjectAccess()) {
    header('Location: gerenciar_obra.php');
    exit;
}

$repository = new MySqlActivityRepository($conn);
$service = new ActivityService($repository);
$action = $_GET['action'] ?? '';

if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        if ($action === 'eventos') {
            echo json_encode($repository->generalCalendarEvents(), JSON_UNESCAPED_UNICODE);
        } elseif ($action === 'salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            $service->saveGeneralActivity($payload);
            echo json_encode(['status' => 'ok']);
        } elseif ($action === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                throw new InvalidArgumentException('Atividade inválida.');
            }
            $repository->deleteGeneralActivity((int) $id);
            echo json_encode(['status' => 'ok']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Ação não encontrada.']);
        }
    } catch (Throwable $exception) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $exception->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agenda corporativa | Auxiliar Obras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <link href="../assets/css/corporate-calendar.css" rel="stylesheet">
</head>
<body>
    <header class="app-header">
        <div class="container-xl d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <div><div class="eyebrow">Planejamento operacional</div><h1 class="page-title">Agenda corporativa</h1></div>
            <a href="dashboard.php" class="btn btn-outline-light"><i class="bi bi-grid-1x2 me-2"></i>Painel</a>
        </div>
    </header>
    <main class="container-xl py-4 py-lg-5">
        <div class="surface-card p-3 p-lg-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                <div><h2 class="section-heading h5 mb-1">Compromissos e rotinas</h2><p class="section-caption mb-0">Selecione um dia para cadastrar uma atividade. Rotinas semanais são exibidas em verde.</p></div>
                <a href="obras_calendario.php" class="btn btn-brand"><i class="bi bi-kanban me-2"></i>Cronograma de obras</a>
            </div>
            <div id="calendar" aria-label="Calendário de atividades"></div>
        </div>
    </main>

    <div class="modal fade" id="activityModal" tabindex="-1" aria-labelledby="activityModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
            <div class="modal-header"><h2 class="modal-title fs-5" id="activityModalTitle">Nova atividade</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">
                <input type="hidden" id="activityId">
                <div class="mb-3"><label for="title" class="form-label">Título</label><input type="text" id="title" class="form-control" maxlength="150" required></div>
                <div class="mb-3"><label for="description" class="form-label">Descrição</label><textarea id="description" class="form-control" rows="3"></textarea></div>
                <div class="row g-3"><div class="col-md-6"><label for="date" class="form-label">Data</label><input type="date" id="date" class="form-control" required></div><div class="col-md-6"><label for="type" class="form-label">Frequência</label><select id="type" class="form-select"><option value="unico">Uma vez</option><option value="recorrente">Semanal</option></select></div></div>
                <p id="formFeedback" class="text-danger small mt-3 mb-0 d-none" role="alert"></p>
            </div>
            <div class="modal-footer justify-content-between"><button type="button" id="deleteButton" class="btn btn-outline-danger d-none">Excluir</button><div class="ms-auto"><button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancelar</button><button type="button" id="saveButton" class="btn btn-brand">Salvar atividade</button></div></div>
        </div></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
    const modalElement = document.getElementById('activityModal');
    const csrfToken = <?= json_encode(Csrf::token()) ?>;
    const modal = new bootstrap.Modal(modalElement);
    const feedback = document.getElementById('formFeedback');
    let calendar;
    const fields = { id: document.getElementById('activityId'), title: document.getElementById('title'), description: document.getElementById('description'), date: document.getElementById('date'), type: document.getElementById('type') };
    function showError(message) { feedback.textContent = message; feedback.classList.remove('d-none'); }
    function resetForm(date = '') { fields.id.value = ''; fields.title.value = ''; fields.description.value = ''; fields.date.value = date; fields.type.value = 'unico'; feedback.classList.add('d-none'); document.getElementById('deleteButton').classList.add('d-none'); fields.type.disabled = false; }
    function openNewActivity(date) { resetForm(date); document.getElementById('activityModalTitle').textContent = 'Nova atividade'; modal.show(); }
    async function request(action, data) { const response = await fetch(`calendario.php?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify(data) }); const result = await response.json(); if (!response.ok || result.status !== 'ok') throw new Error(result.message || 'Não foi possível concluir a operação.'); }
    document.addEventListener('DOMContentLoaded', () => {
        calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
            initialView: 'dayGridMonth', locale: 'pt-br', firstDay: 1, height: 'auto', selectable: true, buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia' }, events: 'calendario.php?action=eventos',
            dateClick: info => openNewActivity(info.dateStr),
            eventClick: info => { if (info.event.id.startsWith('rec_')) return; resetForm(); fields.id.value = info.event.id; fields.title.value = info.event.title; fields.description.value = info.event.extendedProps.descricao || ''; fields.date.value = info.event.startStr; fields.type.value = 'unico'; fields.type.disabled = true; document.getElementById('deleteButton').classList.remove('d-none'); document.getElementById('activityModalTitle').textContent = 'Editar atividade'; modal.show(); }
        }); calendar.render();
    });
    document.getElementById('saveButton').addEventListener('click', async () => { try { feedback.classList.add('d-none'); await request('salvar', { id: fields.id.value, titulo: fields.title.value, descricao: fields.description.value, data: fields.date.value, tipo: fields.type.value }); calendar.refetchEvents(); modal.hide(); } catch (error) { showError(error.message); } });
    document.getElementById('deleteButton').addEventListener('click', async () => { if (!confirm('Excluir esta atividade?')) return; try { await request('excluir', { id: fields.id.value }); calendar.refetchEvents(); modal.hide(); } catch (error) { showError(error.message); } });
    </script>
</body>
</html>
