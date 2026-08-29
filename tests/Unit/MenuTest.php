<?php

declare(strict_types=1);

namespace Mirai\Tests\Unit;

use Mirai\Domain\Menu\MenuLine;
use Mirai\Domain\Menu\MenuRepository;
use PHPUnit\Framework\TestCase;

final class MenuTest extends TestCase
{
    public function testLineMapping(): void
    {
        self::assertSame('hookah', MenuLine::forCategory('kalyan'));
        self::assertSame('bar', MenuLine::forCategory('classic'));
        self::assertSame('bar', MenuLine::forCategory('na_cocktails'));
        self::assertSame('kitchen', MenuLine::forCategory('zakuski'));
        self::assertSame('kitchen', MenuLine::forCategory('unknown_cat'));
    }

    public function testLineValidity(): void
    {
        self::assertTrue(MenuLine::isValid('bar'));
        self::assertFalse(MenuLine::isValid('drinks'));
        self::assertSame(['hookah', 'bar', 'kitchen'], MenuLine::all());
    }

    public function testBuildMenuGroupsAndInheritsLine(): void
    {
        $categories = [
            ['id' => 'kalyan', 'title' => 'Кальян', 'line' => 'hookah', 'sort_order' => 0],
            ['id' => 'zakuski', 'title' => 'Закуски', 'line' => 'kitchen', 'sort_order' => 1],
            ['id' => 'empty', 'title' => 'Пусто', 'line' => 'bar', 'sort_order' => 2],
        ];
        $products = [
            ['id' => 'p1', 'category_id' => 'kalyan', 'name' => 'Классика', 'price' => 2000, 'visible' => true, 'sort_order' => 0],
            ['id' => 'p2', 'category_id' => 'zakuski', 'name' => 'Брускета', 'price' => 750, 'visible' => true, 'sort_order' => 0],
        ];

        $menu = MenuRepository::buildMenu($categories, $products);

        // Пустая категория выкинута.
        self::assertCount(2, $menu);
        self::assertSame('kalyan', $menu[0]['category']->id);
        self::assertSame('hookah', $menu[0]['category']->line);

        // Продукт наследует линию своей категории.
        self::assertSame('hookah', $menu[0]['products'][0]->line);
        self::assertSame('kitchen', $menu[1]['products'][0]->line);
        self::assertSame(2000, $menu[0]['products'][0]->price);
    }

    public function testBuildMenuPreservesCategoryOrder(): void
    {
        $categories = [
            ['id' => 'b', 'title' => 'B', 'line' => 'bar', 'sort_order' => 0],
            ['id' => 'a', 'title' => 'A', 'line' => 'kitchen', 'sort_order' => 1],
        ];
        $products = [
            ['id' => 'x', 'category_id' => 'a', 'name' => 'X', 'price' => 1, 'visible' => true],
            ['id' => 'y', 'category_id' => 'b', 'name' => 'Y', 'price' => 2, 'visible' => true],
        ];

        $menu = MenuRepository::buildMenu($categories, $products);

        // Порядок категорий сохраняется как во входе (b, затем a).
        self::assertSame(['b', 'a'], [$menu[0]['category']->id, $menu[1]['category']->id]);
    }
}
