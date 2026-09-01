<?php

declare(strict_types=1);

namespace Mirai\Tests\Integration;

use Mirai\Domain\Booking\BookingRepository;
use Mirai\Infrastructure\Db\Database;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * End-to-end брони против реального Postgres: анти-накладки (интервал 120 мин,
 * часы 00–11 как продолжение вечера) и занятость. Требует поднятого postgres +
 * применённых миграций (сид столов t11–t18). Пропускается, если БД недоступна.
 */
final class BookingFlowTest extends TestCase
{
    private ContainerInterface $c;
    private BookingRepository $repo;
    private string $date = '2099-01-15'; // заведомо чистая дата
    private string $table = '7';

    protected function setUp(): void
    {
        $this->c = require dirname(__DIR__, 2) . '/src/bootstrap.php';
        try {
            $this->c->get(Database::class)->pdo()->query('SELECT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Postgres недоступен: ' . $e->getMessage());
        }
        $this->repo = $this->c->get(BookingRepository::class);
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    public function testCreateAndOverlapRule(): void
    {
        // 19:00 — первая бронь проходит.
        $r1 = $this->repo->create([
            'dateISO' => $this->date, 'time' => '19:00', 'tableId' => $this->table,
            'name' => 'Тест', 'phone' => '0000',
        ]);
        self::assertTrue($r1['ok']);

        // 20:00 — пересекает интервал [19:00, 21:00) → отказ.
        $r2 = $this->repo->create([
            'dateISO' => $this->date, 'time' => '20:00', 'tableId' => $this->table,
            'name' => 'Тест2', 'phone' => '0001',
        ]);
        self::assertFalse($r2['ok']);
        self::assertSame('table_taken', $r2['error']);

        // 21:30 — не пересекает → проходит.
        $r3 = $this->repo->create([
            'dateISO' => $this->date, 'time' => '21:30', 'tableId' => $this->table,
            'name' => 'Тест3', 'phone' => '0002',
        ]);
        self::assertTrue($r3['ok']);

        // Занятость: две подтверждённые брони на столе.
        $occ = array_filter($this->repo->occupancy($this->date), fn (array $o): bool => $o['tableId'] === $this->table);
        self::assertCount(2, $occ);
    }

    public function testWaitlist(): void
    {
        $id = $this->repo->addWaitlist([
            'dateISO' => $this->date, 'guests' => 5, 'name' => 'Очередь', 'phone' => '0003',
        ]);
        self::assertGreaterThan(0, $id);

        $found = array_filter($this->repo->waitlist(), fn (array $w): bool => (int) $w['id'] === $id);
        self::assertCount(1, $found);

        $this->repo->removeWaitlist($id);
        $found = array_filter($this->repo->waitlist(), fn (array $w): bool => (int) $w['id'] === $id);
        self::assertCount(0, $found);
    }

    private function cleanup(): void
    {
        $pdo = $this->c->get(Database::class)->pdo();
        $pdo->prepare('DELETE FROM bookings WHERE booking_date = ?')->execute([$this->date]);
        $pdo->prepare('DELETE FROM waitlist WHERE booking_date = ?')->execute([$this->date]);
    }
}
