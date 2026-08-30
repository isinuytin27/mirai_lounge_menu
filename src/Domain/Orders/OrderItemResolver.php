<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

use Mirai\Domain\Menu\ProductFinder;

/**
 * Превращает «сырой» ввод клиента ([{id, qty}, …]) в валидированные позиции заказа.
 *
 * Клиент присылает только id и qty; имя/цену/линию сервер берёт из меню (ProductFinder),
 * поэтому подделать цену/название нельзя. Невалидные строки молча отбрасываются
 * (как в старом order-submit.php).
 */
final class OrderItemResolver
{
    private const MAX_QTY = 99;

    public function __construct(private readonly ProductFinder $products) {}

    /**
     * @param mixed $rawItems ожидается list<array{id?:string,product_id?:string,qty?:int}>
     * @return list<OrderItem>
     */
    public function resolve(mixed $rawItems): array
    {
        if (!is_array($rawItems)) {
            return [];
        }

        $resolved = [];
        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = trim((string) ($row['id'] ?? $row['product_id'] ?? ''));
            $qty = (int) ($row['qty'] ?? 0);
            if ($pid === '' || $qty < 1 || $qty > self::MAX_QTY) {
                continue;
            }

            $product = $this->products->findVisibleProduct($pid);
            if ($product === null) {
                continue;
            }

            // В заказ пишем slug товара (стабильный снимок), а не суррогатный id.
            $resolved[] = new OrderItem(
                $product->slug,
                $product->name,
                $qty,
                $product->price,
                $product->line,
                $product->categorySlug,
            );
        }

        return $resolved;
    }
}
