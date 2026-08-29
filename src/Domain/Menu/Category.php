<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

/** Категория меню. Линия выдачи (line) относится ко всем продуктам категории. */
final class Category
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $line,
        public readonly int $sortOrder = 0,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) ($row['title'] ?? ''),
            (string) ($row['line'] ?? MenuLine::KITCHEN),
            (int) ($row['sort_order'] ?? 0),
        );
    }
}
