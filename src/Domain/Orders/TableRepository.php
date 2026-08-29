<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

use Mirai\Infrastructure\Db\Repository;

/** Чтение реестра столов (таблица tables). */
final class TableRepository extends Repository implements TableRegistry
{
    public function activeExists(string $tableId): bool
    {
        $row = $this->fetchOne(
            'SELECT 1 AS ok FROM tables WHERE id = :id AND active = TRUE',
            ['id' => $tableId]
        );

        return $row !== null;
    }

    public function captionOf(string $tableId): ?string
    {
        $row = $this->fetchOne(
            'SELECT caption FROM tables WHERE id = :id',
            ['id' => $tableId]
        );

        if ($row === null) {
            return null;
        }

        return isset($row['caption']) ? (string) $row['caption'] : null;
    }
}
