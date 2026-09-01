<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

/**
 * Наценка чаши для кальяна (витрина). Сервер считает надбавку сам — клиент присылает
 * лишь slug чаши, подделать цену нельзя.
 */
interface BowlPricing
{
    /**
     * @return array{extra:int,name:string,units:int}|null null, если чаша/кальян не найдены
     */
    public function surcharge(string $productSlug, string $bowlSlug): ?array;
}
