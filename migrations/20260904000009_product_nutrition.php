<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * КБЖУ и аллергены товара — для новой карточки блюда (экран «Кухня»).
 * Всё необязательно (заполняется в админке); аллергены — задел на будущее.
 */
final class ProductNutrition extends AbstractMigration
{
    public function up(): void
    {
        $this->table('products')
            ->addColumn('kcal', 'integer', ['null' => true])                                  // калории
            ->addColumn('protein', 'decimal', ['precision' => 6, 'scale' => 1, 'null' => true]) // белки, г
            ->addColumn('fat', 'decimal', ['precision' => 6, 'scale' => 1, 'null' => true])      // жиры, г
            ->addColumn('carbs', 'decimal', ['precision' => 6, 'scale' => 1, 'null' => true])    // углеводы, г
            ->addColumn('allergens', 'text', ['null' => true])                                   // аллергены (список/текст)
            ->update();
    }

    public function down(): void
    {
        $this->table('products')
            ->removeColumn('kcal')->removeColumn('protein')->removeColumn('fat')
            ->removeColumn('carbs')->removeColumn('allergens')
            ->update();
    }
}
