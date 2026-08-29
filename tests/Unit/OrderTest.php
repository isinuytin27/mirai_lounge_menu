<?php

declare(strict_types=1);

namespace Mirai\Tests\Unit;

use Mirai\Domain\Orders\Order;
use Mirai\Domain\Orders\OrderItem;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    private function order(OrderItem ...$items): Order
    {
        return new Order('ord_1', 'pos_6', 'Стол №6', Order::OPEN, array_values($items));
    }

    public function testGroupByLine(): void
    {
        $order = $this->order(
            new OrderItem('a', 'Кальян', 1, 2000, 'hookah', 'kalyan'),
            new OrderItem('b', 'Мохито', 2, 500, 'bar', 'classic'),
            new OrderItem('c', 'Брускета', 1, 750, 'kitchen', 'zakuski'),
            new OrderItem('d', 'Стейк', 1, 1500, 'kitchen', 'hot'),
        );

        $grouped = $order->groupByLine();

        self::assertCount(1, $grouped['hookah']);
        self::assertCount(1, $grouped['bar']);
        self::assertCount(2, $grouped['kitchen']);
    }

    public function testUnknownLineFallsBackToKitchen(): void
    {
        // OrderItem::fromRow нормализует неизвестную линию в kitchen.
        $item = OrderItem::fromRow(['product_id' => 'x', 'name' => 'X', 'qty' => 1, 'price' => 10, 'line' => 'weird']);
        $order = $this->order($item);

        self::assertCount(1, $order->groupByLine()['kitchen']);
    }

    public function testKitchenTextListsOnlyKitchenItems(): void
    {
        $order = $this->order(
            new OrderItem('a', 'Кальян', 1, 2000, 'hookah', 'kalyan'),
            new OrderItem('c', 'Брускета', 2, 750, 'kitchen', 'zakuski'),
        );

        $text = $order->kitchenText();

        self::assertStringContainsString('Заказ ord_1', $text);
        self::assertStringContainsString('Стол: Стол №6', $text);
        self::assertStringContainsString('• Брускета × 2', $text);
        self::assertStringNotContainsString('Кальян', $text);
    }

    public function testKitchenTextWhenNoKitchenItems(): void
    {
        $order = $this->order(new OrderItem('a', 'Кальян', 1, 2000, 'hookah', 'kalyan'));

        self::assertStringContainsString('(нет позиций кухни)', $order->kitchenText());
    }

    public function testTotal(): void
    {
        $order = $this->order(
            new OrderItem('a', 'Кальян', 1, 2000, 'hookah', 'kalyan'),
            new OrderItem('b', 'Мохито', 3, 500, 'bar', 'classic'),
        );

        self::assertSame(2000 + 1500, $order->total());
    }
}
