<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Db;

use Mirai\Infrastructure\Config\Config;
use PDO;
use Throwable;

/**
 * Единая точка подключения к БД. Ленивое соединение, разделяемый PDO, транзакции.
 *
 * transactional() держит транзакцию на весь read-modify-write — это устраняет
 * гонки/lost-update старого json_store (LOCK_EX только на миг записи).
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config) {}

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $db = $this->config->db();
            $this->pdo = new PDO($db['dsn'], $db['user'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return $this->pdo;
    }

    /**
     * Выполняет $fn внутри транзакции. Коммит при успехе, откат при исключении.
     * Вложенные вызовы переиспользуют уже открытую транзакцию (без savepoint-ов).
     *
     * @template T
     * @param callable(PDO):T $fn
     * @return T
     */
    public function transactional(callable $fn): mixed
    {
        $pdo = $this->pdo();

        if ($pdo->inTransaction()) {
            return $fn($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            // Откатываем; если транзакции уже нет (редко) — глотаем только ошибку отката,
            // исходное исключение $e всегда пробрасываем наверх.
            try {
                $pdo->rollBack();
            } catch (\PDOException) {
                // нет активной транзакции — нечего откатывать
            }
            throw $e;
        }
    }
}
