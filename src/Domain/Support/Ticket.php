<?php

declare(strict_types=1);

namespace Mirai\Domain\Support;

/** Тикет персонала. Справочники (категория/приоритет/статус) — как в старом tickets_storage. */
final class Ticket
{
    public const CATEGORIES = ['bug' => 'Баг', 'feature' => 'Фича', 'design' => 'Дизайн', 'content' => 'Контент', 'other' => 'Другое'];
    public const PRIORITIES = ['low' => 'Низкий', 'normal' => 'Средний', 'high' => 'Высокий'];
    public const STATUSES = ['open' => 'Открыт', 'in_progress' => 'В работе', 'closed' => 'Закрыт'];

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $category,
        public readonly string $priority,
        public readonly string $status,
        public readonly ?string $createdBy,
        public readonly ?string $createdAt,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) ($row['title'] ?? ''),
            isset($row['description']) ? (string) $row['description'] : null,
            (string) ($row['category'] ?? 'other'),
            (string) ($row['priority'] ?? 'normal'),
            (string) ($row['status'] ?? 'open'),
            isset($row['created_by']) ? (string) $row['created_by'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Другое';
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? 'Средний';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
