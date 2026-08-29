<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

/**
 * Линия выдачи заказа: hookah | bar | kitchen.
 *
 * Раньше правило было захардкожено в mirai_menu_line.php. Теперь оно — источник для
 * ОДНОРАЗОВОГО заполнения колонки menu_categories.line при импорте; в рантайме линия
 * читается из БД (её можно редактировать), а не вычисляется.
 */
final class MenuLine
{
    public const HOOKAH = 'hookah';
    public const BAR = 'bar';
    public const KITCHEN = 'kitchen';

    private const BAR_CATEGORIES = [
        'classic',
        'tinctures',
        'sours',
        'tropical',
        'na_cocktails',
        'assorted',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return [self::HOOKAH, self::BAR, self::KITCHEN];
    }

    public static function isValid(string $line): bool
    {
        return in_array($line, self::all(), true);
    }

    /** Историческое правило category-id -> line (для сидирования колонки при импорте). */
    public static function forCategory(string $categoryId): string
    {
        $categoryId = trim($categoryId);

        if ($categoryId === 'kalyan') {
            return self::HOOKAH;
        }
        if (in_array($categoryId, self::BAR_CATEGORIES, true)) {
            return self::BAR;
        }

        return self::KITCHEN;
    }
}
