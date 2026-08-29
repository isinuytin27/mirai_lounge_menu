<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

/** Продукт меню. Линия наследуется от категории (проставляется при сборке меню). */
final class Product
{
    public function __construct(
        public readonly string $id,
        public readonly string $categoryId,
        public readonly string $name,
        public readonly int $price,
        public readonly string $line,
        public readonly ?string $description = null,
        public readonly ?string $descriptionShort = null,
        public readonly ?string $image = null,
        public readonly ?string $weight = null,
        public readonly bool $visible = true,
        public readonly int $sortOrder = 0,
    ) {}

    /**
     * @param array<string,mixed> $row
     * @param string $line линия выдачи из категории продукта
     */
    public static function fromRow(array $row, string $line): self
    {
        return new self(
            (string) $row['id'],
            (string) ($row['category_id'] ?? ''),
            (string) ($row['name'] ?? ''),
            (int) ($row['price'] ?? 0),
            $line,
            self::nullableStr($row['description'] ?? null),
            self::nullableStr($row['description_short'] ?? null),
            self::nullableStr($row['image'] ?? null),
            self::nullableStr($row['weight'] ?? null),
            self::boolish($row['visible'] ?? true),
            (int) ($row['sort_order'] ?? 0),
        );
    }

    private static function nullableStr(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = is_scalar($v) ? (string) $v : '';
        return $s === '' ? null : $s;
    }

    /** Postgres через PDO может отдавать boolean как 't'/'f' — приводим устойчиво. */
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
