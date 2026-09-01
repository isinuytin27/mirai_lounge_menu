<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Витрина кальянов (design_handoff_vitrina_kalyanov) — премиальный экран выбора кальяна:
 * карусель кальянов + отдельный трек чаш (композит на «шахты») + расчёт цены + напитки.
 *
 *   hookah_showcase — витринные поля кальяна (изображение, натуральные размеры, anchors
 *                     шахт, модель) поверх products (цена/имя/описание/время — из products)
 *   hookah_bowls    — чаши: изображение/масштаб/наценка (цена = кальян + наценка × шахты)
 *   hookah_drinks   — витринный ряд напитков (фото + имя), без ценовой логики
 *
 * Данные засеиваются bin/import-vitrina (идемпотентно). Редактор — в админке.
 */
final class HookahShowcase extends AbstractMigration
{
    public function up(): void
    {
        // --- Витринные поля кальяна (1:1 к товару категории kalyan) ---
        $this->table('hookah_showcase')
            ->addColumn('product_id', 'integer')
            ->addColumn('image', 'text')                         // PNG с прозрачным фоном
            ->addColumn('img_w', 'integer', ['default' => 0])    // натуральная ширина (для geom)
            ->addColumn('img_h', 'integer', ['default' => 0])    // натуральная высота
            ->addColumn('anchors', 'jsonb', ['default' => '[]']) // шахты: [{cx,w}] — 1/2/3 шт
            ->addColumn('model', 'text', ['null' => true])
            ->addColumn('vol', 'text', ['null' => true])
            ->addColumn('shaft', 'text', ['null' => true])
            ->addColumn('flask', 'text', ['null' => true])
            ->addColumn('heat', 'text', ['null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addIndex('product_id', ['unique' => true])
            ->addForeignKey('product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // --- Чаши ---
        $this->table('hookah_bowls')
            ->addColumn('slug', 'string', ['limit' => 64])
            ->addColumn('name', 'text')
            ->addColumn('image', 'text')
            ->addColumn('img_w', 'integer', ['default' => 0])
            ->addColumn('img_h', 'integer', ['default' => 0])
            ->addColumn('f', 'decimal', ['precision' => 4, 'scale' => 3, 'default' => 0.5]) // масштаб чаши
            ->addColumn('extra', 'integer', ['default' => 0])   // наценка за 1 кальян
            ->addColumn('kind', 'text', ['null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addIndex('slug', ['unique' => true])
            ->create();

        // --- Витринные напитки ---
        $this->table('hookah_drinks')
            ->addColumn('name', 'text')
            ->addColumn('image', 'text')
            ->addColumn('product_id', 'integer', ['null' => true]) // необяз. связь с товаром
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addForeignKey('product_id', 'products', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('hookah_drinks')->drop()->save();
        $this->table('hookah_bowls')->drop()->save();
        $this->table('hookah_showcase')->drop()->save();
    }
}
