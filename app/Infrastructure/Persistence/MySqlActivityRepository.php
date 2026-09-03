<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use mysqli;
use RuntimeException;

final class MySqlActivityRepository
{
    public function __construct(private object $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function generalCalendarEvents(): array
    {
        $result = $this->connection->query('SELECT id, titulo, descricao, data_atividade, tipo, dia_semana FROM atividades WHERE obra_id IS NULL');
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row['tipo'] === 'recorrente'
                ? ['id' => 'rec_' . $row['id'], 'title' => $row['titulo'] . ' (semanal)', 'daysOfWeek' => [(int) $row['dia_semana']], 'color' => '#0f766e']
                : ['id' => (string) $row['id'], 'title' => $row['titulo'], 'start' => $row['data_atividade'], 'extendedProps' => ['descricao' => $row['descricao'], 'tipo' => $row['tipo']]];
        }
        return $events;
    }

    public function createGeneralActivity(string $title, string $description, string $date, string $type, ?int $weekDay): void
    {
        $statement = $this->prepare('INSERT INTO atividades (titulo, descricao, data_atividade, tipo, dia_semana) VALUES (?, ?, ?, ?, ?)');
        $statement->bind_param('ssssi', $title, $description, $date, $type, $weekDay);
        $this->execute($statement);
    }

    public function updateGeneralActivity(int $id, string $title, string $description, string $date): void
    {
        $statement = $this->prepare("UPDATE atividades SET titulo = ?, descricao = ?, data_atividade = ? WHERE id = ? AND tipo = 'unico' AND obra_id IS NULL");
        $statement->bind_param('sssi', $title, $description, $date, $id);
        $this->execute($statement);
    }

    public function deleteGeneralActivity(int $id): void
    {
        $statement = $this->prepare("DELETE FROM atividades WHERE id = ? AND tipo = 'unico' AND obra_id IS NULL");
        $statement->bind_param('i', $id);
        $this->execute($statement);
    }

    public function createProjectActivity(?int $projectId, string $title, string $description, string $deadline, string $status): void
    {
        $statement = $this->prepare('INSERT INTO atividades (obra_id, titulo, descricao, data_atividade, data_limite, status) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->bind_param('isssss', $projectId, $title, $description, $deadline, $deadline, $status);
        $this->execute($statement);
    }

    public function changeStatus(int $id, string $status): void
    {
        $statement = $this->prepare('UPDATE atividades SET status = ? WHERE id = ?');
        $statement->bind_param('si', $status, $id);
        $this->execute($statement);
    }

    /** @return list<array<string, mixed>> */
    public function projects(): array
    {
        return $this->rows('SELECT id, nome FROM obras ORDER BY nome ASC');
    }

    /** @return list<array<string, mixed>> */
    public function overdueActivities(string $today): array
    {
        $statement = $this->prepare("SELECT a.*, o.nome AS nome_obra FROM atividades a LEFT JOIN obras o ON a.obra_id = o.id WHERE a.data_limite < ? AND a.status != 'concluida' ORDER BY a.data_limite ASC");
        $statement->bind_param('s', $today);
        $this->execute($statement);
        return $this->statementRows($statement);
    }

    /** @return list<array<string, mixed>> */
    public function allProjectActivities(): array
    {
        return $this->rows('SELECT a.*, o.nome AS nome_obra FROM atividades a LEFT JOIN obras o ON a.obra_id = o.id WHERE a.data_limite IS NOT NULL ORDER BY a.data_limite ASC');
    }

    private function prepare(string $sql): object
    {
        $statement = $this->connection->prepare($sql);
        if (!$statement) {
            throw new RuntimeException('Não foi possível preparar a operação no banco de dados.');
        }
        return $statement;
    }

    private function execute(object $statement): void
    {
        if (!$statement->execute()) {
            throw new RuntimeException('Não foi possível concluir a operação no banco de dados.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql): array
    {
        $result = $this->connection->query($sql);
        if (!$result) {
            throw new RuntimeException('Não foi possível consultar os dados.');
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    private function statementRows(object $statement): array
    {
        $result = $statement->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
