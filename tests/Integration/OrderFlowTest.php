<?php

declare(strict_types=1);

namespace Mirai\Tests\Integration;

use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Orders\OrderRepository;
use Mirai\Domain\Orders\OrderSubmissionService;
use Mirai\Infrastructure\Db\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * End-to-end против реального Postgres. Требует поднятого postgres + применённых
 * миграций + импортированных данных (см. tests/Integration/README.md).
 * Пропускается, если БД недоступна.
 */
final class OrderFlowTest extends TestCase
{
    private ContainerInterface $c;
    private string $tableId = 'itest_table';

    protected function setUp(): void
    {
        $this->c = require dirname(__DIR__, 2) . '/src/bootstrap.php';
        try {
            $this->c->get(Database::class)->pdo()->query('SELECT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Postgres недоступен: ' . $e->getMessage());
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    public function testMenuLoadsFromDb(): void
    {
        $menu = $this->c->get(MenuRepository::class)->visibleMenu();

        self::assertNotEmpty($menu, 'меню пусто — импортированы ли данные?');
        // у каждой группы есть категория и хотя бы один продукт
        foreach ($menu as $group) {
            self::assertNotEmpty($group['products']);
            self::assertContains($group['category']->line, ['hookah', 'bar', 'kitchen']);
        }
    }

    public function testOrderSubmitAppendAndClose(): void
    {
        $menu = $this->c->get(MenuRepository::class);
        $first = $menu->visibleMenu()[0]['products'][0];

        $svc = $this->c->get(OrderSubmissionService::class);
        $repo = $this->c->get(OrderRepository::class);

        // Новый заказ
        $r1 = $svc->submit($this->tableId, 'Integration', [['id' => $first->id, 'qty' => 2]]);
        self::assertTrue($r1->ok);
        self::assertFalse($r1->append);

        // Дозаказ к тому же столу -> тот же заказ
        $r2 = $svc->submit($this->tableId, 'Integration', [['id' => $first->id, 'qty' => 1]]);
        self::assertTrue($r2->ok);
        self::assertTrue($r2->append);
        self::assertSame($r1->orderId, $r2->orderId);

        // Позиции и сумма
        $order = $repo->find($r1->orderId);
        self::assertNotNull($order);
        self::assertCount(2, $order->items);
        self::assertSame($first->price * 3, $order->total());

        // Закрытие
        self::assertTrue($repo->close($r1->orderId));
        self::assertFalse($repo->close($r1->orderId)); // повторное закрытие — уже закрыт
        self::assertSame('closed', $repo->find($r1->orderId)->status);
    }

    private function cleanup(): void
    {
        $pdo = $this->c->get(Database::class)->pdo();
        $pdo->prepare('DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE table_id = ?)')
            ->execute([$this->tableId]);
        $pdo->prepare('DELETE FROM orders WHERE table_id = ?')->execute([$this->tableId]);
        $pdo->prepare('DELETE FROM tables WHERE id = ?')->execute([$this->tableId]);
    }
}
