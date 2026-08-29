<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Application\Notification\AdminNotificationService;

$result = $conn->query("SELECT id FROM atividades WHERE data_limite < CURDATE() AND status <> 'concluida'");
if (!$result) {
    error_log('Falha ao consultar atividades atrasadas: ' . $conn->error);
    exit(1);
}

$notifications = new AdminNotificationService($conn);
while ($activity = $result->fetch_assoc()) {
    try {
        $notifications->notifyOverdueActivity((int) $activity['id']);
    } catch (Throwable $exception) {
        error_log('Falha ao notificar atividade atrasada: ' . $exception->getMessage());
    }
}