<?php
declare(strict_types=1);

namespace App\Application\Notification;

use mysqli;
use RuntimeException;

final class AdminNotificationService
{
    public function __construct(private mysqli $connection) {}

    public function notifyOverdueActivity(int $activityId): void
    {
        $statement = $this->connection->prepare("SELECT a.titulo, a.data_limite, o.nome AS obra_nome FROM atividades a LEFT JOIN obras o ON o.id = a.obra_id WHERE a.id = ? AND a.data_limite < CURDATE() AND a.status <> 'concluida' LIMIT 1");
        if (!$statement) throw new RuntimeException('Não foi possível consultar a atividade.');
        $statement->bind_param('i', $activityId);
        $statement->execute();
        $activity = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($activity) $this->notifyOnce('atividade_atrasada', 'atividade', $activityId, 'Atividade em atraso', $this->activityMessage($activity, 'A atividade está com prazo vencido.'));
    }

    public function notifyCompletedActivity(int $activityId): void
    {
        $statement = $this->connection->prepare("SELECT a.titulo, a.data_limite, o.nome AS obra_nome FROM atividades a LEFT JOIN obras o ON o.id = a.obra_id WHERE a.id = ? AND a.status = 'concluida' LIMIT 1");
        if (!$statement) throw new RuntimeException('Não foi possível consultar a atividade.');
        $statement->bind_param('i', $activityId);
        $statement->execute();
        $activity = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($activity) $this->notifyOnce('atividade_concluida', 'atividade', $activityId, 'Atividade concluída', $this->activityMessage($activity, 'A atividade foi marcada como concluída.'));
    }

    public function notifyClosedCall(int $callId): void
    {
        $statement = $this->connection->prepare("SELECT c.titulo, c.prioridade, o.nome AS obra_nome FROM chamados c LEFT JOIN obras o ON o.id = c.obra_id WHERE c.id = ? AND c.status = 'fechado' LIMIT 1");
        if (!$statement) throw new RuntimeException('Não foi possível consultar o chamado.');
        $statement->bind_param('i', $callId);
        $statement->execute();
        $call = $statement->get_result()->fetch_assoc();
        $statement->close();
        if (!$call) return;
        $message = '<p>O chamado foi encerrado.</p><p><strong>Obra:</strong> ' . $this->escape($call['obra_nome'] ?? 'Geral') . '</p><p><strong>Chamado:</strong> ' . $this->escape($call['titulo']) . '</p><p><strong>Prioridade:</strong> ' . $this->escape($call['prioridade']) . '</p>';
        $this->notifyOnce('chamado_fechado', 'chamado', $callId, 'Chamado concluído', $message);
    }

    /** @param array<string,mixed> $activity */
    private function activityMessage(array $activity, string $intro): string
    {
        return '<p>' . $intro . '</p><p><strong>Obra:</strong> ' . $this->escape($activity['obra_nome'] ?? 'Geral') . '</p><p><strong>Atividade:</strong> ' . $this->escape($activity['titulo']) . '</p><p><strong>Prazo:</strong> ' . $this->escape((string) $activity['data_limite']) . '</p>';
    }

    private function notifyOnce(string $eventType, string $entityType, int $entityId, string $subject, string $message): void
    {
        $event = $this->connection->prepare('INSERT IGNORE INTO notificacoes_email (tipo_evento, entidade_tipo, entidade_id) VALUES (?, ?, ?)');
        if (!$event) throw new RuntimeException('Execute a migração de notificações de e-mail.');
        $event->bind_param('ssi', $eventType, $entityType, $entityId);
        $event->execute();
        $created = $event->affected_rows === 1;
        $event->close();
        if (!$created) return;

        $admins = $this->connection->query("SELECT DISTINCT email FROM usuarios WHERE COALESCE(role, tipo) = 'admin' AND email <> ''");
        if (!$admins) throw new RuntimeException('Não foi possível localizar os administradores.');
        $queue = $this->connection->prepare("INSERT INTO fila_emails (destinatario, assunto, mensagem_html, status, tentativas) VALUES (?, ?, ?, 'pendente', 0)");
        if (!$queue) throw new RuntimeException('A fila de e-mails não está disponível.');
        while ($admin = $admins->fetch_assoc()) {
            $email = $admin['email'];
            $queue->bind_param('sss', $email, $subject, $message);
            $queue->execute();
        }
        $queue->close();
    }

    private function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}