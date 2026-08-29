<?php
// ==========================================
// CONFIGURAÇÃO DE DISPARO DE E-MAIL (BREVO SMTP)
// ==========================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclui o autoload do Composer se a pasta vendor existir
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Credenciais SMTP fornecidas pela Brevo
$mail_host = 'smtp-relay.brevo.com';
$mail_port = 587;
$mail_user = 'b70f9f001@smtp-brevo.com';
$mail_pass = 'SUA_CHAVE_SMTP_AQUI'; // Insira a Master Key / Chave API gerada na Brevo

/**
 * Função para disparar alerta de novos chamados/ocorrências para os responsáveis
 * 
 * @param array $emails Lista de e-mails dos destinatários
 * @param string $nomeObra Nome da obra relacionada
 * @param string $titulo Título do chamado
 * @param string $descricao Descrição detalhada
 * @param string $prioridade Prioridade (verde, amarelo, vermelho)
 * @return bool
 */
function enviarAlertaChamado(array $emails, string $nomeObra, string $titulo, string $descricao, string $prioridade): bool {
    global $mail_host, $mail_port, $mail_user, $mail_pass;

    if (empty($emails)) {
        return false;
    }

    // Verifica se a classe PHPMailer foi carregada
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("Erro: Classe PHPMailer não encontrada. Verifique o autoloader em vendor/autoload.php");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Configurações do Servidor
        $mail->isSMTP();
        $mail->Host       = $mail_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_user;
        $mail->Password   = $mail_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mail_port;
        $mail->CharSet    = 'UTF-8';

        // Remetente (Use o mesmo e-mail ou domínio verificado na Brevo)
        $mail->setFrom($mail_user, 'Auxiliar Obras - Sistema');

        // Adicionar Destinatários
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email);
            }
        }

        // Conteúdo do E-mail
        $badgeFarol = strtoupper($prioridade);
        $mail->isHTML(true);
        $mail->Subject = "[ALERTA - {$badgeFarol}] Novo Chamado na Obra: {$nomeObra}";
        
        $mail->Body = "
            <h2>Novo Chamado / Ocorrência Registrada</h2>
            <p><strong>Obra:</strong> " . htmlspecialchars($nomeObra) . "</p>
            <p><strong>Prioridade (Farol):</strong> {$badgeFarol}</p>
            <p><strong>Título:</strong> " . htmlspecialchars($titulo) . "</p>
            <p><strong>Descrição:</strong><br>" . nl2br(htmlspecialchars($descricao)) . "</p>
            <hr>
            <p><small>Acesse o painel do Auxiliar Obras para gerenciar este chamado.</small></p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erro no envio de e-mail via PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}