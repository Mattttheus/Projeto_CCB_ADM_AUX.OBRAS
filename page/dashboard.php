<?php
require_once __DIR__ . '/../app/bootstrap.php';

\App\Core\Auth::requireUser();
if (!isset($conn)) {
    die('Falha na conexão: a variável $conn não foi inicializada. Verifique config/conexao.php.');
}

$user_nome = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'Usuário';
$user_role = $_SESSION['tipo'] ?? $_SESSION['role'] ?? 'user';

if (!\App\Core\Auth::hasFullProjectAccess()) {
    header('Location: gerenciar_obra.php');
    exit;
}

$can_edit_status = in_array($user_role, ['admin','engenheiro','mestre_obras','user'], true);

/*
 * Dashboard Geral - Auxiliar Obras
 * Compatível com a estrutura utilizada nas páginas:
 * obras, atividades, compras, documentos_obras e chamados.
 */

function contar($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

function valor($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (float)($row['total'] ?? 0);
}

$hoje = date('Y-m-d');

/* =========================
   INDICADORES PRINCIPAIS
   ========================= */
$totalObras = contar($conn, "SELECT COUNT(*) AS total FROM obras");

$totalAtividades = contar($conn, "SELECT COUNT(*) AS total FROM atividades");

$atividadesConcluidas = contar(
    $conn,
    "SELECT COUNT(*) AS total FROM atividades WHERE status = 'concluida'"
);

$atividadesAtrasadas = contar(
    $conn,
    "SELECT COUNT(*) AS total
     FROM atividades
     WHERE data_limite < '$hoje'
       AND status <> 'concluida'"
);

$atividadesAndamento = contar(
    $conn,
    "SELECT COUNT(*) AS total FROM atividades WHERE status = 'em_andamento'"
);

$totalDocumentos = contar(
    $conn,
    "SELECT COUNT(*) AS total FROM documentos_obras"
);

$totalChamados = contar(
    $conn,
    "SELECT COUNT(*) AS total FROM chamados WHERE status <> 'fechado'"
);

$chamadosUrgentes = contar(
    $conn,
    "SELECT COUNT(*) AS total FROM chamados WHERE prioridade = 'vermelho' AND status <> 'fechado'"
);

$obrasList = $conn->query("SELECT id, nome FROM obras ORDER BY nome ASC");

$financeAvailable = ($conn->query("SHOW TABLES LIKE 'lancamentos_financeiros'")?->num_rows ?? 0) > 0
    && ($conn->query("SHOW TABLES LIKE 'orcamentos_obras'")?->num_rows ?? 0) > 0;
$totalOrcamento = 0;
$totalMateriaisFinanceiro = 0;
$totalOperacionalFinanceiro = 0;

if ($financeAvailable) {
    $financeKpis = $conn->query("SELECT
        COALESCE((SELECT SUM(valor_orcado) FROM orcamentos_obras), 0) AS orcamento,
        COALESCE(SUM(quantidade * valor_unitario), 0) AS realizado,
        COALESCE(SUM(CASE WHEN categoria = 'material' THEN quantidade * valor_unitario ELSE 0 END), 0) AS materiais,
        COALESCE(SUM(CASE WHEN categoria = 'operacional' THEN quantidade * valor_unitario ELSE 0 END), 0) AS operacional
        FROM lancamentos_financeiros")->fetch_assoc();
    $totalFinanceiro = (float) $financeKpis['realizado'];
    $totalOrcamento = (float) $financeKpis['orcamento'];
    $totalMateriaisFinanceiro = (float) $financeKpis['materiais'];
    $totalOperacionalFinanceiro = (float) $financeKpis['operacional'];
} else {
    $totalFinanceiro = valor($conn, "SELECT COALESCE(SUM(valor * quantidade), 0) AS total FROM compras");
}
$saldoOrcamento = $totalOrcamento - $totalFinanceiro;
$percentualOrcamento = $totalOrcamento > 0 ? min(100, ($totalFinanceiro / $totalOrcamento) * 100) : 0;

/* =========================
   PERCENTUAL DE CONCLUSÃO
   ========================= */
$percentualConclusao = $totalAtividades > 0
    ? round(($atividadesConcluidas / $totalAtividades) * 100)
    : 0;

/* =========================
   OBRAS + CUSTOS
   ========================= */
$obrasFinanceiro = $financeAvailable ? $conn->query("
    SELECT o.id, o.nome, COALESCE(b.valor_orcado, 0) AS valor_orcado,
           COALESCE(f.total_obra, 0) AS total_obra
    FROM obras o
    LEFT JOIN orcamentos_obras b ON b.obra_id = o.id
    LEFT JOIN (SELECT obra_id, SUM(quantidade * valor_unitario) AS total_obra FROM lancamentos_financeiros GROUP BY obra_id) f ON f.obra_id = o.id
    ORDER BY total_obra DESC, o.nome ASC LIMIT 8
") : $conn->query("
    SELECT
        o.id,
        o.nome,
        COALESCE(SUM(c.valor * c.quantidade), 0) AS total_obra
    FROM obras o
    LEFT JOIN compras c ON c.obra_id = o.id
    GROUP BY o.id, o.nome
    ORDER BY total_obra DESC, o.nome ASC
    LIMIT 8
");

/* =========================
   PRÓXIMAS ATIVIDADES
   ========================= */
$proximasAtividades = $conn->query("
    SELECT
        a.id,
        a.titulo,
        a.data_limite,
        a.status,
        o.nome AS nome_obra
    FROM atividades a
    LEFT JOIN obras o ON o.id = a.obra_id
    WHERE a.status <> 'concluida'
    ORDER BY
        CASE WHEN a.data_limite < '$hoje' THEN 0 ELSE 1 END,
        a.data_limite ASC
    LIMIT 8
");

/* =========================
   CHAMADOS / OCORRÊNCIAS
   ========================= */
$chamadosRecentes = $conn->query("
    SELECT
        c.id,
        c.titulo,
        c.prioridade,
        c.status,
        COALESCE(u.nome, 'Desconhecido') AS solicitante,
        c.data_abertura
    FROM chamados c
    LEFT JOIN usuarios u ON u.id = c.usuario_id
    WHERE c.status <> 'fechado'
    ORDER BY
        CASE c.prioridade
            WHEN 'vermelho' THEN 1
            WHEN 'amarelo' THEN 2
            ELSE 3
        END,
        c.data_abertura DESC
    LIMIT 6
");

/* =========================
   DADOS PARA GRÁFICOS
   ========================= */
$graficoStatus = [
    $atividadesConcluidas,
    $atividadesAndamento,
    max(0, $totalAtividades - $atividadesConcluidas - $atividadesAndamento)
];

$labelsObras = [];
$valoresObras = [];

if ($obrasFinanceiro) {
    while ($obra = $obrasFinanceiro->fetch_assoc()) {
        $labelsObras[] = $obra['nome'];
        $valoresObras[] = (float)$obra['total_obra'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Auxiliar Obras</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    :root {
        --bg: #f4f6f9;
        --sidebar: #172033;
        --primary: #2563eb;
        --text: #1e293b;
        --muted: #64748b;
        --border: #e2e8f0;
        --shadow: 0 8px 30px rgba(15, 23, 42, .06);
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: var(--bg);
        color: var(--text);
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: 250px;
        background: var(--sidebar);
        color: #fff;
        padding: 22px 15px;
        z-index: 1000;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #fff;
        text-decoration: none;
        font-size: 20px;
        font-weight: 800;
        padding: 8px 12px 25px;
    }

    .brand i {
        color: #38bdf8;
    }

    .menu-title {
        color: #64748b;
        text-transform: uppercase;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
        padding: 12px 12px 7px;
    }

    .menu-link {
        display: flex;
        align-items: center;
        gap: 11px;
        color: #cbd5e1;
        text-decoration: none;
        padding: 11px 12px;
        border-radius: 9px;
        margin-bottom: 4px;
        font-size: 14px;
        transition: .2s;
    }

    .menu-link:hover,
    .menu-link.active {
        background: rgba(37, 99, 235, .22);
        color: #fff;
    }

    .main {
        margin-left: 250px;
        min-height: 100vh;
    }

    .topbar {
        background: #fff;
        border-bottom: 1px solid var(--border);
        min-height: 70px;
        padding: 12px 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 900;
    }

    .content {
        padding: 28px;
    }

    .page-title {
        font-size: 25px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .page-subtitle {
        color: var(--muted);
        font-size: 14px;
    }

    .card-dashboard {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: var(--shadow);
    }

    .metric-card {
        padding: 20px;
        height: 100%;
        position: relative;
        overflow: hidden;
    }

    .metric-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        background: #eff6ff;
        color: var(--primary);
    }

    .metric-value {
        font-size: 28px;
        font-weight: 800;
        margin-top: 15px;
        line-height: 1;
    }

    .metric-label {
        color: var(--muted);
        font-size: 13px;
        margin-top: 8px;
    }

    .metric-danger .metric-icon {
        background: #fee2e2;
        color: #dc2626;
    }

    .metric-success .metric-icon {
        background: #dcfce7;
        color: #16a34a;
    }

    .metric-warning .metric-icon {
        background: #fef3c7;
        color: #d97706;
    }

    .metric-money .metric-icon {
        background: #ede9fe;
        color: #7c3aed;
    }

    .section-card {
        padding: 20px;
    }

    .section-title {
        font-weight: 750;
        font-size: 16px;
    }

    .section-subtitle {
        font-size: 12px;
        color: var(--muted);
    }

    .progress {
        height: 9px;
        border-radius: 20px;
        background: #e2e8f0;
    }

    .table-modern {
        margin-bottom: 0;
    }

    .table-modern th {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .table-modern td {
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .badge-soft {
        border-radius: 30px;
        padding: 6px 9px;
        font-size: 10px;
        font-weight: 700;
    }

    .soft-success {
        background: #dcfce7;
        color: #166534;
    }

    .soft-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .soft-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .soft-primary {
        background: #dbeafe;
        color: #1e40af;
    }

    .soft-secondary {
        background: #e2e8f0;
        color: #475569;
    }

    .chart-box {
        height: 300px;
    }

    .quick-action {
        text-decoration: none;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 10px;
        margin-bottom: 8px;
        transition: .2s;
    }

    .quick-action:hover {
        background: #f8fafc;
        transform: translateX(2px);
    }

    .quick-action i {
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        background: #eff6ff;
        color: var(--primary);
    }

    @media (max-width: 991px) {
        .sidebar {
            position: static;
            width: 100%;
        }

        .main {
            margin-left: 0;
        }

        .menu-title,
        .menu-link {
            display: inline-flex;
        }

        .content {
            padding: 18px;
        }
    }
    </style>
</head>

<body>

    <aside class="sidebar">
        <a href="dashboard.php" class="brand">
            <i class="bi bi-building-gear"></i>
            <span>Auxiliar Obras</span>
        </a>

        <div class="menu-title">Principal</div>
        <a href="dashboard.php" class="menu-link active">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="menu-title">Gestão</div>
        <a href="gerenciar_obra.php" class="menu-link">
            <i class="bi bi-building"></i> Obras
        </a>
        <a href="obras_calendario.php" class="menu-link">
            <i class="bi bi-kanban"></i> Atividades
        </a>
        <a href="calendario.php" class="menu-link">
            <i class="bi bi-calendar3"></i> Calendário
        </a>
        <a href="financeiro.php" class="menu-link">
            <i class="bi bi-cash-stack"></i> Financeiro
        </a>

        <div class="menu-title">Sistema</div>
        <?php if ($user_role === 'admin'): ?>
        <a href="cadastro_externo.php" class="menu-link">
            <i class="bi bi-people"></i> Gerenciar Usuários
        </a>
        <?php endif; ?>
        <a href="perfil.php" class="menu-link">
            <i class="bi bi-person-circle"></i> Meu Perfil
        </a>
        <a href="logout.php" class="menu-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>
    </aside>

    <main class="main">

        <header class="topbar">
            <div>
                <strong>Centro de Controle</strong>
                <div class="small text-muted"><?= date('d/m/Y') ?></div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold small"><?= htmlspecialchars($user_nome) ?></div>
                    <span class="badge-soft soft-primary">
                        <?= strtoupper(htmlspecialchars($user_role)) ?>
                    </span>
                </div>
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                    style="width:42px;height:42px;">
                    <i class="bi bi-person-fill text-secondary"></i>
                </div>
            </div>
        </header>

        <section class="content">
            <?php if (!empty($_SESSION['erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-octagon me-2"></i> <?= htmlspecialchars($_SESSION['erro']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['erro']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['sucesso']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['sucesso']); ?>
            <?php endif; ?>

            <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
                <div>
                    <div class="page-title">Dashboard Geral</div>
                    <div class="page-subtitle">
                        Visão consolidada de obras, cronograma, ocorrências e financeiro.
                    </div>
                </div>

                <div class="mt-3 mt-md-0">
                    <a href="gerenciar_obra.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-building-add me-1"></i> Gerenciar Obras
                    </a>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row g-3 mb-4">

                <div class="col-xl-3 col-md-6">
                    <div class="card-dashboard metric-card">
                        <div class="metric-icon"><i class="bi bi-buildings"></i></div>
                        <div class="metric-value"><?= $totalObras ?></div>
                        <div class="metric-label">Obras cadastradas</div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card-dashboard metric-card">
                        <div class="metric-icon"><i class="bi bi-list-check"></i></div>
                        <div class="metric-value"><?= $totalAtividades ?></div>
                        <div class="metric-label">Atividades cadastradas</div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card-dashboard metric-card metric-danger">
                        <div class="metric-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="metric-value"><?= $atividadesAtrasadas ?></div>
                        <div class="metric-label">Atividades em atraso</div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card-dashboard metric-card metric-money">
                        <div class="metric-icon"><i class="bi bi-currency-dollar"></i></div>
                        <div class="metric-value" style="font-size:22px;">
                            R$ <?= number_format($totalFinanceiro, 2, ',', '.') ?>
                        </div>
                        <div class="metric-label">Custo total registrado</div>
                    </div>
                </div>

            </div>

            <?php if ($financeAvailable): ?>
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card-dashboard section-card h-100">
                        <div class="section-subtitle">Orçamento consolidado</div>
                        <div class="fw-bold fs-4 mt-1">R$ <?= number_format($totalOrcamento, 2, ',', '.') ?></div>
                        <small class="text-muted">Limite financeiro aprovado</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card-dashboard section-card h-100">
                        <div class="section-subtitle">Saldo de orçamento</div>
                        <div class="fw-bold fs-4 mt-1 <?= $saldoOrcamento < 0 ? 'text-danger' : 'text-success' ?>">R$
                            <?= number_format($saldoOrcamento, 2, ',', '.') ?></div>
                        <div class="progress mt-3">
                            <div class="progress-bar <?= $saldoOrcamento < 0 ? 'bg-danger' : 'bg-success' ?>"
                                style="width: <?= $percentualOrcamento ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card-dashboard section-card h-100">
                        <div class="section-subtitle">Consumo de materiais</div>
                        <div class="fw-bold fs-4 mt-1">R$ <?= number_format($totalMateriaisFinanceiro, 2, ',', '.') ?>
                        </div>
                        <small class="text-muted">Insumos registrados</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card-dashboard section-card h-100">
                        <div class="section-subtitle">Custos operacionais</div>
                        <div class="fw-bold fs-4 mt-1">R$ <?= number_format($totalOperacionalFinanceiro, 2, ',', '.') ?>
                        </div>
                        <a href="financeiro.php" class="small text-decoration-none">Abrir gestão financeira <i
                                class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- SEGUNDA LINHA -->
            <div class="row g-3 mb-4">

                <div class="col-lg-8">
                    <div class="card-dashboard section-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="section-title">Execução das atividades</div>
                                <div class="section-subtitle">Distribuição atual do cronograma</div>
                            </div>
                            <span class="badge-soft soft-primary"><?= $percentualConclusao ?>% concluído</span>
                        </div>

                        <div class="chart-box">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card-dashboard section-card h-100">
                        <div class="section-title mb-1">Resumo operacional</div>
                        <div class="section-subtitle mb-4">Indicadores do cronograma</div>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="small">Concluídas</span>
                            <strong class="small"><?= $atividadesConcluidas ?></strong>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-success"
                                style="width: <?= $totalAtividades ? ($atividadesConcluidas/$totalAtividades)*100 : 0 ?>%">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="small">Em andamento</span>
                            <strong class="small"><?= $atividadesAndamento ?></strong>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-warning"
                                style="width: <?= $totalAtividades ? ($atividadesAndamento/$totalAtividades)*100 : 0 ?>%">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="small">Atrasadas</span>
                            <strong class="small text-danger"><?= $atividadesAtrasadas ?></strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-danger"
                                style="width: <?= $totalAtividades ? ($atividadesAtrasadas/$totalAtividades)*100 : 0 ?>%">
                            </div>
                        </div>

                        <hr>

                        <div class="row text-center">
                            <div class="col-6">
                                <div class="fw-bold fs-4"><?= $totalDocumentos ?></div>
                                <small class="text-muted">Documentos</small>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold fs-4 text-danger"><?= $chamadosUrgentes ?></div>
                                <small class="text-muted">Urgentes</small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- GRÁFICO FINANCEIRO -->
            <div class="row g-3 mb-4">

                <div class="col-lg-8">
                    <div class="card-dashboard section-card">
                        <div class="section-title">Custos por obra</div>
                        <div class="section-subtitle mb-3">Obras com maior gasto financeiro registrado</div>

                        <div class="chart-box">
                            <canvas id="financeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card-dashboard section-card h-100">
                                <div class="section-title mb-3">Ações rápidas</div>

                                <a href="gerenciar_obra.php" class="quick-action">
                                    <i class="bi bi-building-add"></i>
                                    <span><strong>Gerenciar obra</strong><small class="d-block text-muted">Abrir centro
                                            de
                                            controle</small></span>
                                </a>

                                <a href="obras_calendario.php" class="quick-action">
                                    <i class="bi bi-kanban"></i>
                                    <span><strong>Nova atividade</strong><small class="d-block text-muted">Cadastrar e
                                            acompanhar prazo</small></span>
                                </a>

                                <a href="calendario.php" class="quick-action">
                                    <i class="bi bi-calendar-plus"></i>
                                    <span><strong>Calendário</strong><small class="d-block text-muted">Visualizar
                                            cronograma</small></span>
                                </a>

                                <a href="financeiro.php" class="quick-action">
                                    <i class="bi bi-bar-chart-line"></i>
                                    <span><strong>Financeiro</strong><small class="d-block text-muted">Consultar custos
                                            por
                                            obra</small></span>
                                </a>
                            </div>
                        </div>
                        <div class="col-12 d-none">
                            <div class="card-dashboard section-card h-100">
                                <div class="section-title mb-3">Upload de documento</div>
                                <div class="section-subtitle mb-3">Envie um PDF diretamente para a obra selecionada.
                                </div>
                                <form action="upload_doc.php" method="POST" enctype="multipart/form-data"
                                    class="row g-2 align-items-end">
                                    <input type="hidden" name="_token"
                                        value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="col-md-6 col-xl-3">
                                        <label class="form-label small fw-semibold">Obra</label>
                                        <select name="obra_id" class="form-select form-select-sm" required>
                                            <?php if ($obrasList && $obrasList->num_rows > 0): ?>
                                            <?php while ($obra = $obrasList->fetch_assoc()): ?>
                                            <option value="<?= (int)$obra['id'] ?>">
                                                <?= htmlspecialchars($obra['nome']) ?></option>
                                            <?php endwhile; ?>
                                            <?php else: ?>
                                            <option value="">Nenhuma obra disponível</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <label class="form-label small fw-semibold">Nome do documento</label>
                                        <input type="text" name="nome_arquivo" class="form-control form-control-sm"
                                            required placeholder="Ex: Planta hidráulica">
                                    </div>
                                    <div class="col-md-4 col-xl-2">
                                        <label class="form-label small fw-semibold">Tipo</label>
                                        <select name="tipo_documento" class="form-select form-select-sm" required>
                                            <option value="Planta">Planta</option>
                                            <option value="Contrato">Contrato</option>
                                            <option value="Relatório">Relatório</option>
                                            <option value="Outros">Outros</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8 col-xl-3">
                                        <label class="form-label small fw-semibold">Arquivo PDF</label>
                                        <input type="file" name="arquivo" class="form-control form-control-sm"
                                            accept=".pdf" required>
                                    </div>
                                    <div class="col-12 col-xl-1 d-grid">
                                        <button type="submit" class="btn btn-primary btn-sm text-nowrap"><i
                                                class="bi bi-upload me-1"></i>Enviar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($obrasList): $obrasList->data_seek(0); endif; ?>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card-dashboard section-card h-100">
                        <div class="section-title mb-3">Upload de documento</div>
                        <div class="section-subtitle mb-3">Envie um PDF diretamente para a obra selecionada.</div>
                        <form action="upload_doc.php" method="POST" enctype="multipart/form-data"
                            class="row g-2 align-items-end">
                            <input type="hidden" name="_token"
                                value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="col-md-6 col-xl-3">
                                <label class="form-label small fw-semibold">Obra</label>
                                <select name="obra_id" class="form-select form-select-sm" required>
                                    <?php if ($obrasList && $obrasList->num_rows > 0): ?>
                                    <?php while ($obra = $obrasList->fetch_assoc()): ?>
                                    <option value="<?= (int)$obra['id'] ?>"><?= htmlspecialchars($obra['nome']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                    <?php else: ?><option value="">Nenhuma obra disponível</option><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6 col-xl-3"><label class="form-label small fw-semibold">Nome do
                                    documento</label><input type="text" name="nome_arquivo"
                                    class="form-control form-control-sm" required placeholder="Ex: Planta hidráulica">
                            </div>
                            <div class="col-md-4 col-xl-2"><label
                                    class="form-label small fw-semibold">Tipo</label><select name="tipo_documento"
                                    class="form-select form-select-sm" required>
                                    <option value="Planta">Planta</option>
                                    <option value="Contrato">Contrato</option>
                                    <option value="Relatório">Relatório</option>
                                    <option value="Outros">Outros</option>
                                </select></div>
                            <div class="col-md-8 col-xl-3"><label class="form-label small fw-semibold">Arquivo
                                    PDF</label><input type="file" name="arquivo" class="form-control form-control-sm"
                                    accept=".pdf" required></div>
                            <div class="col-12 col-xl-1 d-grid"><button type="submit"
                                    class="btn btn-primary btn-sm text-nowrap"><i
                                        class="bi bi-upload me-1"></i>Enviar</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TABELAS -->
            <div class="row g-3">

                <div class="col-lg-7">
                    <div class="card-dashboard section-card p-0 overflow-hidden">
                        <div class="p-3 border-bottom">
                            <div class="section-title">Próximas atividades</div>
                            <div class="section-subtitle">Tarefas pendentes, em andamento e atrasadas</div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Obra</th>
                                        <th>Atividade</th>
                                        <th>Prazo</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($proximasAtividades && $proximasAtividades->num_rows > 0): ?>
                                    <?php while ($act = $proximasAtividades->fetch_assoc()):
                                    $atrasada = ($act['data_limite'] < $hoje);
                                    if ($atrasada) {
                                        $classe = 'soft-danger';
                                        $texto = 'Em atraso';
                                    } elseif ($act['status'] === 'em_andamento') {
                                        $classe = 'soft-warning';
                                        $texto = 'Em andamento';
                                    } else {
                                        $classe = 'soft-secondary';
                                        $texto = 'Pendente';
                                    }
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($act['nome_obra'] ?? 'Geral') ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($act['titulo']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($act['data_limite'])) ?></td>
                                        <td><span class="badge-soft <?= $classe ?>"><?= $texto ?></span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            Nenhuma atividade pendente.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card-dashboard section-card p-0 overflow-hidden">
                        <div class="p-3 border-bottom">
                            <div class="section-title">Ocorrências recentes</div>
                            <div class="section-subtitle">Chamados priorizados pelo farol</div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Prioridade</th>
                                        <th>Ocorrência</th>
                                        <th>Status</th>
                                        <th class="text-end">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($chamadosRecentes && $chamadosRecentes->num_rows > 0): ?>
                                    <?php while ($ch = $chamadosRecentes->fetch_assoc()):
                                    if ($ch['prioridade'] === 'vermelho') {
                                        $classe = 'soft-danger';
                                        $icone = '🔴';
                                    } elseif ($ch['prioridade'] === 'amarelo') {
                                        $classe = 'soft-warning';
                                        $icone = '🟡';
                                    } else {
                                        $classe = 'soft-success';
                                        $icone = '🟢';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge-soft <?= $classe ?>">
                                                <?= $icone ?> <?= ucfirst($ch['prioridade']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold"><?= htmlspecialchars($ch['titulo']) ?></td>
                                        <td>
                                            <span class="small">
                                                <?= strtoupper(str_replace('_', ' ', $ch['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($ch['status'] !== 'fechado' && $can_edit_status): ?>
                                            <form method="POST" action="fechar_chamado.php" class="d-inline"
                                                onsubmit="return confirm('Dar baixa neste chamado?');">
                                                <input type="hidden" name="_token"
                                                    value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="chamado_id" value="<?= (int)$ch['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Dar
                                                    baixa</button>
                                            </form>
                                            <?php else: ?>
                                            <span class="small text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            Nenhuma ocorrência registrada.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </section>
    </main>

    <script>
    const statusData = <?= json_encode($graficoStatus, JSON_UNESCAPED_UNICODE) ?>;

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Concluídas', 'Em andamento', 'Pendentes'],
            datasets: [{
                data: statusData,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    const obraLabels = <?= json_encode($labelsObras, JSON_UNESCAPED_UNICODE) ?>;
    const obraValues = <?= json_encode($valoresObras) ?>;

    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: obraLabels,
            datasets: [{
                label: 'Gasto realizado da obra (R$)',
                data: obraValues,
                borderRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + Number(value).toLocaleString('pt-BR');
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    </script>

</body>

</html>