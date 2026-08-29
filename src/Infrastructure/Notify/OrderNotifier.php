<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Notify;

use Mirai\Domain\Orders\Order;
use Mirai\Domain\Orders\OrderItem;

/**
 * Уведомление о заказе (Telegram/WebPush). Приём заказа зависит от этого интерфейса,
 * а не от конкретного транспорта — реальные реализации появятся в срезе «Уведомления».
 */
interface OrderNotifier
{
    /**
     * @param list<OrderItem> $newItems только что добавленные позиции
     * @param bool $append true = дозаказ к открытому заказу, false = новый заказ
     * @return bool успешность (для поля telegram_ok в ответе); best-effort
     */
    public function orderPlaced(Order $order, array $newItems, bool $append): bool;
}
