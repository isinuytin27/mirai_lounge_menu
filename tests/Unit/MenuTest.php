<?php

declare(strict_types=1);

namespace Mirai\Tests\Unit;

use Mirai\Domain\Menu\MenuLine;
use Mirai\Domain\Menu\Product;
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

    public function testProductInheritsLineAndReadsFields(): void
    {
        $p = Product::fromRow([
            'id' => 5,
            'slug' => 'kalyan_classic',
            'category_slug' => 'kalyan',
            'name' => 'Кальян Классика',
            'price' => 2000,
            'portion_value' => null,
            'prep_time' => '40-60 минут',
            'visible' => true,
            'available' => true,
        ], 'hookah');

        self::assertSame('kalyan_classic', $p->slug);
        self::assertSame('hookah', $p->line);
        self::assertSame(2000, $p->price);
        self::assertSame('40-60 минут', $p->prepTime);
    }

    public function testPortionLabel(): void
    {
        $food = Product::fromRow(['slug' => 'x', 'name' => 'X', 'price' => 1, 'portion_value' => '270.00', 'portion_unit' => 'г'], 'kitchen');
        self::assertSame('270 г', $food->portionLabel());

        $noPortion = Product::fromRow(['slug' => 'y', 'name' => 'Y', 'price' => 1], 'bar');
        self::assertNull($noPortion->portionLabel());
    }
}
