<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use mysqli;
use RuntimeException;

final class MySqlReportRepository
{
    public function __construct(private object $connection) {}

    /** @return list<array<string,mixed>> */
    public function projectsForUser(int $userId, bool $hasFullAccess): array
    {
        if ($hasFullAccess) {
            return $this->rows('SELECT id, nome FROM obras ORDER BY nome');
        }

        $statement = $this->prepare('SELECT DISTINCT o.id, o.nome FROM obras o INNER JOIN obra_responsaveis r ON r.obra_id = o.id WHERE r.usuario_id = ? ORDER BY o.nome');
        $statement->bind_param('i', $userId);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array{title:string,columns:list<string>,rows:list<list<string>>} */
    public function report(int $projectId, string $type): array
    {
        $project = $this->project($projectId);
        return match ($type) {
            'orcamento' => $this->budgetReport($projectId, $project['nome']),
            'financeiro' => $this->financialReport($projectId, $project['nome']),
            'atividades' => $this->activitiesReport($projectId, $project['nome']),
            'compras' => $this->purchasesReport($projectId, $project['nome']),
            default => throw new RuntimeException('Tipo de relatório inválido.'),
        };
    }

    /** @return array<string,mixed> */
    private function project(int $projectId): array
    {
        $statement = $this->prepare('SELECT id, nome FROM obras WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $projectId);
        $statement->execute();
        $project = $statement->get_result()->fetch_assoc();
        if (!$project) throw new RuntimeException('Obra não encontrada.');
        return $project;
    }

    /** @return array{title:string,columns:list<string>,rows:list<list<string>>} */
    private function budgetReport(int $projectId, string $projectName): array
    {
        $statement = $this->prepare("SELECT COALESCE(b.valor_orcado, 0) AS orcado, COALESCE(SUM(f.quantidade * f.valor_unitario), 0) AS realizado FROM obras o LEFT JOIN orcamentos_obras b ON b.obra_id = o.id LEFT JOIN lancamentos_financeiros f ON f.obra_id = o.id WHERE o.id = ? GROUP BY b.valor_orcado");
        $statement->bind_param('i', $projectId);
        $statement->execute();
        $values = $statement->get_result()->fetch_assoc() ?: ['orcado' => 0, 'realizado' => 0];
        $budget = (float) $values['orcado'];
        $spent = (float) $values['realizado'];

        return ['title' => 'Pedido de orçamento - ' . $projectName, 'columns' => ['Item', 'Valor'], 'rows' => [
            ['Orçamento aprovado', $this->money($budget)],
            ['Consumo já registrado', $this->money($spent)],
            ['Saldo previsto', $this->money($budget - $spent)],
        ]];
    }

    /** @return array{title:string,columns:list<string>,rows:list<list<string>>} */
    private function financialReport(int $projectId, string $projectName): array
    {
        $statement = $this->prepare('SELECT categoria, descricao, quantidade, valor_unitario, data_lancamento FROM lancamentos_financeiros WHERE obra_id = ? ORDER BY data_lancamento DESC, id DESC');
        $statement->bind_param('i', $projectId);
        $statement->execute();
        $rows = [];
        while ($entry = $statement->get_result()->fetch_assoc()) {
            $rows[] = [$entry['data_lancamento'], $entry['categoria'], $entry['descricao'], number_format((float) $entry['quantidade'], 2, ',', '.'), $this->money((float) $entry['quantidade'] * (float) $entry['valor_unitario'])];
        }
        return ['title' => 'Relatório financeiro - ' . $projectName, 'columns' => ['Data', 'Categoria', 'Descrição', 'Qtd.', 'Total'], 'rows' => $rows];
    }

    /** @return array{title:string,columns:list<string>,rows:list<list<string>>} */
    private function activitiesReport(int $projectId, string $projectName): array
    {
        $statement = $this->prepare('SELECT titulo, descricao, data_limite, status FROM atividades WHERE obra_id = ? ORDER BY data_limite ASC, id ASC');
        $statement->bind_param('i', $projectId);
        $statement->execute();
        $rows = [];
        while ($activity = $statement->get_result()->fetch_assoc()) {
            $rows[] = [$activity['data_limite'] ?: '-', $activity['status'], $activity['titulo'], $activity['descricao'] ?: '-'];
        }
        return ['title' => 'Relatório de atividades - ' . $projectName, 'columns' => ['Prazo', 'Status', 'Atividade', 'Descrição'], 'rows' => $rows];
    }

    /** @return array{title:string,columns:list<string>,rows:list<list<string>>} */
    private function purchasesReport(int $projectId, string $projectName): array
    {
        $statement = $this->prepare('SELECT tipo, descricao, quantidade, valor, data_compra FROM compras WHERE obra_id = ? ORDER BY data_compra DESC, id DESC');
        $statement->bind_param('i', $projectId);
        $statement->execute();
        $rows = [];
        while ($purchase = $statement->get_result()->fetch_assoc()) {
            $rows[] = [$purchase['data_compra'], $purchase['tipo'], $purchase['descricao'], (string) $purchase['quantidade'], $this->money((float) $purchase['quantidade'] * (float) $purchase['valor'])];
        }
        return ['title' => 'Relatório de compras - ' . $projectName, 'columns' => ['Data', 'Tipo', 'Descrição', 'Qtd.', 'Total'], 'rows' => $rows];
    }

    private function money(float $value): string { return 'R$ ' . number_format($value, 2, ',', '.'); }
    private function prepare(string $sql): object { $statement = $this->connection->prepare($sql); if (!$statement) throw new RuntimeException('Não foi possível preparar o relatório.'); return $statement; }
    /** @return list<array<string,mixed>> */
    private function rows(string $sql): array { $result = $this->connection->query($sql); if (!$result) throw new RuntimeException('Não foi possível consultar os relatórios.'); return $result->fetch_all(MYSQLI_ASSOC); }
}