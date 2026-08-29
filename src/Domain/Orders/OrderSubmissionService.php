<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

use Mirai\Infrastructure\Notify\OrderNotifier;

/**
 * Приём заказа: резолв позиций против меню → запись (транзакция) → уведомление.
 * Порт логики public/api/order-submit.php, отделённый от HTTP (тестируемый).
 */
final class OrderSubmissionService
{
    public function __construct(
        private readonly OrderItemResolver $resolver,
        private readonly OrderStore $orders,
        private readonly OrderNotifier $notifier,
    ) {}

    /**
     * @param mixed $rawItems сырой ввод клиента (list<{id,qty}>)
     */
    public function submit(string $tableId, string $tableCaption, mixed $rawItems): OrderSubmissionResult
    {
        if (!is_array($rawItems) || $rawItems === []) {
            return OrderSubmissionResult::failure('empty_items');
        }

        $items = $this->resolver->resolve($rawItems);
        if ($items === []) {
            return OrderSubmissionResult::failure('no_valid_items');
        }

        $meta = $this->orders->submit($tableId, $tableCaption, $items);
        $order = $this->orders->find($meta['order_id']);

        // best-effort уведомление; провал не ломает приём заказа.
        $telegramOk = false;
        if ($order !== null) {
            $telegramOk = $this->notifier->orderPlaced($order, $items, $meta['append']);
        }

        return OrderSubmissionResult::success($meta['order_id'], $meta['append'], $telegramOk);
    }
}
