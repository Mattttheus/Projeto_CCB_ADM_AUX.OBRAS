<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/conexao.php';

$mail_host = getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com';
$mail_port = (int) (getenv('MAIL_PORT') ?: 587);
$mail_user = getenv('MAIL_USER') ?: '';
$mail_pass = getenv('MAIL_PASSWORD') ?: '';
$mail_from_email = getenv('MAIL_FROM_EMAIL') ?: $mail_user;

function enviarEmailFila(string $destinatario, string $assunto, string $mensagemHtml): void
{
    global $mail_host, $mail_port, $mail_user, $mail_pass, $mail_from_email;

    if ($mail_user === '' || $mail_pass === '' || !class_exists(PHPMailer::class)) {
        throw new RuntimeException('Configure MAIL_USER e MAIL_PASSWORD para enviar e-mails.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $mail_host;
    $mail->SMTPAuth = true;
    $mail->Username = $mail_user;
    $mail->Password = $mail_pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $mail_port;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($mail_from_email, 'Auxiliar Obras');
    $mail->addAddress($destinatario);
    $mail->isHTML(true);
    $mail->Subject = $assunto;
    $mail->Body = $mensagemHtml;
    $mail->send();
}

function enviarAlertaChamado(
    array $emails,
    string $nomeObra,
    string $titulo,
    string $descricao,
    string $prioridade
): bool {
    global $mail_host, $mail_port, $mail_user, $mail_pass, $mail_from_email;

    if ($emails === [] || $mail_user === '' || $mail_pass === '' || !class_exists(PHPMailer::class)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $mail_host;
        $mail->SMTPAuth = true;
        $mail->Username = $mail_user;
        $mail->Password = $mail_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $mail_port;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($mail_from_email, 'Auxiliar Obras - Sistema');

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email);
            }
        }

        if (count($mail->getToAddresses()) === 0) {
            return false;
        }

        $badgeFarol = strtoupper($prioridade);
        $mail->isHTML(true);
        $mail->Subject = "[ALERTA - {$badgeFarol}] Novo Chamado na Obra: {$nomeObra}";
        $mail->Body = "
            <h2>Novo Chamado / Ocorrencia Registrada</h2>
            <p><strong>Obra:</strong> " . htmlspecialchars($nomeObra, ENT_QUOTES, 'UTF-8') . "</p>
            <p><strong>Prioridade (Farol):</strong> {$badgeFarol}</p>
            <p><strong>Titulo:</strong> " . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . "</p>
            <p><strong>Descricao:</strong><br>" . nl2br(htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8')) . "</p>
            <hr>
            <p><small>Acesse o painel do Auxiliar Obras para gerenciar este chamado.</small></p>
        ";

        $mail->send();
        return true;
    } catch (Exception $exception) {
        error_log('Erro no envio de e-mail via PHPMailer: ' . $mail->ErrorInfo);
        return false;
    }
}
