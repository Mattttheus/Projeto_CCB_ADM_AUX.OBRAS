<?php
// ... [Código anterior de sessão e conexão]
require_once("../config/mailer.php");

// ==========================================
// PROCESSAR ABERTURA DE CHAMADO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'abrir_chamado') {
    $titulo_c     = trim($_POST['titulo_chamado'] ?? '');
    $descricao_c  = trim($_POST['descricao_chamado'] ?? '');
    $prioridade_c = $_POST['prioridade_chamado'] ?? 'verde';
    $user_id      = $_SESSION['usuario_id'];

    if (empty($titulo_c) || empty($descricao_c)) {
        $erro = "Preencha o título e a descrição do chamado.";
    } else {
        $stmtC = $conn->prepare("INSERT INTO chamados_obras (obra_id, usuario_id, titulo, descricao, prioridade) VALUES (?, ?, ?, ?, ?)");
        $stmtC->bind_param("iisss", $obra_id, $user_id, $titulo_c, $descricao_c, $prioridade_c);
        
        if ($stmtC->execute()) {
            $msg = "Chamado aberto com sucesso!";

            // Buscar e-mails dos responsáveis vinculados à obra
            $sqlResp = "SELECT u.email FROM usuarios u 
                        INNER JOIN obra_responsaveis r ON r.usuario_id = u.id 
                        WHERE r.obra_id = $obra_id";
            $resResp = $conn->query($sqlResp);
            
            $emails = [];
            if ($resResp && $resResp->num_rows > 0) {
                while ($r = $resResp->fetch_assoc()) {
                    $emails[] = $r['email'];
                }
                
                // Dispara o e-mail em segundo plano
                enviarAlertaChamado($emails, $obraAtual['nome'], $titulo_c, $descricao_c, $prioridade_c);
            }
        } else {
            $erro = "Erro ao registrar chamado.";
        }
        $stmtC->close();
    }
}

// Buscar chamados da obra selecionada
$resChamados = $conn->query("SELECT c.*, u.nome as solicitante 
                            FROM chamados_obras c 
                            JOIN usuarios u ON u.id = c.usuario_id 
                            WHERE c.obra_id = $obra_id 
                            ORDER BY 
                            CASE c.prioridade 
                                WHEN 'vermelho' THEN 1 
                                WHEN 'amarelo' THEN 2 
                                WHEN 'verde' THEN 3 
                            END ASC, c.data_abertura DESC");
?>

<!-- HTML DA SEÇÃO DE CHAMADOS / OCORRÊNCIAS -->
<div class="card mb-4">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Chamados & Ocorrências da Obra</h5>
        <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#formNovoChamado">+ Abrir
            Chamado</button>
    </div>
    <div class="card-body">

        <!-- FORMULÁRIO DE ABERTURA -->
        <div class="collapse mb-4" id="formNovoChamado">
            <div class="p-3 bg-light border rounded">
                <h6>Abertura de Chamado / Ocorrência</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="abrir_chamado">
                    <div class="mb-2">
                        <label class="form-label">Título da Ocorrência</label>
                        <input type="text" name="titulo_chamado" class="form-control form-control-sm" required
                            placeholder="Ex: Falta de EPIs / Risco na estrutura">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nível de Necessidade (Farol)</label>
                        <select name="prioridade_chamado" class="form-select form-select-sm" required>
                            <option value="verde">🟢 Verde - Baixa Prioridade (Dúvidas/Solicitações rotineiras)</option>
                            <option value="amarelo">🟡 Amarelo - Média Prioridade (Atenção/Impacto moderado)</option>
                            <option value="vermelho">🔴 Vermelho - Alta Prioridade / Urgente (Risco/Paralisação)
                            </option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição Detalhada</label>
                        <textarea name="descricao_chamado" class="form-control form-control-sm" rows="3"
                            required></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm w-100">Abrir Chamado e Notificar
                        Responsáveis</button>
                </form>
            </div>
        </div>

        <!-- LISTA DE CHAMADOS COM INDICADOR DE FAROL -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Farol</th>
                        <th>Ocorrência</th>
                        <th>Solicitante</th>
                        <th>Data</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resChamados && $resChamados->num_rows > 0): ?>
                    <?php while ($ch = $resChamados->fetch_assoc()): 
                            $farolClass = 'bg-success';
                            if ($ch['prioridade'] === 'amarelo') $farolClass = 'bg-warning text-dark';
                            if ($ch['prioridade'] === 'vermelho') $farolClass = 'bg-danger';
                        ?>
                    <tr>
                        <td class="text-center">
                            <span class="badge rounded-circle p-2 <?=$farolClass?>"
                                title="Prioridade <?=ucfirst($ch['prioridade'])?>"> </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($ch['titulo']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($ch['descricao']) ?></small>
                        </td>
                        <td><small><?= htmlspecialchars($ch['solicitante']) ?></small></td>
                        <td><small><?= date('d/m/Y H:i', strtotime($ch['data_abertura'])) ?></small></td>
                        <td>
                            <span class="badge bg-outline-dark border text-dark">
                                <?= strtoupper(str_replace('_', ' ', $ch['status'])) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">Nenhum chamado aberto para esta obra.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>