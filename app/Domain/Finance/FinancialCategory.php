<?php
declare(strict_types=1);

namespace App\Domain\Finance;

final class FinancialCategory
{
    public const MATERIAL = 'material';
    public const OPERATIONAL = 'operacional';
    public const SERVICE = 'servico';
    public const EQUIPMENT = 'equipamento';
    public const PRODUCT = 'produto';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [self::MATERIAL => 'Materiais', self::OPERATIONAL => 'Custos operacionais', self::SERVICE => 'Serviços', self::EQUIPMENT => 'Equipamentos', self::PRODUCT => 'Produtos'];
    }
}
