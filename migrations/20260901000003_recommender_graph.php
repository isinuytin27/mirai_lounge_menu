<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Гастропары как ассоциативный движок (из аддона mirai-booking, menu-association-model.json)
 * вместо ручного графа product_pairings.
 *
 * Идея: товар несёт только категорию (rec_category) и теги (rec_tags), а рекомендации
 * считаются на графах категория↔категория (rec_category_pairs) и тег↔тег (rec_tag_pairs)
 * + role-правила из rec_config. Новый товар сразу участвует в рекомендациях — ручных
 * связок заводить не нужно.
 *
 * Справочник заполняется из resources/menu-association-model.json (bin/import-recommender).
 */
final class RecommenderGraph extends AbstractMigration
{
    public function up(): void
    {
        $ts = ['timezone' => true];

        // Ручной граф гастропар больше не нужен.
        $this->table('product_pairings')->drop()->save();

        // Товар: категория-рекомендатора берётся из menu_categories.rec_category,
        // rec_tags на товаре — необязательное переопределение тегов категории.
        $this->table('menu_categories')
            ->addColumn('rec_category', 'string', ['limit' => 40, 'null' => true])
            ->update();
        $this->table('products')
            ->addColumn('rec_tags', 'jsonb', ['null' => true]) // null => наследует default_tags категории
            ->update();

        // --- Справочник тегов (вкус/роль/крепость/темп/контекст) ---
        $this->table('rec_tags', ['id' => false, 'primary_key' => ['slug']])
            ->addColumn('slug', 'string', ['limit' => 40])
            ->addColumn('ru', 'text')
            ->addColumn('tag_group', 'string', ['limit' => 24]) // flavor|role|strength|temp|context
            ->addColumn('color', 'string', ['limit' => 16, 'null' => true])
            ->create();

        // --- Справочник категорий-рекомендатора (со своими тегами по умолчанию и маржой) ---
        $this->table('rec_categories', ['id' => false, 'primary_key' => ['slug']])
            ->addColumn('slug', 'string', ['limit' => 40])
            ->addColumn('ru', 'text')
            ->addColumn('cat_group', 'string', ['limit' => 24]) // hookah|food|cocktail|spirit|beer|tea|soft
            ->addColumn('color', 'string', ['limit' => 16, 'null' => true])
            ->addColumn('default_tags', 'jsonb', ['default' => '[]'])
            ->addColumn('margin', 'decimal', ['precision' => 4, 'scale' => 2, 'default' => 0])
            ->create();

        // --- Граф категория↔категория (неориентированный, вес = сила пары) ---
        $this->table('rec_category_pairs', ['id' => false, 'primary_key' => ['a', 'b']])
            ->addColumn('a', 'string', ['limit' => 40])
            ->addColumn('b', 'string', ['limit' => 40])
            ->addColumn('weight', 'decimal', ['precision' => 4, 'scale' => 3, 'default' => 0])
            ->create();

        // --- Граф тег↔тег (гармония вкусов) ---
        $this->table('rec_tag_pairs', ['id' => false, 'primary_key' => ['a', 'b']])
            ->addColumn('a', 'string', ['limit' => 40])
            ->addColumn('b', 'string', ['limit' => 40])
            ->addColumn('weight', 'decimal', ['precision' => 4, 'scale' => 3, 'default' => 0])
            ->create();

        // --- Конфиг рекомендатора (веса, role_rules, food/drink-группы, output) — одна строка ---
        $this->table('rec_config')
            ->addColumn('data', 'jsonb')
            ->addColumn('updated_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }

    public function down(): void
    {
        $this->table('rec_config')->drop()->save();
        $this->table('rec_tag_pairs')->drop()->save();
        $this->table('rec_category_pairs')->drop()->save();
        $this->table('rec_categories')->drop()->save();
        $this->table('rec_tags')->drop()->save();
        $this->table('products')->removeColumn('rec_tags')->update();
        $this->table('menu_categories')->removeColumn('rec_category')->update();

        // Восстановить ручной граф гастропар (минимально, для отката).
        $ts = ['timezone' => true];
        $this->table('product_pairings')
            ->addColumn('product_id', 'integer')
            ->addColumn('paired_product_id', 'integer')
            ->addColumn('kind', 'string', ['limit' => 24, 'default' => 'gastro'])
            ->addColumn('weight', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 1])
            ->addColumn('note', 'text', ['null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['product_id', 'paired_product_id', 'kind'], ['unique' => true])
            ->addForeignKey('product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('paired_product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
