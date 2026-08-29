<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

/**
 * Поиск видимого продукта по id. Абстракция, чтобы приём заказа не зависел от
 * конкретного MenuRepository (и тестировался без БД).
 */
interface ProductFinder
{
    public function findVisibleProduct(string $productId): ?Product;
}
