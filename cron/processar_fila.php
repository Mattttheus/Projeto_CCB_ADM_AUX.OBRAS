<?php
// cron/processar_fila.php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/mailer.php';

if ($mail_user === '' || $mail_pass === '') {
    error_log('Configuração SMTP incompleta: defina MAIL_USER e MAIL_PASSWORD no ambiente.');
    $conn->query("UPDATE fila_emails SET erro_mensagem = 'Configuração SMTP incompleta. Defina MAIL_USER e MAIL_PASSWORD no arquivo .env.' WHERE status = 'pendente' AND tentativas = 0");
    exit(1);
}

// Buscar até 20 e-mails pendentes por lote
$sqlFila = "SELECT * FROM fila_emails WHERE status = 'pendente' AND tentativas < 3 LIMIT 20";
$resFila = $conn->query($sqlFila);

if ($resFila && $resFila->num_rows > 0) {
    while ($item = $resFila->fetch_assoc()) {
        $id = $item['id'];
        
        try {
            enviarEmailFila($item['destinatario'], $item['assunto'], $item['mensagem_html']);

            // Atualiza status na fila para enviado (prepared statement)
            $stmtUp = $conn->prepare("UPDATE fila_emails SET status = 'enviado', data_envio = NOW() WHERE id = ?");
            if ($stmtUp) {
                $stmtUp->bind_param("i", $id);
                if (!$stmtUp->execute()) {
                    $updErr = $stmtUp->error;
                    $logLine = date('Y-m-d H:i:s') . " - id={$id} - update enviado falhou: " . $updErr . PHP_EOL;
                    file_put_contents(__DIR__ . '/fila_errors.log', $logLine, FILE_APPEND);
                }
                $stmtUp->close();
            } else {
                $logLine = date('Y-m-d H:i:s') . " - id={$id} - prepare update enviado failed: " . $conn->error . PHP_EOL;
                file_put_contents(__DIR__ . '/fila_errors.log', $logLine, FILE_APPEND);
            }

        } catch (\Throwable $e) {
            $erroMsg = $e->getMessage();
            // Incrementa tentativas e salva mensagem de erro (prepared statement)
            // Define status como 'erro' quando (tentativas + 1) >= 3
            $stmtErr = $conn->prepare("UPDATE fila_emails SET tentativas = tentativas + 1, erro_mensagem = ?, status = CASE WHEN (tentativas + 1) >= 3 THEN 'erro' ELSE 'pendente' END WHERE id = ?");
            if ($stmtErr) {
                $stmtErr->bind_param("si", $erroMsg, $id);
                if (!$stmtErr->execute()) {
                    $stmtErrErr = $stmtErr->error;
                    $logLine = date('Y-m-d H:i:s') . " - id={$id} - update tentativas falhou: " . $stmtErrErr . PHP_EOL;
                    file_put_contents(__DIR__ . '/fila_errors.log', $logLine, FILE_APPEND);
                }
                $stmtErr->close();
            } else {
                $logLine = date('Y-m-d H:i:s') . " - id={$id} - prepare update tentativas failed: " . $conn->error . PHP_EOL;
                file_put_contents(__DIR__ . '/fila_errors.log', $logLine, FILE_APPEND);
            }

            // Log do erro para auditoria
            $logLine = date('Y-m-d H:i:s') . " - id={$id} - " . $erroMsg . PHP_EOL;
            $res = file_put_contents(__DIR__ . '/fila_errors.log', $logLine, FILE_APPEND);
            if ($res === false) {
                error_log('Falha ao escrever em fila_errors.log para id=' . $id);
            }
        }
    }
}
