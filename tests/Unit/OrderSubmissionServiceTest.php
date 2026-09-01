<?php

declare(strict_types=1);

namespace Mirai\Tests\Unit;

use Mirai\Domain\Menu\Product;
use Mirai\Domain\Menu\BowlPricing;
use Mirai\Domain\Menu\ProductFinder;
use Mirai\Domain\Orders\Order;
use Mirai\Domain\Orders\OrderItem;
use Mirai\Domain\Orders\OrderItemResolver;
use Mirai\Domain\Orders\OrderStore;
use Mirai\Domain\Orders\OrderSubmissionService;
use Mirai\Infrastructure\Notify\OrderNotifier;
use PHPUnit\Framework\TestCase;

final class OrderSubmissionServiceTest extends TestCase
{
    private function bowls(): BowlPricing
    {
        return new class implements BowlPricing {
            public function surcharge(string $productSlug, string $bowlSlug): ?array { return null; }
        };
    }

    private function finder(): ProductFinder
    {
        return new class implements ProductFinder {
            public function findVisibleProduct(string $productId): ?Product
            {
                return $productId === 'p1'
                    ? new Product(1, 'p1', 'zakuski', 'Брускета', 750, 'kitchen')
                    : null;
            }
        };
    }

    private function store(): OrderStore
    {
        return new class implements OrderStore {
            public ?string $lastTable = null;
            /** @var list<OrderItem> */
            public array $lastItems = [];

            public function submit(string $tableId, string $tableCaption, array $items): array
            {
                $this->lastTable = $tableId;
                $this->lastItems = $items;
                return ['order_id' => 'ord_test', 'append' => false];
            }

            public function find(string $orderId): ?Order
            {
                return new Order($orderId, 'pos_6', 'Стол №6', Order::OPEN, $this->lastItems);
            }
        };
    }

    public function testEmptyInputFailsWithEmptyItems(): void
    {
        $service = new OrderSubmissionService(
            new OrderItemResolver($this->finder(), $this->bowls()),
            $this->store(),
            $this->recordingNotifier(),
        );

        $result = $service->submit('pos_6', 'Стол №6', []);

        self::assertFalse($result->ok);
        self::assertSame('empty_items', $result->error);
    }

    public function testAllInvalidItemsFailsWithNoValidItems(): void
    {
        $service = new OrderSubmissionService(
            new OrderItemResolver($this->finder(), $this->bowls()),
            $this->store(),
            $this->recordingNotifier(),
        );

        $result = $service->submit('pos_6', 'Стол №6', [['id' => 'unknown', 'qty' => 1]]);

        self::assertFalse($result->ok);
        self::assertSame('no_valid_items', $result->error);
    }

    public function testSuccessSubmitsResolvedItemsAndNotifies(): void
    {
        $store = $this->store();
        $notifier = $this->recordingNotifier();

        $service = new OrderSubmissionService(
            new OrderItemResolver($this->finder(), $this->bowls()),
            $store,
            $notifier,
        );

        $result = $service->submit('pos_6', 'Стол №6', [['id' => 'p1', 'qty' => 2]]);

        self::assertTrue($result->ok);
        self::assertSame('ord_test', $result->orderId);
        self::assertFalse($result->append);
        self::assertTrue($result->telegramOk);          // notifier вернул true
        self::assertSame('pos_6', $store->lastTable);
        self::assertCount(1, $store->lastItems);
        self::assertSame(750, $store->lastItems[0]->price);
        self::assertSame(1, $notifier->calls);
    }

    private function recordingNotifier(): OrderNotifier
    {
        return new class implements OrderNotifier {
            public int $calls = 0;

            public function orderPlaced(Order $order, array $newItems, bool $append): bool
            {
                $this->calls++;
                return true;
            }
        };
    }
}
