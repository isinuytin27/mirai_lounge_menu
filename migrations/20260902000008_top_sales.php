<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Хиты продаж (topsales из аддона брони) — курируемый персоналом ряд «🔥 Хиты»
 * вверху экрана меню. Позиция ссылается на товар (имя/цена/фото — из products),
 * персонал задаёт порядок, бейдж и заметку. Публично на чтение, правит только админка.
 */
final class TopSales extends AbstractMigration
{
    public function up(): void
    {
        $this->table('top_sales')
            ->addColumn('product_id', 'integer')
            ->addColumn('badge', 'text', ['null' => true])   // напр. «ХИТ», «-20%»
            ->addColumn('note', 'text', ['null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('product_id', ['unique' => true])
            ->addForeignKey('product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('top_sales')->drop()->save();
    }
}
