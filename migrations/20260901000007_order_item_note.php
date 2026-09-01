<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Модификатор позиции заказа (note) — напр. выбранная чаша для кальяна из витрины
 * («Чаша: Грейпфрут ×2»). Цена позиции уже включает наценку чаши (считает сервер).
 */
final class OrderItemNote extends AbstractMigration
{
    public function up(): void
    {
        $this->table('order_items')
            ->addColumn('note', 'text', ['null' => true])
            ->update();
    }

    public function down(): void
    {
        $this->table('order_items')->removeColumn('note')->update();
    }
}
