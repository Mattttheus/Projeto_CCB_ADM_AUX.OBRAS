<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;

Auth::requireUser();

$user_id = (int)$_SESSION['usuario_id'];
$user_role = strtolower($_SESSION['tipo'] ?? $_SESSION['role'] ?? 'comum');
$error = '';
$success = '';

// Apenas o administrador pode criar, alterar ou remover usuários e seus níveis.
$tem_acesso_gerenciamento = ($user_role === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::validate($_POST['_token'] ?? null);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }

    // Processamento da Abertura de Chamado na própria página
    if ($error === '' && isset($_POST['abrir_chamado_direto'])) {
        $titulo_chamado = trim($_POST['titulo_chamado'] ?? '');
        $descricao_chamado = trim($_POST['descricao_chamado'] ?? '');

        if (empty($titulo_chamado) || empty($descricao_chamado)) {
            $error = 'Preencha o título e a descrição do chamado.';
        } else {
            $stmtChamado = $conn->prepare("INSERT INTO chamados (usuario_id, titulo, descricao, status, data_criacao) VALUES (?, ?, ?, 'Aberto', NOW())");
            
            if ($stmtChamado) {
                $stmtChamado->bind_param('iss', $user_id, $titulo_chamado, $descricao_chamado);
                if ($stmtChamado->execute()) {
                    $success = 'Chamado aberto com sucesso!';
                } else {
                    $error = 'Erro ao registrar chamado: ' . $conn->error;
                }
            } else {
                $success = 'Chamado enviado com sucesso para a equipe responsável!';
            }
        }
    }

    // 1. Ação para cadastrar Matheus Vinicius automaticamente como Suporte
    if ($error === '' && isset($_POST['cadastrar_matheus_suporte']) && $tem_acesso_gerenciamento) {
        $nome_matheus = "Matheus Vinicius";
        $email_matheus = "matheus.suporte@auxiliarobras.com.br";
        $senha_padrao = password_hash("Mudar@123", PASSWORD_DEFAULT);
        $role_suporte = "suporte";

        $stmtCheckM = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmtCheckM->bind_param('s', $email_matheus);
        $stmtCheckM->execute();
        
        if ($stmtCheckM->get_result()->num_rows > 0) {
            $error = 'O usuário Matheus Vinicius já está cadastrado no sistema.';
        } else {
            // Tentativa inteligente: detecta se a coluna é 'role' ou 'tipo' ou ambas para evitar quebras
            $queryInsert = "INSERT INTO usuarios (nome, email, senha, role, tipo) VALUES (?, ?, ?, ?, ?)";
            $stmtInsertM = $conn->prepare($queryInsert);
            
            if ($stmtInsertM) {
                $stmtInsertM->bind_param('sssss', $nome_matheus, $email_matheus, $senha_padrao, $role_suporte, $role_suporte);
                $executou = $stmtInsertM->execute();
            } else {
                // Fallback caso sua tabela use apenas uma das colunas (ex: apenas tipo ou apenas role)
                $queryInsert = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)";
                $stmtInsertM = $conn->prepare($queryInsert);
                if ($stmtInsertM) {
                    $stmtInsertM->bind_param('ssss', $nome_matheus, $email_matheus, $senha_padrao, $role_suporte);
                    $executou = $stmtInsertM->execute();
                } else {
                    $queryInsert = "INSERT INTO usuarios (nome, email, senha, role) VALUES (?, ?, ?, ?)";
                    $stmtInsertM = $conn->prepare($queryInsert);
                    $stmtInsertM->bind_param('ssss', $nome_matheus, $email_matheus, $senha_padrao, $role_suporte);
                    $executou = $stmtInsertM->execute();
                }
            }

            if ($executou) {
                $success = 'Usuário "Matheus Vinicius" cadastrado com perfil Suporte! Senha provisória: Mudar@123';
            } else {
                $error = 'Erro ao cadastrar Matheus Vinicius: ' . $conn->error;
            }
        }
    }

    // 2. Ação de Cadastro de Novo Usuário Manual
    if ($error === '' && isset($_POST['novo_usuario_manual']) && $tem_acesso_gerenciamento) {
        $n_nome = trim($_POST['n_nome'] ?? '');
        $n_email = filter_var(trim($_POST['n_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $n_perfil = trim($_POST['n_perfil'] ?? 'comum');
        $n_senha_texto = (string) ($_POST['n_senha'] ?? '');
        $n_senha = password_hash($n_senha_texto, PASSWORD_DEFAULT);

        if (!$n_email || empty($n_nome) || strlen($n_senha_texto) < 12) {
            $error = 'Informe nome, e-mail e uma senha com ao menos 12 caracteres.';
        } else {
            $stmtCheckU = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmtCheckU->bind_param('s', $n_email);
            $stmtCheckU->execute();
            if ($stmtCheckU->get_result()->num_rows > 0) {
                $error = 'E-mail já está em uso.';
            } else {
                $stmtInsertU = $conn->prepare("INSERT INTO usuarios (nome, email, senha, role, tipo) VALUES (?, ?, ?, ?, ?)");
                if ($stmtInsertU) {
                    $stmtInsertU->bind_param('sssss', $n_nome, $n_email, $n_senha, $n_perfil, $n_perfil);
                    $exec = $stmtInsertU->execute();
                } else {
                    $stmtInsertU = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
                    if ($stmtInsertU) {
                        $stmtInsertU->bind_param('ssss', $n_nome, $n_email, $n_senha, $n_perfil);
                        $exec = $stmtInsertU->execute();
                    } else {
                        $stmtInsertU = $conn->prepare("INSERT INTO usuarios (nome, email, senha, role) VALUES (?, ?, ?, ?)");
                        $stmtInsertU->bind_param('ssss', $n_nome, $n_email, $n_senha, $n_perfil);
                        $exec = $stmtInsertU->execute();
                    }
                }
                
                if ($exec) {
                    $success = "Usuário '$n_nome' cadastrado com sucesso!";
                } else {
                    $error = 'Erro ao cadastrar usuário: ' . $conn->error;
                }
            }
        }
    }

    // 3. Ação de Alteração/Edição de Usuário (Corrigido com Fallback de colunas)
    if ($error === '' && isset($_POST['editar_usuario_sistema']) && $tem_acesso_gerenciamento) {
        $edit_id = (int)($_POST['edit_id'] ?? 0);
        $edit_nome = trim($_POST['edit_nome'] ?? '');
        $edit_email = filter_var(trim($_POST['edit_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $edit_perfil = trim($_POST['edit_perfil'] ?? 'comum');

        if ($edit_id > 0 && $edit_email && !empty($edit_nome)) {
            
            // Tenta atualizar ambas as colunas (role e tipo)
            $stmtUpdateU = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, role = ?, tipo = ? WHERE id = ?");
            
            if ($stmtUpdateU) {
                $stmtUpdateU->bind_param('ssssi', $edit_nome, $edit_email, $edit_perfil, $edit_perfil, $edit_id);
                $atualizou = $stmtUpdateU->execute();
            } else {
                // Se falhar porque uma das duas colunas não existe no banco, tenta apenas com 'tipo'
                $stmtUpdateU = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, tipo = ? WHERE id = ?");
                if ($stmtUpdateU) {
                    $stmtUpdateU->bind_param('sssi', $edit_nome, $edit_email, $edit_perfil, $edit_id);
                    $atualizou = $stmtUpdateU->execute();
                } else {
                    // Se ainda assim falhar, tenta apenas com 'role'
                    $stmtUpdateU = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, role = ? WHERE id = ?");
                    $stmtUpdateU->bind_param('sssi', $edit_nome, $edit_email, $edit_perfil, $edit_id);
                    $atualizou = $stmtUpdateU->execute();
                }
            }

            if ($atualizou) {
                $success = 'Usuário atualizado com sucesso.';
            } else {
                $error = 'Erro ao atualizar dados do usuário: ' . $conn->error;
            }
        } else {
            $error = 'Parâmetros incorretos para atualização.';
        }
    }

    // Apenas o administrador define os responsáveis pelas obras.
    if ($error === '' && ($_POST['acao_responsavel'] ?? '') === 'vincular' && $user_role === 'admin') {
        $obraId = (int) ($_POST['obra_id'] ?? 0);
        $responsavelId = (int) ($_POST['responsavel_id'] ?? 0);
        $statement = $conn->prepare(
            'INSERT INTO obra_responsaveis (obra_id, usuario_id) SELECT ?, u.id FROM usuarios u WHERE u.id = ? AND COALESCE(u.role, u.tipo) NOT IN (\'admin\', \'suporte\') AND NOT EXISTS (SELECT 1 FROM obra_responsaveis r WHERE r.obra_id = ? AND r.usuario_id = u.id)'
        );
        if ($obraId < 1 || $responsavelId < 1 || !$statement) {
            $error = 'Selecione uma obra e um usuário válido.';
        } else {
            $statement->bind_param('iii', $obraId, $responsavelId, $obraId);
            $statement->execute();
            $success = $statement->affected_rows > 0
                ? 'Responsável vinculado à obra com sucesso.'
                : 'O usuário já possui acesso a esta obra ou não pode ser vinculado.';
            $statement->close();
        }
    }

    if ($error === '' && ($_POST['acao_responsavel'] ?? '') === 'remover' && $user_role === 'admin') {
        $obraId = (int) ($_POST['obra_id'] ?? 0);
        $responsavelId = (int) ($_POST['responsavel_id'] ?? 0);
        $statement = $conn->prepare('DELETE FROM obra_responsaveis WHERE obra_id = ? AND usuario_id = ?');
        if ($obraId < 1 || $responsavelId < 1 || !$statement) {
            $error = 'Vínculo de responsável inválido.';
        } else {
            $statement->bind_param('ii', $obraId, $responsavelId);
            $statement->execute();
            $success = 'Acesso do responsável removido da obra.';
            $statement->close();
        }
    }

    // 4. Ação de Remoção/Exclusão de Usuário
    if ($error === '' && isset($_POST['remover_usuario_sistema']) && $tem_acesso_gerenciamento) {
        $remove_id = (int)($_POST['remove_id'] ?? 0);
        if ($remove_id === $user_id) {
            $error = 'Você não pode excluir sua própria conta.';
        } elseif ($remove_id > 0) {
            $stmtDeleteU = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmtDeleteU->bind_param('i', $remove_id);
            if ($stmtDeleteU->execute()) {
                $success = 'Usuário removido com sucesso do sistema.';
            } else {
                $error = 'Erro ao deletar o usuário: ' . $conn->error;
            }
        }
    }

    // Uploads devem ocorrer na página da obra, onde há validação de PDF e autorização por obra.
    if ($error === '' && isset($_POST['upload_documento'])) {
        $error = 'Envie documentos pela página da obra.';
    }

    // 6. Atualização de Perfil Próprio
    if ($error === '' && isset($_POST['update_profile'])) {
        $nome = trim($_POST['nome'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email || empty($nome)) {
            $error = 'Dados inválidos.';
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
            $stmt->bind_param('ssi', $nome, $email, $user_id);
            if ($stmt->execute()) {
                $_SESSION['usuario_nome'] = $nome;
                $success = 'Perfil atualizado.';
            }
        }
    }

    // 7. Reset/Troca forçada de senhas
    if ($error === '' && isset($_POST['reset_user_password']) && $tem_acesso_gerenciamento) {
        $target_user_id = (int)($_POST['target_user_id'] ?? 0);
        $forced_password = $_POST['nova_senha_forcada'] ?? '';
        if ($target_user_id > 0 && strlen($forced_password) >= 12) {
            $newHash = password_hash($forced_password, PASSWORD_DEFAULT);
            $stmtReset = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmtReset->bind_param('si', $newHash, $target_user_id);
            if ($stmtReset->execute()) $success = 'Senha redefinida com sucesso.';
        } else {
            $error = 'Informe uma senha com ao menos 12 caracteres.';
        }
    }

    // 8. Enfileira lembrete para o usuário que possui chamados pendentes
    if ($error === '' && isset($_POST['enviar_lembrete_chamados']) && $tem_acesso_gerenciamento) {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $stmtPending = $conn->prepare("SELECT u.nome, u.email, c.id, c.titulo, c.prioridade, c.data_abertura FROM usuarios u INNER JOIN chamados c ON c.usuario_id = u.id WHERE u.id = ? AND c.status NOT IN ('resolvido', 'fechado') ORDER BY c.data_abertura ASC");
        if ($targetUserId < 1 || !$stmtPending) {
            $error = 'Não foi possível localizar os chamados do usuário.';
        } else {
            $stmtPending->bind_param('i', $targetUserId);
            $stmtPending->execute();
            $pendingResult = $stmtPending->get_result();
            $first = $pendingResult->fetch_assoc();
            if (!$first) {
                $error = 'Este usuário não possui chamados pendentes.';
            } else {
                $items = '<ul>';
                $items .= '<li><strong>' . htmlspecialchars($first['titulo'], ENT_QUOTES, 'UTF-8') . '</strong> — prioridade ' . htmlspecialchars($first['prioridade'], ENT_QUOTES, 'UTF-8') . '</li>';
                while ($call = $pendingResult->fetch_assoc()) {
                    $items .= '<li><strong>' . htmlspecialchars($call['titulo'], ENT_QUOTES, 'UTF-8') . '</strong> — prioridade ' . htmlspecialchars($call['prioridade'], ENT_QUOTES, 'UTF-8') . '</li>';
                }
                $items .= '</ul>';
                $subject = 'Lembrete: chamados pendentes - Auxiliar Obras';
                $body = '<p>Olá, ' . htmlspecialchars($first['nome'], ENT_QUOTES, 'UTF-8') . '.</p><p>Existem chamados pendentes que precisam de acompanhamento:</p>' . $items . '<p>Acesse o sistema para atualizar o status.</p>';
                $queue = $conn->prepare("INSERT INTO fila_emails (destinatario, assunto, mensagem_html, status, tentativas) VALUES (?, ?, ?, 'pendente', 0)");
                if ($queue) {
                    $queue->bind_param('sss', $first['email'], $subject, $body);
                    $success = $queue->execute() ? 'Lembrete incluído na fila de e-mails.' : 'Não foi possível incluir o lembrete na fila.';
                    $queue->close();
                } else {
                    $error = 'Fila de e-mails indisponível. Execute a migração de e-mails.';
                }
            }
            $stmtPending->close();
        }
    }
}

// Busca dados atualizados para exibição na tela
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$nome = $userData['nome'] ?? '';
$email = $userData['email'] ?? '';

$usersList = null;
if ($tem_acesso_gerenciamento) {
    $usersList = $conn->query("SELECT u.id, u.nome, u.email, u.role, u.tipo,
        SUM(CASE WHEN c.status NOT IN ('resolvido', 'fechado') THEN 1 ELSE 0 END) AS chamados_abertos,
        SUM(CASE WHEN c.status IN ('resolvido', 'fechado') THEN 1 ELSE 0 END) AS chamados_resolvidos
        FROM usuarios u LEFT JOIN chamados c ON c.usuario_id = u.id
        GROUP BY u.id, u.nome, u.email, u.role, u.tipo ORDER BY u.nome ASC");
}

$obrasComResponsaveis = null;
$usuariosResponsaveis = null;
if ($user_role === 'admin') {
    $obrasComResponsaveis = $conn->query("SELECT o.id, o.nome, GROUP_CONCAT(u.nome ORDER BY u.nome SEPARATOR ', ') AS responsaveis FROM obras o LEFT JOIN obra_responsaveis r ON r.obra_id = o.id LEFT JOIN usuarios u ON u.id = r.usuario_id GROUP BY o.id, o.nome ORDER BY o.nome ASC");
    $usuariosResponsaveis = $conn->query("SELECT id, nome FROM usuarios WHERE COALESCE(role, tipo) NOT IN ('admin', 'suporte') ORDER BY nome ASC");
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Meu Perfil - Auxiliar Obras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <header class="bg-white border-bottom shadow-sm mb-4">
        <div class="container py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="fs-4 fw-bold text-primary">Auxiliar Obras</span>
                <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($user_role) ?></span>
            </div>
            <?php if ($tem_acesso_gerenciamento): ?>
            <a href="log_emails.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">Gerenciar e-mails</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-outline-primary d-flex align-items-center gap-2">Voltar ao
                Dashboard</a>
        </div>
    </header>

    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <?php if ($error): ?><div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success mb-4"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <!-- Informações do Perfil Logado -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Meu Perfil</h4>
                        <form method="POST">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="update_profile" value="1">
                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" name="nome" class="form-control"
                                    value="<?= htmlspecialchars($nome) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control"
                                    value="<?= htmlspecialchars($email) ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Salvar Perfil</button>
                        </form>
                    </div>
                </div>

                <!-- Painel de Ações e Acessos -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Ações Disponíveis</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (in_array($user_role, ['user', 'comum', 'operador', 'engenheiro', 'mestre_obras', 'admin', 'suporte'], true)): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="collapse"
                                data-bs-target="#boxAbrirChamado">Abrir Chamado</button>
                            <?php endif; ?>

                            <?php if (in_array($user_role, ['operador', 'engenheiro', 'mestre_obras', 'admin', 'suporte'], true)): ?>
                            <a href="gerenciar_obra.php#chamados" class="btn btn-outline-success">Resolução de
                                Chamados</a>
                            <a href="gerenciar_obra.php#atividades" class="btn btn-outline-info">Lançamento de Atividades</a>
                            <?php endif; ?>

                            <?php if ($tem_acesso_gerenciamento): ?>
                            <button type="button" class="btn btn-warning" data-bs-toggle="collapse"
                                data-bs-target="#boxNovoUsuario">Novo Usuário Manual</button>
                            <?php endif; ?>
                        </div>

                        <!-- Formulário Integrado: Abrir Chamado -->
                        <div class="collapse mt-3" id="boxAbrirChamado">
                            <div class="card card-body bg-light border">
                                <h6>Abrir Novo Chamado</h6>
                                <form method="POST">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="abrir_chamado_direto" value="1">
                                    <div class="mb-2">
                                        <input type="text" name="titulo_chamado" class="form-control form-control-sm"
                                            placeholder="Título / Assunto do chamado" required>
                                    </div>
                                    <div class="mb-2">
                                        <textarea name="descricao_chamado" class="form-control form-control-sm" rows="3"
                                            placeholder="Descreva o problema ou solicitação..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">Enviar Chamado</button>
                                </form>
                            </div>
                        </div>

                        <!-- Form de Cadastro Manual -->
                        <div class="collapse mt-3" id="boxNovoUsuario">
                            <div class="card card-body bg-light border">
                                <h6>Cadastrar Novo Usuário</h6>
                                <form method="POST">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="novo_usuario_manual" value="1">
                                    <div class="mb-2"><input type="text" name="n_nome"
                                            class="form-control form-control-sm" placeholder="Nome" required></div>
                                    <div class="mb-2"><input type="email" name="n_email"
                                            class="form-control form-control-sm" placeholder="E-mail" required></div>
                                    <div class="mb-2"><input type="password" name="n_senha"
                                            class="form-control form-control-sm" placeholder="Senha" minlength="12" required></div>
                                    <div class="mb-2">
                                        <select name="n_perfil" class="form-select form-select-sm">
                                            <option value="comum">Comum (Leitor)</option>
                                            <option value="operador">Operador</option>
                                            <option value="suporte">Suporte</option>
                                            <option value="admin">Administrador</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-warning">Criar Cadastro</button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Painel de Gerenciamento Geral (Exclusivo Administrador) -->
                <?php if ($user_role === 'admin'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Responsáveis por Obra</h4>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead><tr><th>Obra</th><th>Responsáveis com acesso</th><th>Adicionar responsável</th></tr></thead>
                                <tbody>
                                    <?php while ($obra = $obrasComResponsaveis->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($obra['nome']) ?></td>
                                        <td><?= htmlspecialchars($obra['responsaveis'] ?? 'Nenhum responsável definido') ?></td>
                                        <td>
                                            <form method="POST" class="d-flex gap-2">
                                                <?= Csrf::input() ?>
                                                <input type="hidden" name="obra_id" value="<?= (int) $obra['id'] ?>">
                                                <select name="responsavel_id" class="form-select form-select-sm" required>
                                                    <option value="">Selecionar usuário</option>
                                                    <?php $usuariosResponsaveis->data_seek(0); while ($responsavel = $usuariosResponsaveis->fetch_assoc()): ?>
                                                    <option value="<?= (int) $responsavel['id'] ?>"><?= htmlspecialchars($responsavel['nome']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <button type="submit" name="acao_responsavel" value="vincular" class="btn btn-sm btn-primary">Vincular</button>
                                                <button type="submit" name="acao_responsavel" value="remover" class="btn btn-sm btn-outline-danger">Revogar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($tem_acesso_gerenciamento): ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Gerenciar, Alterar e Remover Usuários</h4>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th class="text-center">Chamados</th>
                                        <th>Nível</th>
                                        <th>Ações / Modificações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($usersList && $usersList->num_rows > 0): ?>
                                    <?php while ($usuario = $usersList->fetch_assoc()): 
                                            $userLvl = $usuario['role'] ?? $usuario['tipo'] ?? 'comum';
                                        ?>
                                    <tr>
                                        <td><?= htmlspecialchars($usuario['nome']) ?></td>
                                        <td><?= htmlspecialchars($usuario['email']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark" title="Chamados abertos ou em atendimento"><?= (int) $usuario['chamados_abertos'] ?> abertos</span>
                                            <span class="badge bg-success mt-1" title="Chamados resolvidos ou fechados"><?= (int) $usuario['chamados_resolvidos'] ?> resolvidos</span>
                                        </td>
                                        <td><span
                                                class="badge bg-light text-dark text-uppercase"><?= htmlspecialchars($userLvl) ?></span>
                                        </td>
                                        <td>
                                            <!-- Edição Integrada -->
                                            <form method="POST" class="d-inline-block me-1">
                                                <?= Csrf::input() ?>
                                                <input type="hidden" name="editar_usuario_sistema" value="1">
                                                <input type="hidden" name="edit_id" value="<?= $usuario['id'] ?>">
                                                <input type="text" name="edit_nome"
                                                    class="form-control form-control-sm d-inline-block mb-1"
                                                    style="width:120px;"
                                                    value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                                                <input type="email" name="edit_email"
                                                    class="form-control form-control-sm d-inline-block mb-1"
                                                    style="width:140px;"
                                                    value="<?= htmlspecialchars($usuario['email']) ?>" required>
                                                <select name="edit_perfil"
                                                    class="form-select form-select-sm d-inline-block mb-1"
                                                    style="width:100px变量;">
                                                    <option value="comum" <?= ($userLvl=='comum')?'selected':'' ?>>Comum
                                                    </option>
                                                    <option value="operador"
                                                        <?= ($userLvl=='operador')?'selected':'' ?>>Operador</option>
                                                    <option value="suporte" <?= ($userLvl=='suporte')?'selected':'' ?>>
                                                        Suporte</option>
                                                    <option value="admin" <?= ($userLvl=='admin')?'selected':'' ?>>Admin
                                                    </option>
                                                </select>
                                                <button type="submit"
                                                    class="btn btn-sm btn-success mb-1">Alterar</button>
                                            </form>

                                            <!-- Reset de Senha -->
                                            <form method="POST" class="d-inline-block me-1">
                                                <?= Csrf::input() ?>
                                                <input type="hidden" name="reset_user_password" value="1">
                                                <input type="hidden" name="target_user_id"
                                                    value="<?= $usuario['id'] ?>">
                                                <input type="password" name="nova_senha_forcada"
                                                    class="form-control form-control-sm d-inline-block"
                                                    style="width:100px;" placeholder="Nova Senha" minlength="12" required>
                                                <button type="submit" class="btn btn-sm btn-secondary">Senha</button>
                                            </form>

                                            <?php if ((int) $usuario['chamados_abertos'] > 0): ?>
                                            <form method="POST" class="d-inline-block me-1" onsubmit="return confirm('Enviar lembrete de chamados pendentes para este usuário?');">
                                                <?= Csrf::input() ?>
                                                <input type="hidden" name="enviar_lembrete_chamados" value="1">
                                                <input type="hidden" name="target_user_id" value="<?= (int) $usuario['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Enviar lembrete por e-mail"><i class="bi bi-envelope"></i> Lembrar</button>
                                            </form>
                                            <?php endif; ?>

                                            <!-- Remoção / Exclusão -->
                                            <form method="POST" class="d-inline-block"
                                                onsubmit="return confirm('Tem certeza absoluta que deseja REMOVER este usuário?');">
                                                <?= Csrf::input() ?>
                                                <input type="hidden" name="remover_usuario_sistema" value="1">
                                                <input type="hidden" name="remove_id" value="<?= $usuario['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
