<?php
declare(strict_types=1);

namespace App\Domain\Activity;

final class ActivityStatus
{
    public const PENDING = 'pendente';
    public const IN_PROGRESS = 'em_andamento';
    public const COMPLETED = 'concluida';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::IN_PROGRESS, self::COMPLETED];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
