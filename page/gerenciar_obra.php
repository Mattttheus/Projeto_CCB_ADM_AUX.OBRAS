<?php
require_once __DIR__ . '/../app/bootstrap.php';

\App\Core\Auth::requireUser();

$erro = '';
$sucesso = '';
$user_id = (int) $_SESSION['usuario_id'];
$user_role = strtolower((string) ($_SESSION['role'] ?? $_SESSION['tipo'] ?? 'user'));
$has_full_project_access = \App\Core\Auth::hasFullProjectAccess();

if (!empty($_SESSION['erro'])) {
    $erro = $_SESSION['erro'];
    unset($_SESSION['erro']);
}
if (!empty($_SESSION['sucesso'])) {
    $sucesso = $_SESSION['sucesso'];
    unset($_SESSION['sucesso']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'excluir_documento') {
    try {
        \App\Core\Csrf::validate($_POST['_token'] ?? null);
    } catch (Throwable $exception) {
        $_SESSION['erro'] = $exception->getMessage();
        header('Location: gerenciar_obra.php');
        exit;
    }
    $del_doc_id = (int) ($_POST['del_doc'] ?? 0);
    $redirectObraId = (int) ($_POST['obra_id'] ?? 0);
    if ($del_doc_id > 0) {
        $stmtDel = $conn->prepare("SELECT obra_id, caminho_arquivo FROM documentos_obras WHERE id = ? LIMIT 1");
        if ($stmtDel) {
            $stmtDel->bind_param("i", $del_doc_id);
            $stmtDel->execute();
            $resDel = $stmtDel->get_result();
            $docToDelete = $resDel ? $resDel->fetch_assoc() : null;
            $stmtDel->close();

            if (!$docToDelete || !\App\Core\Auth::canAccessProject($conn, (int) $docToDelete['obra_id'])) {
                $_SESSION['erro'] = 'Você não tem acesso a esta obra.';
                header('Location: gerenciar_obra.php');
                exit;
            }

            if (!empty($docToDelete['caminho_arquivo'])) {
                $arquivoPath = realpath(__DIR__ . '/../' . $docToDelete['caminho_arquivo']);
                $baseDir = realpath(__DIR__ . '/../uploads');
                if ($arquivoPath && $baseDir && strpos($arquivoPath, $baseDir) === 0 && file_exists($arquivoPath)) {
                    @unlink($arquivoPath);
                }
            }

            $stmtDelete = $conn->prepare("DELETE FROM documentos_obras WHERE id = ?");
            if ($stmtDelete) {
                $stmtDelete->bind_param("i", $del_doc_id);
                if ($stmtDelete->execute()) {
                    $_SESSION['sucesso'] = 'Documento excluído com sucesso.';
                } else {
                    $_SESSION['erro'] = 'Falha ao excluir documento: ' . $stmtDelete->error;
                }
                $stmtDelete->close();
            } else {
                $_SESSION['erro'] = 'Erro no banco ao excluir documento: ' . $conn->error;
            }
        }
    }
    header('Location: gerenciar_obra.php?obra_id=' . $redirectObraId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'excluir_documento') {
    try {
        \App\Core\Csrf::validate($_POST['_token'] ?? null);
    } catch (Throwable $exception) {
        $erro = $exception->getMessage();
    }
}

// ==========================================
// CRIAR NOVA OBRA (processamento POST)
// ==========================================
if ($erro === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'criar_obra') {
    $nome_obra = trim($_POST['nome_obra'] ?? '');

    if (!$has_full_project_access) {
        $erro = 'Apenas administrador ou suporte podem criar obras.';
    } elseif ($nome_obra === '') {
        $erro = "Nome da obra é obrigatório.";
    } else {
        $stmtIns = $conn->prepare("INSERT INTO obras (nome) VALUES (?)");
        if ($stmtIns) {
            $stmtIns->bind_param("s", $nome_obra);
            if ($stmtIns->execute()) {
                $new_id = $stmtIns->insert_id;
                $stmtIns->close();
                header("Location: gerenciar_obra.php?obra_id=" . $new_id);
                exit;
            } else {
                $erro = "Erro ao criar obra: " . $stmtIns->error;
                $stmtIns->close();
            }
        } else {
            $erro = "Erro no banco: " . $conn->error;
        }
    }
}

// ==========================================
// ABRIR NOVO CHAMADO (processamento POST)
// ==========================================
if ($erro === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'abrir_chamado') {
    $obra_id_chamado = isset($_POST['obra_id']) ? (int)$_POST['obra_id'] : (isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : 0);
    $titulo = trim($_POST['titulo_chamado'] ?? '');
    $prioridade = trim($_POST['prioridade_chamado'] ?? 'verde');
    $descricao = trim($_POST['descricao_chamado'] ?? '');

    if ($titulo === '' || $descricao === '' || $obra_id_chamado === 0) {
        $erro = "Todos os campos do chamado são obrigatórios.";
    } elseif (!\App\Core\Auth::canAccessProject($conn, $obra_id_chamado)) {
        $erro = 'Você não tem acesso a esta obra.';
    } else {
        $stmtChamado = $conn->prepare("INSERT INTO chamados (obra_id, usuario_id, titulo, prioridade, descricao, status, data_abertura) VALUES (?, ?, ?, ?, ?, 'aberto', NOW())");
        if ($stmtChamado) {
            $stmtChamado->bind_param("iisss", $obra_id_chamado, $user_id, $titulo, $prioridade, $descricao);
            if ($stmtChamado->execute()) {
                $stmtChamado->close();
                header("Location: gerenciar_obra.php?obra_id=" . $obra_id_chamado);
                exit;
            } else {
                $erro = "Erro ao abrir chamado: " . $stmtChamado->error;
                $stmtChamado->close();
            }
        } else {
            $erro = "Erro ao preparar chamado: " . $conn->error;
        }
    }
}

// ==========================================
// FECHAR / DAR BAIXA EM CHAMADO (POST)
// ==========================================
if ($erro === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fechar_chamado') {
    $chamado_id = isset($_POST['chamado_id']) ? (int)$_POST['chamado_id'] : 0;
    if ($chamado_id > 0) {
        $stmtCall = $conn->prepare('SELECT obra_id FROM chamados WHERE id = ? LIMIT 1');
        $stmtCall->bind_param('i', $chamado_id);
        $stmtCall->execute();
        $call = $stmtCall->get_result()->fetch_assoc();
        $stmtCall->close();

        if (!$call || !\App\Core\Auth::canAccessProject($conn, (int) $call['obra_id'])) {
            $erro = 'Você não tem acesso a este chamado.';
        } else {
            $stmtClose = $conn->prepare("UPDATE chamados SET status = 'fechado', data_fechamento = NOW(), fechado_por = ? WHERE id = ?");
            if ($stmtClose) {
            $stmtClose->bind_param('ii', $user_id, $chamado_id);
            if ($stmtClose->execute()) {
                $stmtClose->close();
                try {
                    (new \App\Application\Notification\AdminNotificationService($conn))->notifyClosedCall($chamado_id);
                } catch (Throwable $exception) {
                    error_log('Falha ao enfileirar aviso de chamado concluído: ' . $exception->getMessage());
                }
                header('Location: gerenciar_obra.php?obra_id=' . (int) ($call['obra_id'] ?? 0));
                exit;
            } else {
                $erro = 'Falha ao fechar chamado: ' . $stmtClose->error;
                $stmtClose->close();
            }
        } else {
            $erro = 'Erro no banco ao preparar fechamento: ' . $conn->error;
        }
        }
    }
}

// ==========================================
// PERMISSÕES & DADOS DA SESSÃO
// ==========================================
$user_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

$can_delete_docs   = ($user_role === 'admin');
$can_delete_tasks  = ($user_role === 'admin');
$can_add_docs      = in_array($user_role, ['admin', 'suporte', 'engenheiro']);
$can_add_tasks     = in_array($user_role, ['admin', 'suporte', 'engenheiro']);
$can_edit_status   = in_array($user_role, ['admin', 'suporte', 'engenheiro', 'mestre_obras', 'user']);

// ==========================================
// SELEÇÃO DA OBRA
// ==========================================
$obra_id = isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : 0;
if ($has_full_project_access) {
    $todasObras = $conn->query("SELECT id, nome FROM obras ORDER BY nome ASC");
} else {
    $stmtObras = $conn->prepare(
        "SELECT DISTINCT o.id, o.nome FROM obras o INNER JOIN obra_responsaveis r ON r.obra_id = o.id WHERE r.usuario_id = ? ORDER BY o.nome ASC"
    );
    $stmtObras->bind_param('i', $user_id);
    $stmtObras->execute();
    $todasObras = $stmtObras->get_result();
}

if ($obra_id === 0 && $todasObras && $todasObras->num_rows > 0) {
    $primeiraObra = $todasObras->fetch_assoc();
    $obra_id = $primeiraObra['id'];
    $todasObras->data_seek(0);
}

// Obter dados da obra selecionada
$obraAtual = null;
$stmtObra = $has_full_project_access
    ? $conn->prepare("SELECT * FROM obras WHERE id = ?")
    : $conn->prepare("SELECT o.* FROM obras o INNER JOIN obra_responsaveis r ON r.obra_id = o.id WHERE o.id = ? AND r.usuario_id = ? LIMIT 1");
if ($stmtObra) {
    if ($has_full_project_access) {
        $stmtObra->bind_param("i", $obra_id);
    } else {
        $stmtObra->bind_param("ii", $obra_id, $user_id);
    }
    $stmtObra->execute();
    $resObra = $stmtObra->get_result();
    $obraAtual = $resObra ? $resObra->fetch_assoc() : null;
    $stmtObra->close();
} else {
    error_log('prepare SELECT obras failed: ' . $conn->error);
}

if ($obra_id > 0 && !$obraAtual) {
    http_response_code(403);
    exit('Acesso negado a esta obra.');
}

// Consultas seguras com Prepared Statements para evitar SQL Injection
$stmtDoc = $conn->prepare("SELECT * FROM documentos_obras WHERE obra_id = ? ORDER BY data_upload DESC");
$stmtDoc->bind_param("i", $obra_id);
$stmtDoc->execute();
$resDocumentos = $stmtDoc->get_result();

$hoje = date('Y-m-d');
$stmtAct = $conn->prepare("SELECT * FROM atividades WHERE obra_id = ? ORDER BY data_limite ASC");
$stmtAct->bind_param("i", $obra_id);
$stmtAct->execute();
$resAtividades = $stmtAct->get_result();

$fprioridade = isset($_GET['fprioridade']) ? $_GET['fprioridade'] : '';

// Totais por prioridade (todos os chamados abertos)
$countsAll = ['vermelho' => 0, 'amarelo' => 0, 'verde' => 0];
$countsSql = "SELECT c.prioridade, COUNT(*) as cnt FROM chamados c";
if (!$has_full_project_access) {
    $countsSql .= " INNER JOIN obra_responsaveis r ON r.obra_id = c.obra_id WHERE r.usuario_id = ? AND c.status != 'fechado' GROUP BY c.prioridade";
    $stmtCounts = $conn->prepare($countsSql);
    $stmtCounts->bind_param('i', $user_id);
    $stmtCounts->execute();
    $resCounts = $stmtCounts->get_result();
} else {
    $resCounts = $conn->query($countsSql . " WHERE c.status != 'fechado' GROUP BY c.prioridade");
}
if ($resCounts) {
    while ($r = $resCounts->fetch_assoc()) {
        $p = $r['prioridade'];
        if (isset($countsAll[$p])) {
            $countsAll[$p] = (int)$r['cnt'];
        }
    }
}

// Totais por prioridade para a obra atual
$countsObra = ['vermelho' => 0, 'amarelo' => 0, 'verde' => 0];
$stmtCountsObra = $conn->prepare("SELECT prioridade, COUNT(*) as cnt FROM chamados WHERE obra_id = ? AND status != 'fechado' GROUP BY prioridade");
if ($stmtCountsObra) {
    $stmtCountsObra->bind_param("i", $obra_id);
    $stmtCountsObra->execute();
    $resCountsObra = $stmtCountsObra->get_result();
    while ($r = $resCountsObra->fetch_assoc()) {
        $p = $r['prioridade'];
        if (isset($countsObra[$p])) {
            $countsObra[$p] = (int)$r['cnt'];
        }
    }
    $stmtCountsObra->close();
}

// Filtro WHERE dinâmico e seguro
$allowed = ['verde', 'amarelo', 'vermelho'];
$whereFilter = "c.status != 'fechado'";
if ($fprioridade && in_array($fprioridade, $allowed, true)) {
    $fprioEsc = $conn->real_escape_string($fprioridade);
    $whereFilter .= " AND c.prioridade = '" . $fprioEsc . "'";
}

$callsSql = "SELECT c.*, o.nome as obra_nome FROM chamados c LEFT JOIN obras o ON c.obra_id = o.id";
if (!$has_full_project_access) {
    $callsSql .= " INNER JOIN obra_responsaveis r ON r.obra_id = c.obra_id WHERE r.usuario_id = ? AND " . $whereFilter;
    $stmtCalls = $conn->prepare($callsSql . " ORDER BY o.nome ASC, CASE WHEN c.prioridade='vermelho' THEN 1 WHEN c.prioridade='amarelo' THEN 2 ELSE 3 END, c.data_abertura DESC");
    $stmtCalls->bind_param('i', $user_id);
    $stmtCalls->execute();
    $resChamadosTodas = $stmtCalls->get_result();
} else {
    $resChamadosTodas = $conn->query($callsSql . " WHERE " . $whereFilter . " ORDER BY o.nome ASC, CASE WHEN c.prioridade='vermelho' THEN 1 WHEN c.prioridade='amarelo' THEN 2 ELSE 3 END, c.data_abertura DESC");
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($obraAtual['nome'] ?? 'Gestão de Obras') ?> - Auxiliar Obras</title>

    <!-- Bootstrap 5 & Google Fonts (Inter) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --bg-body: #f4f6f9;
        --sidebar-bg: #1e293b;
        --sidebar-color: #94a3b8;
        --sidebar-active: #38bdf8;
        --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        --primary-accent: #2563eb;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--bg-body);
        color: #334155;
    }

    .top-navbar {
        background-color: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.75rem 1.5rem;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .top-navbar .brand-logo {
        font-weight: 700;
        font-size: 1.2rem;
        color: var(--primary-accent);
        text-decoration: none;
    }

    .card-custom {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: var(--card-shadow);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card-header-custom {
        background: transparent;
        border-bottom: 1px solid #f1f5f9;
        padding: 1.2rem 1.5rem;
        font-weight: 600;
    }

    .badge-soft-primary {
        background-color: #dbeafe;
        color: #1e40af;
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

    .table-modern {
        margin-bottom: 0;
    }

    .table-modern th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
    }

    .table-modern td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 0.9rem;
    }

    .dropdown-item {
        font-size: 0.85rem;
    }
    </style>
</head>

<body>

    <!-- NAVBAR SUPERIOR -->
    <nav class="top-navbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="brand-logo d-flex align-items-center gap-2">
                <i class="bi bi-building-gear text-primary fs-4"></i>
                <span>Auxiliar<span class="text-dark">Obras</span></span>
            </a>

            <span class="vr d-none d-md-inline-block opacity-25"></span>

            <!-- SELETOR DINÂMICO DE OBRA -->
            <form method="GET" class="d-flex align-items-center m-0">
                <div class="input-group input-group-sm">
                    <label class="input-group-text bg-light border-0 fw-medium" for="selectObra"><i
                            class="bi bi-geo-alt me-1"></i> Obra:</label>
                    <select name="obra_id" id="selectObra" class="form-select border-0 bg-light fw-bold text-dark"
                        onchange="this.form.submit()">
                        <?php if ($todasObras && $todasObras->num_rows > 0): ?>
                        <?php while ($ob = $todasObras->fetch_assoc()): ?>
                        <option value="<?=$ob['id']?>" <?= $ob['id'] == $obra_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ob['nome']) ?>
                        </option>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>

            <?php if ($has_full_project_access): ?>
            <div class="d-flex align-items-center">
                <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="collapse"
                    data-bs-target="#collapseCriarObra" type="button">+ Nova Obra</button>
            </div>
            <div class="collapse mt-2" id="collapseCriarObra">
                <form method="POST" class="d-flex align-items-center gap-2 mt-2">
                    <?= \App\Core\Csrf::input() ?>
                    <input type="hidden" name="action" value="criar_obra">
                    <input type="text" name="nome_obra" class="form-control form-control-sm" placeholder="Nome da obra"
                        required>
                    <button type="submit" class="btn btn-primary btn-sm">Criar</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- ÁREA DO USUÁRIO -->
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="fw-semibold text-dark fs-7"><?= htmlspecialchars($user_nome) ?></div>
                <span
                    class="badge badge-soft-primary rounded-pill"><?= strtoupper(htmlspecialchars($user_role)) ?></span>
            </div>
            <div class="dropdown">
                <button class="btn btn-light rounded-circle p-2 border d-flex align-items-center justify-content-center"
                    style="width:40px; height:40px;" data-bs-toggle="dropdown">
                    <i class="bi bi-person-fill text-secondary fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard
                            Geral</a></li>
                    <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person me-2"></i> Meu Perfil</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i> Sair do Sistema</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- CONTEÚDO PRINCIPAL -->
    <div class="container-fluid px-4 py-4">

        <!-- ALERTAS DE ERRO OU SUCESSO -->
        <?php if(!empty($erro)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-octagon me-2"></i> <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        <?php if(!empty($sucesso)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($sucesso) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- CABEÇALHO DA OBRA -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-7">
                <h3 class="fw-bold mb-1 text-dark">
                    <?= htmlspecialchars($obraAtual['nome'] ?? 'Gerenciamento de Obra') ?></h3>
                <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i> Centro de controle de documentos,
                    atividades e ocorrências da obra.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 me-2"><i
                        class="bi bi-arrow-left me-1"></i> Voltar ao Painel</a>
                <?php if (!empty($obraAtual['id'])): ?>
                <a href="financeiro.php?obra_id=<?= (int) $obraAtual['id'] ?>"
                    class="btn btn-outline-success btn-sm rounded-pill px-3 me-2"><i
                        class="bi bi-cash-stack me-1"></i> Financeiro</a>
                <?php endif; ?>
                <?php if ($can_add_docs): ?>
                <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="collapse"
                    data-bs-target="#formUploadDoc"><i class="bi bi-cloud-upload me-1"></i> Novo Anexo</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- SEÇÃO EXPANSÍVEL: FORMULÁRIO DE UPLOAD DE DOCUMENTO (EXEMPLO) -->
        <div class="collapse mb-4" id="formUploadDoc">
            <div class="card card-custom p-4">
                <h6 class="fw-bold mb-3">Anexar Documento / Planta</h6>
                <form action="upload_doc.php?obra_id=<?= $obra_id ?>" method="POST" enctype="multipart/form-data"
                    class="row g-3">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="obra_id" value="<?= (int) $obra_id ?>">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Nome do Arquivo</label>
                        <input type="text" name="nome_arquivo" class="form-control form-control-sm" required
                            placeholder="Ex: Planta Hidráulica Pav. 1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Tipo</label>
                        <select name="tipo_documento" class="form-select form-select-sm" required>
                            <option value="Planta">Planta</option>
                            <option value="Contrato">Contrato</option>
                            <option value="Relatório">Relatório</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Arquivo (PDF)</label>
                        <input type="file" name="arquivo" class="form-control form-control-sm" required accept=".pdf">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Fazer Upload</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- CONTEÚDO EM GRID (DOCUMENTOS E CRONOGRAMA) -->
        <div class="row g-4">

            <!-- COLUNA 1: DOCUMENTOS -->
            <div class="col-lg-6" id="atividades">
                <div class="card card-custom h-100">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <span class="text-dark"><i class="bi bi-folder2-open text-primary me-2"></i> Documentos e
                            Plantas</span>
                        <small class="text-muted fw-normal"><?= $resDocumentos ? $resDocumentos->num_rows : 0 ?>
                            arquivos</small>
                    </div>
                    <div class="card-body p-3">
                        <div class="input-group input-group-sm mb-3">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="bi bi-search text-muted"></i></span>
                            <input type="text" id="inputBuscaDoc" class="form-control bg-light border-start-0"
                                placeholder="Filtrar por nome do arquivo...">
                        </div>

                        <div class="table-responsive">
                            <table class="table table-modern" id="tabelaDocumentos">
                                <thead>
                                    <tr>
                                        <th>Nome do Arquivo</th>
                                        <th>Tipo</th>
                                        <th class="text-end">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($resDocumentos && $resDocumentos->num_rows > 0): ?>
                                    <?php while ($doc = $resDocumentos->fetch_assoc()): ?>
                                    <tr class="linha-documento">
                                        <td class="coluna-nome">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf fs-5 text-danger me-2"></i>
                                                <span
                                                    class="fw-medium text-dark"><?= htmlspecialchars($doc['nome_arquivo']) ?></span>
                                            </div>
                                        </td>
                                        <td class="coluna-tipo"><span
                                                class="badge badge-soft-primary"><?= htmlspecialchars($doc['tipo_documento']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <a href="../<?= $doc['caminho_arquivo'] ?>" target="_blank"
                                                class="btn btn-light btn-sm rounded-circle" title="Baixar"><i
                                                    class="bi bi-download"></i></a>
                                            <?php if ($can_delete_docs): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Excluir este documento?')">
                                                <?= \App\Core\Csrf::input() ?>
                                                <input type="hidden" name="action" value="excluir_documento">
                                                <input type="hidden" name="obra_id" value="<?= (int) $obra_id ?>">
                                                <input type="hidden" name="del_doc" value="<?= (int) $doc['id'] ?>">
                                                <button type="submit" class="btn btn-light btn-sm text-danger rounded-circle" title="Excluir"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Nenhum documento anexado.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUNA 2: ATIVIDADES DA OBRA -->
            <div class="col-lg-6">
                <div class="card card-custom h-100">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <span class="text-dark"><i class="bi bi-check2-square text-success me-2"></i> Cronograma de
                            Atividades</span>
                        <?php if ($can_add_tasks): ?>
                        <button class="btn btn-light btn-sm rounded-pill fw-medium" data-bs-toggle="collapse"
                            data-bs-target="#formNovaAtividade">+ Nova Tarefa</button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Tarefa</th>
                                        <th>Prazo</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($resAtividades && $resAtividades->num_rows > 0): ?>
                                    <?php while ($act = $resAtividades->fetch_assoc()): 
                                        $isAtrasado = ($act['data_limite'] < $hoje && $act['status'] !== 'concluida');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($act['titulo']) ?>
                                            </div>
                                            <?php if($act['descricao']): ?><small
                                                class="text-muted d-block"><?= htmlspecialchars($act['descricao']) ?></small><?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="<?= $isAtrasado ? 'text-danger fw-bold' : 'text-muted' ?>">
                                                <i
                                                    class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($act['data_limite'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($can_edit_status): ?>
                                            <div class="dropdown">
                                                <button
                                                    class="btn btn-sm rounded-pill border-0 <?= $isAtrasado ? 'badge-soft-danger' : ($act['status']==='concluida' ? 'badge-soft-success' : 'badge-soft-warning') ?> dropdown-toggle fw-semibold"
                                                    type="button" data-bs-toggle="dropdown">
                                                    <?= $isAtrasado ? 'Em Atraso' : ucfirst(str_replace('_', ' ', $act['status'])) ?>
                                                </button>
                                                <ul class="dropdown-menu shadow-sm border-0">
                                                    <li><a class="dropdown-item"
                                                            href="?obra_id=<?=$obra_id?>&mudar_status=pendente&act_id=<?=$act['id']?>">Pendente</a>
                                                    </li>
                                                    <li><a class="dropdown-item"
                                                            href="?obra_id=<?=$obra_id?>&mudar_status=em_andamento&act_id=<?=$act['id']?>">Em
                                                            Andamento</a></li>
                                                    <li><a class="dropdown-item"
                                                            href="?obra_id=<?=$obra_id?>&mudar_status=concluida&act_id=<?=$act['id']?>">Concluída</a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <?php else: ?>
                                            <span
                                                class="badge <?= $isAtrasado ? 'badge-soft-danger' : ($act['status']==='concluida' ? 'badge-soft-success' : 'badge-soft-warning') ?> fw-semibold">
                                                <?= $isAtrasado ? 'Em Atraso' : ucfirst(str_replace('_', ' ', $act['status'])) ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Nenhuma atividade
                                            cadastrada.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD DE CHAMADOS E OCORRÊNCIAS (MODERNO) -->
        <div class="card card-custom mt-4" id="chamados">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span class="text-dark fw-bold">
                    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> Ocorrências & Chamados (Farol)
                </span>
                <button class="btn btn-danger btn-sm rounded-pill px-3" data-bs-toggle="collapse"
                    data-bs-target="#boxNovoChamado">
                    <i class="bi bi-plus-lg me-1"></i> Abrir Chamado
                </button>
            </div>
            <div class="card-body p-3">

                <!-- FORMULÁRIO DE ABERTURA -->
                <div class="collapse mb-4" id="boxNovoChamado">
                    <div class="p-3 bg-light rounded-3 border">
                        <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-pencil-square me-1"></i> Nova Ocorrência</h6>
                        <form method="POST">
                            <?= \App\Core\Csrf::input() ?>
                            <input type="hidden" name="action" value="abrir_chamado">
                            <input type="hidden" name="obra_id" value="<?= $obra_id ?>">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-semibold">Título do Chamado</label>
                                    <input type="text" name="titulo_chamado" class="form-control form-control-sm"
                                        required placeholder="Ex: Falta de EPI no setor B">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Nível de Urgência (Farol)</label>
                                    <select name="prioridade_chamado" class="form-select form-select-sm fw-semibold"
                                        required>
                                        <option value="verde" class="text-success">🟢 Verde - Baixa (Dúvida /
                                            Solicitação)</option>
                                        <option value="amarelo" class="text-warning">🟡 Amarelo - Média (Atenção /
                                            Atraso)</option>
                                        <option value="vermelho" class="text-danger">🔴 Vermelho - Urgente (Risco /
                                            Parada)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Descrição Detalhada</label>
                                    <textarea name="descricao_chamado" class="form-control form-control-sm" rows="3"
                                        required placeholder="Descreva os detalhes da ocorrência..."></textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="button" class="btn btn-light btn-sm me-1" data-bs-toggle="collapse"
                                        data-bs-target="#boxNovoChamado">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">
                                        <i class="bi bi-send me-1"></i> Registrar e Notificar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- CONTADORES DO FAROL -->
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <?php
                            $baseParams = $_GET;
                            $baseParams['fprioridade'] = '';
                            $urlAll = htmlspecialchars('?' . http_build_query($baseParams));
                            $baseParams['fprioridade'] = 'vermelho';
                            $urlRed = htmlspecialchars('?' . http_build_query($baseParams));
                            $baseParams['fprioridade'] = 'amarelo';
                            $urlYellow = htmlspecialchars('?' . http_build_query($baseParams));
                            $baseParams['fprioridade'] = 'verde';
                            $urlGreen = htmlspecialchars('?' . http_build_query($baseParams));
                        ?>
                        <a href="<?= $urlAll ?>" class="badge bg-light text-dark border">Todos
                            (<?= array_sum($countsAll) ?>)</a>
                        <a href="<?= $urlRed ?>" class="badge badge-soft-danger">🔴
                            <?= $countsAll['vermelho'] ?? 0 ?></a>
                        <a href="<?= $urlYellow ?>" class="badge badge-soft-warning">🟡
                            <?= $countsAll['amarelo'] ?? 0 ?></a>
                        <a href="<?= $urlGreen ?>" class="badge badge-soft-success">🟢
                            <?= $countsAll['verde'] ?? 0 ?></a>
                        <div class="ms-3 text-muted small">Nesta obra: 🔴 <?= $countsObra['vermelho'] ?? 0 ?> • 🟡
                            <?= $countsObra['amarelo'] ?? 0 ?> • 🟢 <?= $countsObra['verde'] ?? 0 ?></div>
                    </div>
                </div>

                <!-- TABELA DE CHAMADOS (TODAS AS OBRAS AGRUPADAS) -->
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th class="text-center">Prioridade</th>
                                <th>Ocorrência</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resChamadosTodas && $resChamadosTodas->num_rows > 0): ?>
                            <?php
                                $currentObra = null;
                                while ($ch = $resChamadosTodas->fetch_assoc()):
                                    $obraNome = $ch['obra_nome'] ?: 'Geral / Sem Obra';
                                    if ($obraNome !== $currentObra):
                                        $currentObra = $obraNome;
                            ?>
                            <tr class="table-active">
                                <td colspan="5" class="fw-bold text-dark">Obra: <?= htmlspecialchars($currentObra) ?>
                                </td>
                            </tr>
                            <?php endif; 
                                    $badgeClass = 'badge-soft-success';
                                    $icFarol = '🟢';
                                    if ($ch['prioridade'] === 'amarelo') { $badgeClass = 'badge-soft-warning'; $icFarol = '🟡'; }
                                    if ($ch['prioridade'] === 'vermelho') { $badgeClass = 'badge-soft-danger'; $icFarol = '🔴'; }
                            ?>
                            <tr>
                                <td class="text-center align-middle">
                                    <span class="badge <?= $badgeClass ?> rounded-pill px-2 py-1 fs-7">
                                        <?= $icFarol ?> <?= ucfirst($ch['prioridade']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="fw-bold text-dark d-block"><?= htmlspecialchars($ch['titulo']) ?></span>
                                    <small class="text-muted"><?= htmlspecialchars($ch['descricao']) ?></small>
                                </td>
                                <td><small
                                        class="text-muted"><?= date('d/m/Y H:i', strtotime($ch['data_abertura'])) ?></small>
                                </td>
                                <td><span
                                        class="badge bg-light text-secondary border"><?= ucfirst($ch['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if ($ch['status'] !== 'fechado'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= \App\Core\Csrf::input() ?>
                                        <input type="hidden" name="action" value="fechar_chamado">
                                        <input type="hidden" name="chamado_id" value="<?= $ch['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2"
                                            onclick="return confirm('Confirmar baixa deste chamado?')">
                                            <i class="bi bi-check-lg"></i> Baixar
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nenhum chamado aberto encontrado.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- BOOTSTRAP BUNDLE JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SCRIPT FILTRO FILTRAR DOCUMENTOS EM REAL-TIME -->
    <script>
    document.getElementById('inputBuscaDoc').addEventListener('keyup', function() {
        let filtro = this.value.toLowerCase();
        let linhas = document.querySelectorAll('#tabelaDocumentos .linha-documento');

        linhas.forEach(function(linha) {
            let nomeTxt = linha.querySelector('.coluna-nome').textContent.toLowerCase();
            let tipoTxt = linha.querySelector('.coluna-tipo').textContent.toLowerCase();

            if (nomeTxt.includes(filtro) || tipoTxt.includes(filtro)) {
                linha.style.display = '';
            } else {
                linha.style.display = 'none';
            }
        });
    });
    </script>
</body>

</html>
