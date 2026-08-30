<?php

declare(strict_types=1);

namespace Mirai\Tests\Unit;

use Mirai\Domain\Menu\Product;
use Mirai\Domain\Menu\ProductFinder;
use Mirai\Domain\Orders\OrderItemResolver;
use PHPUnit\Framework\TestCase;

final class OrderItemResolverTest extends TestCase
{
    private function finder(): ProductFinder
    {
        return new class implements ProductFinder {
            public function findVisibleProduct(string $productId): ?Product
            {
                return match ($productId) {
                    'p_hookah' => new Product(1, 'p_hookah', 'kalyan', 'Кальян', 2000, 'hookah'),
                    'p_food' => new Product(2, 'p_food', 'zakuski', 'Брускета', 750, 'kitchen'),
                    default => null,
                };
            }
        };
    }

    public function testResolvesValidItemsAndTakesPriceFromMenu(): void
    {
        $resolver = new OrderItemResolver($this->finder());

        // Клиент пытается подсунуть свою цену — она игнорируется.
        $items = $resolver->resolve([
            ['id' => 'p_hookah', 'qty' => 2, 'price' => 1],
            ['id' => 'p_food', 'qty' => 1],
        ]);

        self::assertCount(2, $items);
        self::assertSame(2000, $items[0]->price);   // из меню, не из ввода
        self::assertSame('hookah', $items[0]->line);
        self::assertSame(2, $items[0]->qty);
        self::assertSame('Брускета', $items[1]->name);
    }

    public function testDropsUnknownProductAndBadQty(): void
    {
        $resolver = new OrderItemResolver($this->finder());

        $items = $resolver->resolve([
            ['id' => 'nope', 'qty' => 1],       // нет в меню
            ['id' => 'p_food', 'qty' => 0],     // qty < 1
            ['id' => 'p_food', 'qty' => 100],   // qty > 99
            ['id' => '', 'qty' => 1],           // пустой id
            ['id' => 'p_food', 'qty' => 3],     // ок
        ]);

        self::assertCount(1, $items);
        self::assertSame('p_food', $items[0]->productId);
        self::assertSame(3, $items[0]->qty);
    }

    public function testAcceptsProductIdAlias(): void
    {
        $resolver = new OrderItemResolver($this->finder());
        $items = $resolver->resolve([['product_id' => 'p_food', 'qty' => 1]]);

        self::assertCount(1, $items);
    }

    public function testNonArrayInputYieldsEmpty(): void
    {
        $resolver = new OrderItemResolver($this->finder());

        self::assertSame([], $resolver->resolve('nope'));
        self::assertSame([], $resolver->resolve([]));
    }
}
