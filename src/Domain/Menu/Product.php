<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

/**
 * Товар меню. slug — стабильный идентификатор для фронта/заказов; id — суррогатный PK
 * (используется во внутренних связях, напр. графе гастропар).
 */
final class Product
{
    /** @param list<PairedProduct> $pairings сопутствующие товары (граф связок) */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $categorySlug,
        public readonly string $name,
        public readonly int $price,
        public readonly string $line,
        public readonly ?string $description = null,
        public readonly ?string $descriptionShort = null,
        public readonly ?string $composition = null,
        public readonly ?string $portionValue = null,
        public readonly ?string $portionUnit = null,
        public readonly ?string $prepTime = null,
        public readonly ?string $image = null,
        public readonly bool $visible = true,
        public readonly bool $available = true,
        public readonly int $sortOrder = 0,
        public readonly array $pairings = [],
    ) {}

    /**
     * @param array<string,mixed> $row
     * @param string $line линия выдачи из категории
     * @param list<PairedProduct> $pairings
     */
    public static function fromRow(array $row, string $line, array $pairings = []): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['slug'] ?? ''),
            (string) ($row['category_slug'] ?? $row['cat_slug'] ?? ''),
            (string) ($row['name'] ?? ''),
            (int) ($row['price'] ?? 0),
            $line,
            self::nullableStr($row['description'] ?? null),
            self::nullableStr($row['description_short'] ?? null),
            self::nullableStr($row['composition'] ?? null),
            self::nullableStr($row['portion_value'] ?? null),
            self::nullableStr($row['portion_unit'] ?? null),
            self::nullableStr($row['prep_time'] ?? null),
            self::nullableStr($row['image'] ?? null),
            self::boolish($row['visible'] ?? true),
            self::boolish($row['available'] ?? true),
            (int) ($row['sort_order'] ?? 0),
            $pairings,
        );
    }

    /** Порция человекочитаемо: «270 г», «500 мл» или null. */
    public function portionLabel(): ?string
    {
        if ($this->portionValue === null) {
            return null;
        }
        $value = rtrim(rtrim($this->portionValue, '0'), '.');
        return $this->portionUnit !== null ? "{$value} {$this->portionUnit}" : $value;
    }

    private static function nullableStr(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = is_scalar($v) ? (string) $v : '';
        return $s === '' ? null : $s;
    }

    private static function boolish(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            return in_array(strtolower($v), ['t', 'true', '1', 'yes'], true);
        }
        return (bool) $v;
    }
}
