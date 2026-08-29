<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

/** Результат приёма заказа. Коды ошибок совпадают со старым API (empty_items/no_valid_items). */
final class OrderSubmissionResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $orderId = null,
        public readonly bool $append = false,
        public readonly bool $telegramOk = false,
        public readonly ?string $error = null,
    ) {}

    public static function success(string $orderId, bool $append, bool $telegramOk): self
    {
        return new self(true, $orderId, $append, $telegramOk);
    }

    public static function failure(string $error): self
    {
        return new self(false, error: $error);
    }
}
