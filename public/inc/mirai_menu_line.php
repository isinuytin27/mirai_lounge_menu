<?php
declare(strict_types=1);

/**
 * Линия выдачи заказа по категории меню: hookah | bar | kitchen
 */
function mirai_menu_line_for_category(string $categoryId): string
{
    $categoryId = trim($categoryId);
    if ($categoryId === "kalyan") {
        return "hookah";
    }
    $bar = [
        "classic",
        "tinctures",
        "sours",
        "tropical",
        "na_cocktails",
        "assorted",
    ];
    if (in_array($categoryId, $bar, true)) {
        return "bar";
    }
    return "kitchen";
}
