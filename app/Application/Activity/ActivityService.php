<?php
declare(strict_types=1);

namespace App\Application\Activity;

use App\Domain\Activity\ActivityStatus;
use App\Infrastructure\Persistence\MySqlActivityRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class ActivityService
{
    public function __construct(private MySqlActivityRepository $activities)
    {
    }

    /** @param array<string, mixed> $input */
    public function createProjectActivity(array $input): void
    {
        $title = $this->requiredText($input['titulo'] ?? null, 'O título é obrigatório.');
        $deadline = $this->validDate($input['data_limite'] ?? null);
        $status = (string) ($input['status'] ?? ActivityStatus::PENDING);

        if (!ActivityStatus::isValid($status)) {
            throw new InvalidArgumentException('Status de atividade inválido.');
        }

        $projectId = filter_var($input['obra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $this->activities->createProjectActivity($projectId, $title, trim((string) ($input['descricao'] ?? '')), $deadline, $status);
    }

    /** @param array<string, mixed> $input */
    public function saveGeneralActivity(array $input): void
    {
        $title = $this->requiredText($input['titulo'] ?? null, 'O título é obrigatório.');
        $date = $this->validDate($input['data'] ?? null);
        $type = ($input['tipo'] ?? '') === 'recorrente' ? 'recorrente' : 'unico';
        $description = trim((string) ($input['descricao'] ?? ''));
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id) {
            $this->activities->updateGeneralActivity((int) $id, $title, $description, $date);
            return;
        }

        $weekDay = $type === 'recorrente' ? (int) (new DateTimeImmutable($date))->format('w') : null;
        $this->activities->createGeneralActivity($title, $description, $date, $type, $weekDay);
    }

    public function changeStatus(int $activityId, string $status): void
    {
        if ($activityId < 1 || !ActivityStatus::isValid($status)) {
            throw new InvalidArgumentException('Dados inválidos para atualização de status.');
        }
        $this->activities->changeStatus($activityId, $status);
    }

    private function requiredText(mixed $value, string $message): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            throw new InvalidArgumentException($message);
        }
        return $text;
    }

    private function validDate(mixed $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            throw new InvalidArgumentException('Informe uma data válida.');
        }
        return $date->format('Y-m-d');
    }
}
