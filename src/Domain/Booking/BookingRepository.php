<?php

declare(strict_types=1);

namespace Mirai\Domain\Booking;

use Mirai\Infrastructure\Db\Database;
use Mirai\Infrastructure\Db\Repository;

/**
 * Брони/лист ожидания в Postgres. Порт логики server/app.py аддона, но без SQLite и
 * без общего пароля (доступ персонала — через наши роли на уровне роутов).
 *
 * Анти-накладки: у каждой брони интервал [время, время+DURATION); две брони на один
 * стол в один день конфликтуют, если интервалы пересекаются. Проверка+вставка — под
 * блокировкой строки стола (FOR UPDATE), поэтому гонка двух параллельных броней
 * исключена (в отличие от read-modify-write по JSON старого стека).
 */
final class BookingRepository extends Repository
{
    /** Длительность брони (мин) — только для проверки пересечений; в брони не хранится. */
    private const DURATION_MIN = 120;

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Столы зала для карты (активные, с местом на схеме).
     *
     * @return list<array{id:string,label:string,zone:?string,seats:?int,shape:string,x:?float,y:?float}>
     */
    public function hallTables(): array
    {
        $rows = $this->fetchAll(
            "SELECT id, caption, zone, shape, COALESCE(seats, capacity) AS seats, pos_x, pos_y
             FROM tables WHERE active = TRUE AND pos_x IS NOT NULL
             ORDER BY sort_order, id"
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'label' => (string) ($r['caption'] ?? $r['id']),
            'zone' => $r['zone'] !== null ? (string) $r['zone'] : null,
            'seats' => $r['seats'] !== null ? (int) $r['seats'] : null,
            'shape' => (string) ($r['shape'] ?? 'square'),
            'x' => $r['pos_x'] !== null ? (float) $r['pos_x'] : null,
            'y' => $r['pos_y'] !== null ? (float) $r['pos_y'] : null,
        ], $rows);
    }

    // ------------------------------------------------------------ гость ----

    /**
     * Занятость (публично): активные брони на дату — что показать на карте зала.
     *
     * @return list<array{id:int,dateISO:string,time:?string,tableId:?string,status:string}>
     */
    public function occupancy(string $date): array
    {
        $rows = $this->fetchAll(
            "SELECT id, booking_date, booking_time, table_id, status
             FROM bookings
             WHERE booking_date = :d AND status <> 'cancelled'",
            ['d' => $date]
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'dateISO' => (string) $r['booking_date'],
            'time' => $r['booking_time'] !== null ? (string) $r['booking_time'] : null,
            'tableId' => $r['table_id'] !== null ? (string) $r['table_id'] : null,
            'status' => (string) $r['status'],
        ], $rows);
    }

    /**
     * Создать бронь с проверкой пересечения (атомарно).
     *
     * @param array<string,mixed> $b
     * @return array{ok:bool,error?:string,booking?:Booking}
     */
    public function create(array $b): array
    {
        $date = trim((string) ($b['dateISO'] ?? ''));
        if ($date === '') {
            return ['ok' => false, 'error' => 'date_required'];
        }
        $tableId = trim((string) ($b['tableId'] ?? ''));
        $time = trim((string) ($b['time'] ?? ''));

        /** @var array{ok:bool,error?:string,booking?:Booking} $result */
        $result = $this->db->transactional(function () use ($b, $date, $tableId, $time): array {
            $pdo = $this->pdo();

            if ($tableId !== '') {
                // Сериализуем брони по этому столу: блокируем строку стола.
                $lock = $pdo->prepare('SELECT id FROM tables WHERE id = :id FOR UPDATE');
                $lock->execute(['id' => $tableId]);

                if ($this->overlaps($tableId, $date, $time)) {
                    return ['ok' => false, 'error' => 'table_taken'];
                }
            }

            $ins = $pdo->prepare(
                "INSERT INTO bookings
                    (booking_date, booking_time, table_id, table_label, zone, guests,
                     name, phone, email, comment, status, source, created_at)
                 VALUES
                    (:date, :time, :table_id, :table_label, :zone, :guests,
                     :name, :phone, :email, :comment, :status, :source, CURRENT_TIMESTAMP)
                 RETURNING id"
            );
            $ins->execute([
                'date' => $date,
                'time' => $time !== '' ? $time : null,
                'table_id' => $tableId !== '' ? $tableId : null,
                'table_label' => self::nn($b['tableLabel'] ?? null),
                'zone' => self::nn($b['zone'] ?? null),
                'guests' => isset($b['guests']) && $b['guests'] !== '' ? (int) $b['guests'] : null,
                'name' => self::nn($b['name'] ?? null),
                'phone' => self::nn($b['phone'] ?? null),
                'email' => self::nn($b['email'] ?? null),
                'comment' => self::nn($b['comment'] ?? null),
                'status' => (string) ($b['status'] ?? 'confirmed'),
                'source' => (string) ($b['source'] ?? 'widget'),
            ]);
            $id = (int) $ins->fetchColumn();

            $row = $this->fetchOne('SELECT * FROM bookings WHERE id = :id', ['id' => $id]);

            return ['ok' => true, 'booking' => Booking::fromRow($row ?? [])];
        });

        return $result;
    }

    /** Пересечение интервалов на столе+дате (без cancelled). */
    private function overlaps(string $tableId, string $date, string $time): bool
    {
        $start = self::slotMinutes($time);
        $end = $start + self::DURATION_MIN;

        $rows = $this->fetchAll(
            "SELECT booking_time FROM bookings
             WHERE table_id = :t AND booking_date = :d AND status <> 'cancelled'",
            ['t' => $tableId, 'd' => $date]
        );
        foreach ($rows as $r) {
            $s = self::slotMinutes((string) ($r['booking_time'] ?? ''));
            $e = $s + self::DURATION_MIN;
            if ($start < $e && $s < $end) {
                return true;
            }
        }

        return false;
    }

    /** «HH:MM» → минуты от полуночи; часы 00–11 считаем продолжением вечера (клуб вечер-ночь). */
    private static function slotMinutes(string $hhmm): int
    {
        $parts = explode(':', $hhmm !== '' ? $hhmm : '00:00');
        $hh = (int) $parts[0];
        $mm = (int) ($parts[1] ?? 0);
        $total = $hh * 60 + $mm;
        if ($hh < 12) {
            $total += 24 * 60;
        }

        return $total;
    }

    // ---------------------------------------------------------- персонал ----

    /**
     * Все брони (свежие сверху) — для админки.
     *
     * @return list<Booking>
     */
    public function all(): array
    {
        $rows = $this->fetchAll('SELECT * FROM bookings ORDER BY created_at DESC');
        return array_map(static fn (array $r): Booking => Booking::fromRow($r), $rows);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->execute('UPDATE bookings SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM bookings WHERE id = :id', ['id' => $id]);
    }

    // --------------------------------------------------------- waitlist ----

    /**
     * @return list<array<string,mixed>>
     */
    public function waitlist(): array
    {
        return $this->fetchAll('SELECT * FROM waitlist ORDER BY created_at ASC');
    }

    /**
     * @param array<string,mixed> $w
     */
    public function addWaitlist(array $w): int
    {
        $pdo = $this->pdo();
        $ins = $pdo->prepare(
            'INSERT INTO waitlist (booking_date, guests, name, phone, comment, created_at)
             VALUES (:date, :guests, :name, :phone, :comment, CURRENT_TIMESTAMP) RETURNING id'
        );
        $ins->execute([
            'date' => (string) ($w['dateISO'] ?? ''),
            'guests' => isset($w['guests']) && $w['guests'] !== '' ? (int) $w['guests'] : null,
            'name' => self::nn($w['name'] ?? null),
            'phone' => self::nn($w['phone'] ?? null),
            'comment' => self::nn($w['comment'] ?? null),
        ]);

        return (int) $ins->fetchColumn();
    }

    public function removeWaitlist(int $id): void
    {
        $this->execute('DELETE FROM waitlist WHERE id = :id', ['id' => $id]);
    }

    private static function nn(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }
}
