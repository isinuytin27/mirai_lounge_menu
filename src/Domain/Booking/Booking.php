<?php

declare(strict_types=1);

namespace Mirai\Domain\Booking;

/**
 * Бронь стола. Стол хранится и FK (table_id), и снимком (table_label/zone) —
 * чтобы карточка брони осталась читаемой, даже если стол переименуют/уберут.
 */
final class Booking
{
    public function __construct(
        public readonly int $id,
        public readonly string $date,        // YYYY-MM-DD
        public readonly ?string $time,       // HH:MM
        public readonly ?string $tableId,
        public readonly ?string $tableLabel,
        public readonly ?string $zone,
        public readonly ?int $guests,
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $comment,
        public readonly string $status,
        public readonly string $source,
        public readonly ?string $createdAt,
    ) {}

    /** @param array<string,mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) ($r['id'] ?? 0),
            (string) ($r['booking_date'] ?? ''),
            self::s($r['booking_time'] ?? null),
            self::s($r['table_id'] ?? null),
            self::s($r['table_label'] ?? null),
            self::s($r['zone'] ?? null),
            isset($r['guests']) ? (int) $r['guests'] : null,
            self::s($r['name'] ?? null),
            self::s($r['phone'] ?? null),
            self::s($r['email'] ?? null),
            self::s($r['comment'] ?? null),
            (string) ($r['status'] ?? 'confirmed'),
            (string) ($r['source'] ?? 'widget'),
            self::s($r['created_at'] ?? null),
        );
    }

    /** @return array<string,mixed> camelCase — как отдаёт фронт брони. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'dateISO' => $this->date,
            'time' => $this->time,
            'tableId' => $this->tableId,
            'tableLabel' => $this->tableLabel,
            'zone' => $this->zone,
            'guests' => $this->guests,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'comment' => $this->comment,
            'status' => $this->status,
            'source' => $this->source,
            'createdAt' => $this->createdAt,
        ];
    }

    private static function s(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = is_scalar($v) ? (string) $v : '';
        return $s === '' ? null : $s;
    }
}
