<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Notify;

use Mirai\Domain\Orders\Order;

/**
 * Заглушка уведомлений: ничего не отправляет. Дефолт до переноса Telegram/WebPush
 * (срез «Уведомления»). Позволяет приёму заказа работать и тестироваться без транспорта.
 */
final class NullOrderNotifier implements OrderNotifier
{
    public function orderPlaced(Order $order, array $newItems, bool $append): bool
    {
        return false;
    }
}
