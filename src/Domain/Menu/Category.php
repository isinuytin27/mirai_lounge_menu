<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

/** Категория меню. slug — стабильный якорь (для anchors/data-target), line — маршрут выдачи. */
final class Category
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $line,
        public readonly ?string $groupSlug = null,
        public readonly int $sortOrder = 0,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) ($row['slug'] ?? $row['id']),
            (string) ($row['title'] ?? ''),
            (string) ($row['line'] ?? MenuLine::KITCHEN),
            isset($row['group_slug']) ? (string) $row['group_slug'] : null,
            (int) ($row['sort_order'] ?? 0),
        );
    }
}
