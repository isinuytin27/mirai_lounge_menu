<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

/**
 * Реестр столов. Стол — первокласс (таблица tables), больше не элемент галереи.
 * Абстракция, чтобы table-session middleware тестировался без БД.
 */
interface TableRegistry
{
    /** Существует ли активный стол с таким id. */
    public function activeExists(string $tableId): bool;

    /** Подпись стола (или null, если стол не найден). */
    public function captionOf(string $tableId): ?string;
}
