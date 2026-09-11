<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use mysqli;
use RuntimeException;

final class MySqlDashboardRepository
{
    public function __construct(private mysqli $connection) {}

    public function metrics(string $today): array
    {
        $totalActivities = $this->count('SELECT COUNT(*) AS total FROM atividades');
        $completedActivities = $this->count("SELECT COUNT(*) AS total FROM atividades WHERE status = 'concluida'");
        $delayedActivities = $this->countPrepared(
            "SELECT COUNT(*) AS total FROM atividades WHERE data_limite < ? AND status <> 'concluida'",
            's',
            $today,
        );
        $activitiesInProgress = $this->count("SELECT COUNT(*) AS total FROM atividades WHERE status = 'em_andamento'");
        $financeAvailable = $this->tableExists('lancamentos_financeiros') && $this->tableExists('orcamentos_obras');

        $totalBudget = 0.0;
        $totalFinancial = 0.0;
        $totalMaterials = 0.0;
        $totalOperational = 0.0;

        if ($financeAvailable) {
            $row = $this->fetchOne("SELECT
                COALESCE((SELECT SUM(valor_orcado) FROM orcamentos_obras), 0) AS orcamento,
                COALESCE(SUM(quantidade * valor_unitario), 0) AS realizado,
                COALESCE(SUM(CASE WHEN categoria = 'material' THEN quantidade * valor_unitario ELSE 0 END), 0) AS materiais,
                COALESCE(SUM(CASE WHEN categoria = 'operacional' THEN quantidade * valor_unitario ELSE 0 END), 0) AS operacional
                FROM lancamentos_financeiros");
            $totalFinancial = (float) ($row['realizado'] ?? 0);
            $totalBudget = (float) ($row['orcamento'] ?? 0);
            $totalMaterials = (float) ($row['materiais'] ?? 0);
            $totalOperational = (float) ($row['operacional'] ?? 0);
        } else {
            $totalFinancial = $this->sum('SELECT COALESCE(SUM(valor * quantidade), 0) AS total FROM compras');
        }

        return [
            'totalObras' => $this->count('SELECT COUNT(*) AS total FROM obras'),
            'totalAtividades' => $totalActivities,
            'atividadesConcluidas' => $completedActivities,
            'atividadesAtrasadas' => $delayedActivities,
            'atividadesAndamento' => $activitiesInProgress,
            'totalDocumentos' => $this->count('SELECT COUNT(*) AS total FROM documentos_obras'),
            'totalChamados' => $this->count("SELECT COUNT(*) AS total FROM chamados WHERE status <> 'fechado'"),
            'chamadosUrgentes' => $this->count("SELECT COUNT(*) AS total FROM chamados WHERE prioridade = 'vermelho' AND status <> 'fechado'"),
            'financeAvailable' => $financeAvailable,
            'totalOrcamento' => $totalBudget,
            'totalFinanceiro' => $totalFinancial,
            'totalMateriaisFinanceiro' => $totalMaterials,
            'totalOperacionalFinanceiro' => $totalOperational,
        ];
    }

    public function projects(): array
    {
        return $this->fetchAll('SELECT id, nome FROM obras ORDER BY nome ASC');
    }

    public function financialOverview(): array
    {
        if ($this->tableExists('lancamentos_financeiros') && $this->tableExists('orcamentos_obras')) {
            return $this->fetchAll("
                SELECT o.id, o.nome, COALESCE(b.valor_orcado, 0) AS valor_orcado,
                       COALESCE(f.total_obra, 0) AS total_obra
                FROM obras o
                LEFT JOIN orcamentos_obras b ON b.obra_id = o.id
                LEFT JOIN (SELECT obra_id, SUM(quantidade * valor_unitario) AS total_obra FROM lancamentos_financeiros GROUP BY obra_id) f ON f.obra_id = o.id
                ORDER BY total_obra DESC, o.nome ASC LIMIT 8
            ");
        }

        return $this->fetchAll("
            SELECT
                o.id,
                o.nome,
                0 AS valor_orcado,
                COALESCE(SUM(c.valor * c.quantidade), 0) AS total_obra
            FROM obras o
            LEFT JOIN compras c ON c.obra_id = o.id
            GROUP BY o.id, o.nome
            ORDER BY total_obra DESC, o.nome ASC
            LIMIT 8
        ");
    }

    public function upcomingActivities(string $today): array
    {
        return $this->fetchAllPrepared(
            "SELECT
                a.id,
                a.titulo,
                a.data_limite,
                a.status,
                o.nome AS nome_obra
            FROM atividades a
            LEFT JOIN obras o ON o.id = a.obra_id
            WHERE a.status <> 'concluida'
            ORDER BY
                CASE WHEN a.data_limite < ? THEN 0 ELSE 1 END,
                a.data_limite ASC
            LIMIT 8",
            's',
            $today,
        );
    }

    public function recentCalls(): array
    {
        return $this->fetchAll("
            SELECT
                c.id,
                c.titulo,
                c.prioridade,
                c.status,
                COALESCE(u.nome, 'Desconhecido') AS solicitante,
                c.data_abertura
            FROM chamados c
            LEFT JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.status <> 'fechado'
            ORDER BY
                CASE c.prioridade
                    WHEN 'vermelho' THEN 1
                    WHEN 'amarelo' THEN 2
                    ELSE 3
                END,
                c.data_abertura DESC
            LIMIT 6
        ");
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-z_]+$/i', $table)) {
            throw new RuntimeException('Nome de tabela inválido.');
        }

        $statement = $this->prepare("SHOW TABLES LIKE ?");
        $statement->bind_param('s', $table);
        $statement->execute();
        $result = $statement->get_result();
        $exists = $result->num_rows > 0;
        $statement->close();

        return $exists;
    }

    private function count(string $sql): int
    {
        $row = $this->fetchOne($sql);

        return (int) ($row['total'] ?? 0);
    }

    private function countPrepared(string $sql, string $types, mixed ...$params): int
    {
        $row = $this->fetchOnePrepared($sql, $types, ...$params);

        return (int) ($row['total'] ?? 0);
    }

    private function sum(string $sql): float
    {
        $row = $this->fetchOne($sql);

        return (float) ($row['total'] ?? 0);
    }

    private function fetchOne(string $sql): array
    {
        $result = $this->connection->query($sql);
        if (!$result) {
            throw new RuntimeException('Não foi possível carregar os dados do painel.');
        }

        return $result->fetch_assoc() ?: [];
    }

    private function fetchOnePrepared(string $sql, string $types, mixed ...$params): array
    {
        $statement = $this->prepare($sql);
        $statement->bind_param($types, ...$params);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result->fetch_assoc() ?: [];
        $statement->close();

        return $row;
    }

    private function fetchAll(string $sql): array
    {
        $result = $this->connection->query($sql);
        if (!$result) {
            throw new RuntimeException('Não foi possível carregar os dados do painel.');
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function fetchAllPrepared(string $sql, string $types, mixed ...$params): array
    {
        $statement = $this->prepare($sql);
        $statement->bind_param($types, ...$params);
        $statement->execute();
        $result = $statement->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        return $rows;
    }

    private function prepare(string $sql): \mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);
        if (!$statement) {
            throw new RuntimeException('Não foi possível preparar os dados do painel.');
        }

        return $statement;
    }
}
