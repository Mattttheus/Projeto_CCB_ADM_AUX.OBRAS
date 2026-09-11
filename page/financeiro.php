<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Application\Finance\FinancialService;
use App\Core\Auth;
use App\Core\Csrf;
use App\Domain\Finance\FinancialCategory;
use App\Infrastructure\Persistence\MySqlFinancialRepository;

Auth::requireUser();

$repository = new MySqlFinancialRepository($conn);
$service = new FinancialService($repository);
$hasFullProjectAccess = Auth::hasFullProjectAccess();
$userId = (int) $_SESSION['usuario_id'];
$success = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);
$error = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::validate($_POST['_token'] ?? null);
        $projectId = (int) ($_POST['obra_id'] ?? 0);
        if (!Auth::canAccessProject($conn, $projectId)) {
            throw new RuntimeException('Você não tem acesso financeiro a esta obra.');
        }
        if (($_POST['action'] ?? '') === 'novo_lancamento') { $service->register($_POST); $_SESSION['flash_success'] = 'Lançamento financeiro registrado.'; }
        if (($_POST['action'] ?? '') === 'definir_orcamento') { $service->setBudget($_POST); $_SESSION['flash_success'] = 'Orçamento da obra atualizado.'; }
        header('Location: financeiro.php' . (!empty($_POST['obra_filtro']) ? '?obra_id=' . (int) $_POST['obra_filtro'] : '')); exit;
    }
    $filterId = filter_var($_GET['obra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    if ($filterId && !Auth::canAccessProject($conn, $filterId)) {
        throw new RuntimeException('Você não tem acesso financeiro a esta obra.');
    }
    $projects = $hasFullProjectAccess ? $repository->projects() : $repository->projectsForUser($userId);
    $overview = $hasFullProjectAccess ? $repository->overview() : $repository->overviewForUser($userId);
    $categories = $hasFullProjectAccess ? $repository->categoryTotals($filterId) : $repository->categoryTotalsForUser($userId, $filterId);
    $entries = $hasFullProjectAccess ? $repository->entries($filterId) : $repository->entriesForUser($userId, $filterId);
} catch (Throwable $exception) { $error = $exception->getMessage(); $projects = $overview = $categories = $entries = []; $filterId = null; }
$totalSpent = array_sum(array_map(static fn($row) => (float) $row['total_realizado'], $overview));
$totalBudget = array_sum(array_map(static fn($row) => (float) $row['valor_orcado'], $overview));
$totalMaterials = array_sum(array_map(static fn($row) => (float) $row['materiais'], $overview));
$totalOperational = array_sum(array_map(static fn($row) => (float) $row['operacionais'], $overview));
function money(float $value): string { return 'R$ ' . number_format($value, 2, ',', '.'); }
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestão financeira | Auxiliar Obras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/corporate-calendar.css" rel="stylesheet">
    <link href="../assets/css/app-shell.css" rel="stylesheet">
</head>

<body class="app-page-body">
    <header class="app-header">
        <div class="container-xl d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <div>
                <div class="eyebrow">Controle orçamentário</div>
                <h1 class="page-title">Gestão financeira de obras</h1>
            </div><div class="d-flex gap-2"><a href="relatorios.php" class="btn btn-light text-dark"><i class="bi bi-file-earmark-text me-2"></i>Relatórios</a><a href="dashboard.php" class="btn btn-outline-light"><i class="bi bi-grid-1x2 me-2"></i>Painel</a></div>
        </div>
    </header>
    <main class="container-xl py-4 py-lg-5">
        <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><?= htmlspecialchars($success) ?>
        </div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm">
            <strong>Configuração necessária.</strong> <?= htmlspecialchars($error) ?>
        </div><?php endif; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="surface-card finance-metric">
                    <div class="label">Gasto realizado</div>
                    <div class="value"><?= money($totalSpent) ?></div><small class="text-muted">Lançamentos
                        registrados</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="surface-card finance-metric">
                    <div class="label">Orçamento aprovado</div>
                    <div class="value"><?= money($totalBudget) ?></div><small class="text-muted">Base para
                        acompanhamento</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="surface-card finance-metric">
                    <div class="label">Consumo de materiais</div>
                    <div class="value"><?= money($totalMaterials) ?></div><small class="text-muted">Materiais
                        aplicados</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="surface-card finance-metric">
                    <div class="label">Custos operacionais</div>
                    <div class="value"><?= money($totalOperational) ?></div><small class="text-muted">Operação da
                        obra</small>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-lg-5">
                <section class="surface-card p-3 p-lg-4 h-100">
                    <h2 class="section-heading h5">Registrar despesa</h2>
                    <p class="section-caption mb-4">O valor total é calculado por quantidade × valor unitário.</p>
                    <form method="post"><?= Csrf::input() ?><input type="hidden" name="action"
                            value="novo_lancamento"><input type="hidden" name="obra_filtro"
                            value="<?= (int) $filterId ?>">
                        <div class="mb-3"><label class="form-label">Obra</label><select name="obra_id"
                                class="form-select" required>
                                <option value="">Selecione a obra</option><?php foreach ($projects as $project): ?>
                                <option value="<?= (int)$project['id'] ?>"
                                    <?= $filterId === (int)$project['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['nome']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Categoria</label><select name="categoria"
                                    class="form-select"><?php foreach (FinancialCategory::labels() as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Data</label><input type="date"
                                    name="data_lancamento" value="<?= date('Y-m-d') ?>" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-3"><label class="form-label">Descrição</label><input name="descricao"
                                class="form-control" maxlength="255" placeholder="Ex.: Cimento CP-II 50kg" required>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-6"><label class="form-label">Quantidade</label><input name="quantidade"
                                    type="number" min="0.01" step="0.01" value="1" class="form-control" required></div>
                            <div class="col-6"><label class="form-label">Valor unitário</label><input
                                    name="valor_unitario" type="number" min="0" step="0.01" class="form-control"
                                    placeholder="0,00" required></div>
                        </div><button class="btn btn-brand w-100 mt-4"><i class="bi bi-plus-lg me-2"></i>Registrar
                            lançamento</button>
                    </form>
                    <hr class="my-4">
                    <h3 class="section-heading h6">Definir orçamento</h3>
                    <form method="post" class="row g-2"><?= Csrf::input() ?><input type="hidden" name="action"
                            value="definir_orcamento"><input type="hidden" name="obra_filtro"
                            value="<?= (int) $filterId ?>">
                        <div class="col-7"><select name="obra_id" class="form-select form-select-sm" required>
                                <option value="">Obra</option><?php foreach ($projects as $project): ?><option
                                    value="<?= (int)$project['id'] ?>"><?= htmlspecialchars($project['nome']) ?>
                                </option><?php endforeach; ?>
                            </select></div>
                        <div class="col-5"><input type="number" name="valor_orcado" min="0" step="0.01"
                                class="form-control form-control-sm" placeholder="R$ 0,00" required></div>
                        <div class="col-12"><button class="btn btn-outline-primary btn-sm w-100">Salvar
                                orçamento</button></div>
                    </form>
                </section>
            </div>
            <div class="col-lg-7">
                <section class="surface-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between gap-3 align-items-start mb-3">
                        <div>
                            <h2 class="section-heading h5 mb-1">Orçamento x realizado</h2>
                            <p class="section-caption mb-0">Acompanhe a execução financeira de cada obra.</p>
                        </div>
                        <form method="get"><select name="obra_id" class="form-select form-select-sm"
                                onchange="this.form.submit()">
                                <option value="">Todas as obras</option><?php foreach ($projects as $project): ?><option
                                    value="<?= (int)$project['id'] ?>"
                                    <?= $filterId === (int)$project['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['nome']) ?></option><?php endforeach; ?>
                            </select></form>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Obra</th>
                                    <th>Orçamento</th>
                                    <th>Realizado</th>
                                    <th>Consumo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overview as $row): $spent=(float)$row['total_realizado'];$budget=(float)$row['valor_orcado'];$percent=$budget>0?min(100,($spent/$budget)*100):0; ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['nome']) ?></td>
                                    <td><?= money($budget) ?></td>
                                    <td><?= money($spent) ?><div class="progress mt-2">
                                            <div class="progress-bar <?= $budget && $spent>$budget?'bg-danger':'bg-success' ?>"
                                                style="width:<?= $percent ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="<?= $budget && $spent>$budget?'text-danger fw-bold':'' ?>">
                                        <?= $budget ? number_format(($spent/$budget)*100,1,',','.') . '%' : 'Sem orçamento' ?>
                                    </td>
                                </tr><?php endforeach; ?><?php if (!$overview): ?><tr>
                                    <td colspan="4" class="text-center text-muted py-4">Nenhuma obra cadastrada.</td>
                                </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <section class="surface-card p-3 p-lg-4 h-100">
                    <h2 class="section-heading h5">Consumo por categoria</h2>
                    <p class="section-caption">Distribuição dos gastos no período.</p>
                    <?php foreach ($categories as $category): ?><div
                        class="d-flex justify-content-between align-items-center border-bottom py-3"><span><span
                                class="category-dot me-2"></span><?= htmlspecialchars(FinancialCategory::labels()[$category['categoria']] ?? $category['categoria']) ?></span><strong><?= money((float)$category['total']) ?></strong>
                    </div><?php endforeach; ?><?php if (!$categories): ?><p class="text-muted small">Sem lançamentos
                        para apresentar.</p><?php endif; ?>
                </section>
            </div>
            <div class="col-lg-8">
                <section class="surface-card overflow-hidden">
                    <div class="p-3 p-lg-4 border-bottom">
                        <h2 class="section-heading h5 mb-1">Últimos lançamentos</h2>
                        <p class="section-caption mb-0">Até 100 registros, em ordem de data.</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Obra</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th>Quantidade</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($entries as $entry): ?><tr>
                                    <td><?= date('d/m/Y', strtotime($entry['data_lancamento'])) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($entry['nome_obra']) ?></td>
                                    <td><?= htmlspecialchars($entry['descricao']) ?></td>
                                    <td><span
                                            class="badge text-bg-light border text-dark"><?= htmlspecialchars(FinancialCategory::labels()[$entry['categoria']] ?? $entry['categoria']) ?></span>
                                    </td>
                                    <td><?= number_format((float)$entry['quantidade'],2,',','.') ?></td>
                                    <td class="text-end fw-semibold">
                                        <?= money((float)$entry['quantidade']*(float)$entry['valor_unitario']) ?></td>
                                </tr><?php endforeach; ?><?php if (!$entries): ?><tr>
                                    <td colspan="6" class="text-center text-muted py-4">Nenhum lançamento encontrado.
                                    </td>
                                </tr><?php endif; ?></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </main>
</body>

</html>