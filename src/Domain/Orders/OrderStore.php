<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

/**
 * Запись/чтение заказов, нужные приёму заказа. Абстракция над OrderRepository,
 * чтобы OrderSubmissionService тестировался без БД.
 */
interface OrderStore
{
    /**
     * @param list<OrderItem> $items
     * @return array{order_id:string,append:bool}
     */
    public function submit(string $tableId, string $tableCaption, array $items): array;

    public function find(string $orderId): ?Order;
}
