<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

use Mirai\Domain\Menu\BowlPricing;
use Mirai\Domain\Menu\ProductFinder;

/**
 * Превращает «сырой» ввод клиента ([{id, qty, bowl?}, …]) в валидированные позиции заказа.
 *
 * Клиент присылает только id, qty и (для кальяна из витрины) slug чаши; имя/цену/линию
 * и наценку чаши сервер берёт сам (ProductFinder/BowlPricing) — подделать цену нельзя.
 * Невалидные строки молча отбрасываются (как в старом order-submit.php).
 */
final class OrderItemResolver
{
    private const MAX_QTY = 99;

    public function __construct(
        private readonly ProductFinder $products,
        private readonly BowlPricing $bowls,
    ) {}

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

            // Кальян из витрины: наценка выбранной чаши (× число шахт) + подпись.
            $price = $product->price;
            $note = null;
            $bowl = trim((string) ($row['bowl'] ?? ''));
            if ($bowl !== '') {
                $s = $this->bowls->surcharge($product->slug, $bowl);
                if ($s !== null) {
                    $price += $s['extra'] * $s['units'];
                    $note = 'Чаша: ' . $s['name'] . ($s['units'] > 1 ? ' ×' . $s['units'] : '');
                }
            }

            // В заказ пишем slug товара (стабильный снимок), а не суррогатный id.
            $resolved[] = new OrderItem(
                $product->slug,
                $product->name,
                $qty,
                $price,
                $product->line,
                $product->categorySlug,
                $note,
            );
        }

        return $resolved;
    }
}
