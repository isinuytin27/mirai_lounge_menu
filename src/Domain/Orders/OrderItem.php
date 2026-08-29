<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

use Mirai\Domain\Menu\MenuLine;

/** Позиция заказа (снимок продукта на момент заказа: имя/цена/линия фиксируются). */
final class OrderItem
{
    public function __construct(
        public readonly string $productId,
        public readonly string $name,
        public readonly int $qty,
        public readonly int $price,
        public readonly string $line,
        public readonly string $categoryId,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $line = (string) ($row['line'] ?? MenuLine::KITCHEN);
        if (!MenuLine::isValid($line)) {
            $line = MenuLine::KITCHEN;
        }

        return new self(
            (string) ($row['product_id'] ?? ''),
            (string) ($row['name'] ?? ''),
            (int) ($row['qty'] ?? 0),
            (int) ($row['price'] ?? 0),
            $line,
            (string) ($row['category_id'] ?? ''),
        );
    }

    /** @return array{product_id:string,name:string,qty:int,price:int,line:string,category_id:string} */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'name' => $this->name,
            'qty' => $this->qty,
            'price' => $this->price,
            'line' => $this->line,
            'category_id' => $this->categoryId,
        ];
    }

    public function subtotal(): int
    {
        return $this->price * $this->qty;
    }
}
