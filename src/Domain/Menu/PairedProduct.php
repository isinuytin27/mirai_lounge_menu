<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

/** Сопутствующий товар (ребро графа гастропар): цель + атрибуты связи. */
final class PairedProduct
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly int $price,
        public readonly ?string $image,
        public readonly string $kind,      // gastro | upsell | alt
        public readonly float $weight,     // вес ребра
        public readonly ?string $note,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['slug'] ?? ''),
            (string) ($row['name'] ?? ''),
            (int) ($row['price'] ?? 0),
            isset($row['image']) && $row['image'] !== '' ? (string) $row['image'] : null,
            (string) ($row['kind'] ?? 'gastro'),
            (float) ($row['weight'] ?? 1),
            isset($row['note']) && $row['note'] !== '' ? (string) $row['note'] : null,
        );
    }
}
