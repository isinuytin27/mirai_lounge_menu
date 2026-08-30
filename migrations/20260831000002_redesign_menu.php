<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Редизайн доменного меню: продуманные таблицы вместо порта из JSON 1:1.
 *
 *   menu_groups  (Кальян/Бар/Кухня/Ночное — вместо хардкода в коде)
 *     └ menu_categories (group_id, line-роутинг)
 *         └ products (суррогатный id + slug, порция/время раздельно, стоп-лист)
 *             └ product_pairings (гастропары: товар ↔ сопутствующий товар)
 *
 * Данные меню переимпортируются из data/menu.json (bin/import-json).
 * order_items.product_id — снимок (slug), FK на products не ставим.
 */
final class RedesignMenu extends AbstractMigration
{
    public function up(): void
    {
        $ts = ['timezone' => true];

        // Старые таблицы меню (порт из JSON) удаляем — пересоздаём правильно.
        $this->table('menu_products')->drop()->save();
        $this->table('menu_categories')->drop()->save();

        // --- Группы меню (плашки первого экрана) ---
        $this->table('menu_groups')
            ->addColumn('slug', 'string', ['limit' => 64])
            ->addColumn('title', 'text')
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('slug', ['unique' => true])
            ->create();

        // --- Категории ---
        $this->table('menu_categories')
            ->addColumn('slug', 'string', ['limit' => 128])
            ->addColumn('group_id', 'integer', ['null' => true])
            ->addColumn('title', 'text')
            // line — маршрут выдачи заказа (hookah|bar|kitchen), НЕ то же, что группа-UX.
            ->addColumn('line', 'string', ['limit' => 16, 'default' => 'kitchen'])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('slug', ['unique' => true])
            ->addIndex('group_id')
            ->addForeignKey('group_id', 'menu_groups', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        // --- Товары ---
        $this->table('products')
            ->addColumn('slug', 'string', ['limit' => 160])
            ->addColumn('category_id', 'integer', ['null' => true])
            ->addColumn('name', 'text')
            ->addColumn('price', 'integer', ['default' => 0])          // ₽, целые
            ->addColumn('description', 'text', ['null' => true])       // полное описание
            ->addColumn('description_short', 'text', ['null' => true]) // короткое (для карточки)
            ->addColumn('composition', 'text', ['null' => true])       // состав/ингредиенты (отдельно)
            ->addColumn('portion_value', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true])
            ->addColumn('portion_unit', 'string', ['limit' => 16, 'null' => true]) // г | мл | шт
            ->addColumn('prep_time', 'string', ['limit' => 64, 'null' => true])    // напр. «40-60 минут» (кальян)
            ->addColumn('image', 'text', ['null' => true])
            ->addColumn('visible', 'boolean', ['default' => true])     // опубликован
            ->addColumn('available', 'boolean', ['default' => true])   // в наличии (стоп-лист)
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('slug', ['unique' => true])
            ->addIndex('category_id')
            ->addIndex(['visible', 'available'])
            ->addForeignKey('category_id', 'menu_categories', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        // --- Гастропары / связки товаров = ГРАФ ---
        // Ребро направленного взвешенного типизированного графа: узлы = products,
        // ребро product_id -> paired_product_id с типом (kind) и весом (weight).
        // Адъяценси-лист — стандартное реляционное представление графа, легко обходить.
        // Взаимную пару хранить двумя рёбрами (A->B и B->A) либо запросом по обеим сторонам.
        $this->table('product_pairings')
            ->addColumn('product_id', 'integer')          // узел-источник (напр. кальян)
            ->addColumn('paired_product_id', 'integer')   // узел-цель (напр. чай)
            // тип ребра: gastro (гастропара) | upsell (допродажа) | alt (альтернатива) | …
            ->addColumn('kind', 'string', ['limit' => 24, 'default' => 'gastro'])
            // вес ребра: сила связи/приоритет (для сортировки и обхода графа)
            ->addColumn('weight', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 1])
            ->addColumn('note', 'text', ['null' => true]) // подпись пары, необяз.
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            // одно ребро данного типа между парой узлов
            ->addIndex(['product_id', 'paired_product_id', 'kind'], ['unique' => true])
            ->addIndex('product_id')
            ->addIndex('paired_product_id')
            ->addForeignKey('product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('paired_product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('product_pairings')->drop()->save();
        $this->table('products')->drop()->save();
        $this->table('menu_categories')->drop()->save();
        $this->table('menu_groups')->drop()->save();

        // Возврат к простым портовым таблицам (минимально, для отката).
        $ts = ['timezone' => true];
        $this->table('menu_categories', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('title', 'text')
            ->addColumn('line', 'string', ['limit' => 16, 'default' => 'kitchen'])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->create();
        $this->table('menu_products', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('category_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('name', 'text')
            ->addColumn('price', 'integer', ['default' => 0])
            ->addColumn('visible', 'boolean', ['default' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->create();
    }
}
