<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Db;

use PDO;

/**
 * Базовый репозиторий: тонкие хелперы поверх PDO. Доменные репозитории наследуются
 * и добавляют свои запросы. ORM намеренно нет — запросы явные и читаемые.
 */
abstract class Repository
{
    public function __construct(protected readonly Database $db) {}

    protected function pdo(): PDO
    {
        return $this->db->pdo();
    }

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        /** @var list<array<string,mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        /** @var array<string,mixed>|false $row */
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string,mixed> $params
     * @return int число затронутых строк
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }
}
