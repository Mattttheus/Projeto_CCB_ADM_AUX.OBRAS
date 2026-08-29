<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use mysqli;
use RuntimeException;

final class MySqlFinancialRepository
{
    public function __construct(private mysqli $connection) {}

    public function createEntry(int $projectId, string $category, string $description, float $quantity, float $unitCost, string $date): void
    {
        $statement = $this->prepare('INSERT INTO lancamentos_financeiros (obra_id, categoria, descricao, quantidade, valor_unitario, data_lancamento) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->bind_param('issdds', $projectId, $category, $description, $quantity, $unitCost, $date);
        $this->execute($statement);
    }

    public function saveBudget(int $projectId, float $budget): void
    {
        $statement = $this->prepare('INSERT INTO orcamentos_obras (obra_id, valor_orcado) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor_orcado = VALUES(valor_orcado), atualizado_em = CURRENT_TIMESTAMP');
        $statement->bind_param('id', $projectId, $budget);
        $this->execute($statement);
    }

    /** @return list<array<string,mixed>> */
    public function projects(): array { return $this->rows('SELECT id, nome FROM obras ORDER BY nome'); }
    /** @return list<array<string,mixed>> */
    public function projectsForUser(int $userId): array
    {
        $statement = $this->prepare('SELECT DISTINCT o.id, o.nome FROM obras o INNER JOIN obra_responsaveis r ON r.obra_id = o.id WHERE r.usuario_id = ? ORDER BY o.nome');
        $statement->bind_param('i', $userId);
        $this->execute($statement);

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    /** @return list<array<string,mixed>> */
    public function overview(): array
    {
        return $this->rows("SELECT o.id, o.nome, COALESCE(b.valor_orcado, 0) AS valor_orcado, COALESCE(SUM(f.valor_unitario * f.quantidade), 0) AS total_realizado, COALESCE(SUM(CASE WHEN f.categoria = 'material' THEN f.valor_unitario * f.quantidade ELSE 0 END), 0) AS materiais, COALESCE(SUM(CASE WHEN f.categoria = 'operacional' THEN f.valor_unitario * f.quantidade ELSE 0 END), 0) AS operacionais FROM obras o LEFT JOIN orcamentos_obras b ON b.obra_id = o.id LEFT JOIN lancamentos_financeiros f ON f.obra_id = o.id GROUP BY o.id, o.nome, b.valor_orcado ORDER BY total_realizado DESC, o.nome");
    }
    /** @return list<array<string,mixed>> */
    public function overviewForUser(int $userId): array
    {
        $statement = $this->prepare("SELECT o.id, o.nome, COALESCE(b.valor_orcado, 0) AS valor_orcado, COALESCE(SUM(f.valor_unitario * f.quantidade), 0) AS total_realizado, COALESCE(SUM(CASE WHEN f.categoria = 'material' THEN f.valor_unitario * f.quantidade ELSE 0 END), 0) AS materiais, COALESCE(SUM(CASE WHEN f.categoria = 'operacional' THEN f.valor_unitario * f.quantidade ELSE 0 END), 0) AS operacionais FROM obras o INNER JOIN obra_responsaveis r ON r.obra_id = o.id LEFT JOIN orcamentos_obras b ON b.obra_id = o.id LEFT JOIN lancamentos_financeiros f ON f.obra_id = o.id WHERE r.usuario_id = ? GROUP BY o.id, o.nome, b.valor_orcado ORDER BY total_realizado DESC, o.nome");
        $statement->bind_param('i', $userId);
        $this->execute($statement);

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    /** @return list<array<string,mixed>> */
    public function categoryTotals(?int $projectId): array
    {
        $sql = 'SELECT categoria, SUM(quantidade) AS quantidade, SUM(quantidade * valor_unitario) AS total FROM lancamentos_financeiros' . ($projectId ? ' WHERE obra_id = ?' : '') . ' GROUP BY categoria ORDER BY total DESC';
        if (!$projectId) return $this->rows($sql);
        $statement = $this->prepare($sql); $statement->bind_param('i', $projectId); $this->execute($statement); return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    /** @return list<array<string,mixed>> */
    public function categoryTotalsForUser(int $userId, ?int $projectId): array
    {
        $sql = 'SELECT f.categoria, SUM(f.quantidade) AS quantidade, SUM(f.quantidade * f.valor_unitario) AS total FROM lancamentos_financeiros f INNER JOIN obra_responsaveis r ON r.obra_id = f.obra_id WHERE r.usuario_id = ?' . ($projectId ? ' AND f.obra_id = ?' : '') . ' GROUP BY f.categoria ORDER BY total DESC';
        $statement = $this->prepare($sql);
        if ($projectId) $statement->bind_param('ii', $userId, $projectId); else $statement->bind_param('i', $userId);
        $this->execute($statement);

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    /** @return list<array<string,mixed>> */
    public function entries(?int $projectId): array
    {
        $sql = 'SELECT f.*, o.nome AS nome_obra FROM lancamentos_financeiros f JOIN obras o ON o.id = f.obra_id' . ($projectId ? ' WHERE f.obra_id = ?' : '') . ' ORDER BY f.data_lancamento DESC, f.id DESC LIMIT 100';
        if (!$projectId) return $this->rows($sql);
        $statement = $this->prepare($sql); $statement->bind_param('i', $projectId); $this->execute($statement); return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    /** @return list<array<string,mixed>> */
    public function entriesForUser(int $userId, ?int $projectId): array
    {
        $sql = 'SELECT f.*, o.nome AS nome_obra FROM lancamentos_financeiros f INNER JOIN obras o ON o.id = f.obra_id INNER JOIN obra_responsaveis r ON r.obra_id = f.obra_id WHERE r.usuario_id = ?' . ($projectId ? ' AND f.obra_id = ?' : '') . ' ORDER BY f.data_lancamento DESC, f.id DESC LIMIT 100';
        $statement = $this->prepare($sql);
        if ($projectId) $statement->bind_param('ii', $userId, $projectId); else $statement->bind_param('i', $userId);
        $this->execute($statement);

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    private function prepare(string $sql): \mysqli_stmt { $stmt = $this->connection->prepare($sql); if (!$stmt) throw new RuntimeException('Estrutura financeira indisponível. Execute a migração database/migrations/20260816_financeiro.sql.'); return $stmt; }
    private function execute(\mysqli_stmt $statement): void { if (!$statement->execute()) throw new RuntimeException('Não foi possível salvar o lançamento financeiro.'); }
    /** @return list<array<string,mixed>> */ private function rows(string $sql): array { $result = $this->connection->query($sql); if (!$result) throw new RuntimeException('Estrutura financeira indisponível. Execute a migração database/migrations/20260816_financeiro.sql.'); return $result->fetch_all(MYSQLI_ASSOC); }
}