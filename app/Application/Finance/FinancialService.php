<?php
declare(strict_types=1);

namespace App\Application\Finance;

use App\Core\Validator;
use App\Domain\Finance\FinancialCategory;
use App\Infrastructure\Persistence\MySqlFinancialRepository;
use InvalidArgumentException;

final class FinancialService
{
    public function __construct(private MySqlFinancialRepository $repository) {}

    /** @param array<string,mixed> $input */
    public function register(array $input): void
    {
        $projectId = filter_var($input['obra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($input['quantidade'] ?? null, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01]]);
        $unitCost = filter_var(str_replace(',', '.', (string) ($input['valor_unitario'] ?? '')), FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
        if (!$projectId || !$quantity || $unitCost === false) throw new InvalidArgumentException('Informe obra, quantidade e valor unitário válidos.');
        $category = Validator::oneOf($input['categoria'] ?? '', array_keys(FinancialCategory::labels()), 'Categoria');
        $description = Validator::requiredText($input['descricao'] ?? '', 'a descrição', 255);
        $date = (string) ($input['data_lancamento'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new InvalidArgumentException('Informe uma data válida.');
        $this->repository->createEntry((int) $projectId, $category, $description, (float) $quantity, (float) $unitCost, $date);
    }

    /** @param array<string,mixed> $input */
    public function setBudget(array $input): void
    {
        $projectId = filter_var($input['obra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $budget = filter_var(str_replace(',', '.', (string) ($input['valor_orcado'] ?? '')), FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
        if (!$projectId || $budget === false) throw new InvalidArgumentException('Informe obra e orçamento válidos.');
        $this->repository->saveBudget((int) $projectId, (float) $budget);
    }
}
