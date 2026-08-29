<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

use Mirai\Domain\Menu\MenuLine;

/** Заказ стола. Статус open|closed; позиции сгруппированы по линиям выдачи. */
final class Order
{
    public const OPEN = 'open';
    public const CLOSED = 'closed';

    /** @param list<OrderItem> $items */
    public function __construct(
        public readonly string $id,
        public readonly ?string $tableId,
        public readonly string $tableCaption,
        public readonly string $status,
        public readonly array $items,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?string $closedAt = null,
    ) {}

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (OrderItem $i): int => $i->subtotal(), $this->items));
    }

    /**
     * Позиции, сгруппированные по линиям выдачи (для UI кухни/бара/кальяна).
     *
     * @return array{hookah:list<OrderItem>,bar:list<OrderItem>,kitchen:list<OrderItem>}
     */
    public function groupByLine(): array
    {
        $out = [MenuLine::HOOKAH => [], MenuLine::BAR => [], MenuLine::KITCHEN => []];
        foreach ($this->items as $item) {
            $line = isset($out[$item->line]) ? $item->line : MenuLine::KITCHEN;
            $out[$line][] = $item;
        }

        return $out;
    }

    /** Текст для кухни: только позиции линии kitchen. */
    public function kitchenText(): string
    {
        $lines = ["Заказ {$this->id}", "Стол: {$this->tableCaption}", ''];
        $any = false;
        foreach ($this->items as $item) {
            if ($item->line !== MenuLine::KITCHEN) {
                continue;
            }
            $any = true;
            $lines[] = "• {$item->name} × {$item->qty}";
        }

        if (!$any) {
            return "Заказ {$this->id}\nСтол: {$this->tableCaption}\n\n(нет позиций кухни)";
        }

        return implode("\n", $lines);
    }
}
