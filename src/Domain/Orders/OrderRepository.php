<?php

declare(strict_types=1);

namespace Mirai\Domain\Orders;

use Mirai\Infrastructure\Db\Repository;
use PDO;

/**
 * Хранилище заказов. Заменяет mirai_orders.php.
 *
 * submit() выполняется в ОДНОЙ транзакции с блокировкой открытого заказа стола
 * (SELECT ... FOR UPDATE) — это устраняет lost-update старого flock-над-JSON и
 * гарантирует, что параллельные дозаказы одного стола не потеряются.
 */
final class OrderRepository extends Repository implements OrderStore
{
    /**
     * Приём заказа: если у стола есть открытый заказ — дозаказ, иначе новый.
     *
     * @param list<OrderItem> $items уже валидированные позиции (непустой список)
     * @return array{order_id:string,append:bool}
     */
    public function submit(string $tableId, string $tableCaption, array $items): array
    {
        return $this->db->transactional(function (PDO $pdo) use ($tableId, $tableCaption, $items): array {
            $now = date('c');

            // Стол — первокласс: гарантируем его существование (FK orders.table_id).
            $this->ensureTable($pdo, $tableId, $tableCaption);

            // Блокируем открытый заказ стола, чтобы параллельный дозаказ не создал второй.
            $stmt = $pdo->prepare(
                "SELECT id FROM orders WHERE table_id = :tid AND status = 'open'
                 ORDER BY created_at NULLS LAST LIMIT 1 FOR UPDATE"
            );
            $stmt->execute(['tid' => $tableId]);
            $openId = $stmt->fetchColumn();

            if (is_string($openId) && $openId !== '') {
                $this->appendItems($pdo, $openId, $items, $now);
                $pdo->prepare('UPDATE orders SET updated_at = :now WHERE id = :id')
                    ->execute(['now' => $now, 'id' => $openId]);

                return ['order_id' => $openId, 'append' => true];
            }

            $orderId = 'ord_' . bin2hex(random_bytes(5));
            $pdo->prepare(
                "INSERT INTO orders (id, table_id, table_caption, status, created_at, updated_at)
                 VALUES (:id, :tid, :cap, 'open', :now, :now)"
            )->execute(['id' => $orderId, 'tid' => $tableId, 'cap' => $tableCaption, 'now' => $now]);

            $this->appendItems($pdo, $orderId, $items, $now);

            return ['order_id' => $orderId, 'append' => false];
        });
    }

    /** Закрыть заказ. @return bool был ли найден и закрыт открытый заказ. */
    public function close(string $orderId): bool
    {
        $now = date('c');
        $affected = $this->execute(
            "UPDATE orders SET status = 'closed', closed_at = :now, updated_at = :now
             WHERE id = :id AND status <> 'closed'",
            ['now' => $now, 'id' => $orderId]
        );

        return $affected > 0;
    }

    /**
     * Заказы для персонала, свежие сверху.
     *
     * @return list<Order>
     */
    public function listForStaff(int $limit = 100): array
    {
        $rows = $this->fetchAll(
            'SELECT id, table_id, table_caption, status, created_at, updated_at, closed_at
             FROM orders ORDER BY COALESCE(updated_at, created_at) DESC NULLS LAST LIMIT :limit',
            ['limit' => $limit]
        );

        $orders = [];
        foreach ($rows as $row) {
            $orders[] = $this->hydrate($row, $this->itemsFor((string) $row['id']));
        }

        return $orders;
    }

    public function find(string $orderId): ?Order
    {
        $row = $this->fetchOne(
            'SELECT id, table_id, table_caption, status, created_at, updated_at, closed_at
             FROM orders WHERE id = :id',
            ['id' => $orderId]
        );

        return $row === null ? null : $this->hydrate($row, $this->itemsFor($orderId));
    }

    // ---------- внутреннее ----------

    private function ensureTable(PDO $pdo, string $tableId, string $caption): void
    {
        $pdo->prepare(
            'INSERT INTO tables (id, caption, active) VALUES (:id, :cap, TRUE)
             ON CONFLICT (id) DO UPDATE SET caption = EXCLUDED.caption'
        )->execute(['id' => $tableId, 'cap' => $caption]);
    }

    /** @param list<OrderItem> $items */
    private function appendItems(PDO $pdo, string $orderId, array $items, string $now): void
    {
        // Продолжаем нумерацию sort_order после уже имеющихся позиций.
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM order_items WHERE order_id = :id');
        $maxStmt->execute(['id' => $orderId]);
        $next = ((int) $maxStmt->fetchColumn()) + 1;

        $insert = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, name, qty, price, line, category_id, note, added_at, sort_order)
             VALUES (:oid, :pid, :name, :qty, :price, :line, :cat, :note, :added, :sort)'
        );
        foreach ($items as $item) {
            $insert->execute([
                'oid' => $orderId,
                'pid' => $item->productId,
                'name' => $item->name,
                'qty' => $item->qty,
                'price' => $item->price,
                'line' => $item->line,
                'cat' => $item->categoryId,
                'note' => $item->note,
                'added' => $now,
                'sort' => $next++,
            ]);
        }
    }

    /** @return list<OrderItem> */
    private function itemsFor(string $orderId): array
    {
        $rows = $this->fetchAll(
            'SELECT product_id, name, qty, price, line, category_id, note
             FROM order_items WHERE order_id = :id ORDER BY sort_order',
            ['id' => $orderId]
        );

        return array_map(static fn (array $r): OrderItem => OrderItem::fromRow($r), $rows);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<OrderItem> $items
     */
    private function hydrate(array $row, array $items): Order
    {
        return new Order(
            (string) $row['id'],
            isset($row['table_id']) ? (string) $row['table_id'] : null,
            (string) ($row['table_caption'] ?? ''),
            (string) ($row['status'] ?? Order::OPEN),
            $items,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            isset($row['closed_at']) ? (string) $row['closed_at'] : null,
        );
    }
}
