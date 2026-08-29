<?php
// cron/processar_fila.php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/mailer.php';

// Buscar até 20 e-mails pendentes por lote
$sqlFila = "SELECT * FROM fila_emails WHERE status = 'pendente' AND tentativas < 3 LIMIT 20";
$resFila = $conn->query($sqlFila);

if ($resFila && $resFila->num_rows > 0) {
    while ($item = $resFila->fetch_assoc()) {
        $id = $item['id'];
        
        // Se a classe PHPMailer não estiver disponível (dependências não instaladas), registre e pule
        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            $logLine = date('Y-m-d H:i:s') . " - id={$id} - PHPMailer class not found; skipping.\n";
            @file_put_contents(__DIR__ . '/fila_errors.log', $logLine, FILE_APPEND);
            continue;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // Configurações SMTP
            $mail->isSMTP();
            $mail->Host       = $mail_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $mail_user;
            $mail->Password   = $mail_pass;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($mail_user, 'Auxiliar Obras');
            $mail->addAddress($item['destinatario']);
            $mail->isHTML(true);
            $mail->Subject = $item['assunto'];
            $mail->Body    = $item['mensagem_html'];

            $mail->send();

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

        } catch (\Exception $e) {
            $erroMsg = $mail->ErrorInfo . ' | ' . $e->getMessage();
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
