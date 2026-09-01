<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Домен бронирования (аддон mirai-booking → наш Postgres, без SQLite).
 * Заменяет iframe restoplace своим экраном брони с картой зала.
 *
 *   tables      — каталог столов расширен для карты зала (зона/места/позиция)
 *   bookings    — брони: дата+время, стол (FK+снимок), гость, статус, источник
 *   waitlist    — лист ожидания (когда всё занято)
 *
 * Анти-накладки считаются в приложении (интервал брони DEFAULT_DURATION, часы 00–11
 * как продолжение вечера) под транзакцией — см. BookingRepository.
 */
final class Booking extends AbstractMigration
{
    public function up(): void
    {
        $ts = ['timezone' => true];

        // --- Расширяем каталог столов для карты зала ---
        // pos_x/pos_y — проценты 0..100 (позиция центра стола на схеме зала).
        $this->table('tables')
            ->addColumn('zone', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('seats', 'integer', ['null' => true])
            ->addColumn('shape', 'string', ['limit' => 12, 'null' => true]) // round|square
            ->addColumn('pos_x', 'decimal', ['precision' => 6, 'scale' => 3, 'null' => true])
            ->addColumn('pos_y', 'decimal', ['precision' => 6, 'scale' => 3, 'null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->update();

        // --- Брони ---
        $this->table('bookings')
            ->addColumn('booking_date', 'date')
            ->addColumn('booking_time', 'string', ['limit' => 5, 'null' => true]) // 'HH:MM'
            ->addColumn('table_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('table_label', 'text', ['null' => true]) // снимок на момент брони
            ->addColumn('zone', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('guests', 'integer', ['null' => true])
            ->addColumn('name', 'text', ['null' => true])
            ->addColumn('phone', 'text', ['null' => true])
            ->addColumn('email', 'text', ['null' => true])
            ->addColumn('comment', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'confirmed']) // confirmed|cancelled|seated|noshow
            ->addColumn('source', 'string', ['limit' => 20, 'default' => 'widget'])    // widget|staff|phone
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['table_id', 'booking_date'])
            ->addIndex(['booking_date', 'status'])
            ->addForeignKey('table_id', 'tables', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        // --- Лист ожидания ---
        $this->table('waitlist')
            ->addColumn('booking_date', 'date')
            ->addColumn('guests', 'integer', ['null' => true])
            ->addColumn('name', 'text', ['null' => true])
            ->addColumn('phone', 'text', ['null' => true])
            ->addColumn('comment', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['booking_date'])
            ->create();

        // --- Сид столов зала из схемы виджета аддона (booking.js: столы 1–8) ---
        // [id, label, x%, y%, seats, shape, zone]
        $hall = [
            ['1', '1', 85, 20, 6, 'square', 'Lounge · Reception'],
            ['2', '2', 85, 52, 6, 'square', 'Lounge · Reception'],
            ['3', '3', 82, 82, 4, 'square', 'VIP PS 2'],
            ['4', '4', 48, 82, 4, 'square', 'Food & Hookah'],
            ['5', '5', 18, 82, 4, 'square', 'VIP PS 1'],
            ['6', '6', 15, 50, 4, 'square', 'VIP PS 1'],
            ['7', '7', 45, 46, 6, 'round', 'Food & Hookah'],
            ['8', '8', 45, 16, 6, 'round', 'Food & Hookah'],
        ];
        $sort = 0;
        foreach ($hall as [$id, $label, $x, $y, $seats, $shape, $zone]) {
            $this->execute(sprintf(
                "INSERT INTO tables (id, caption, capacity, active, zone, seats, shape, pos_x, pos_y, sort_order)
                 VALUES ('%s', 'Стол %s', %d, TRUE, '%s', %d, '%s', %d, %d, %d)
                 ON CONFLICT (id) DO UPDATE SET
                   zone = EXCLUDED.zone, seats = EXCLUDED.seats, shape = EXCLUDED.shape,
                   pos_x = EXCLUDED.pos_x, pos_y = EXCLUDED.pos_y, sort_order = EXCLUDED.sort_order",
                $id,
                $label,
                $seats,
                $zone,
                $seats,
                $shape,
                $x,
                $y,
                $sort++
            ));
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM tables WHERE id IN ('1','2','3','4','5','6','7','8')");
        $this->table('waitlist')->drop()->save();
        $this->table('bookings')->drop()->save();
        $this->table('tables')
            ->removeColumn('zone')->removeColumn('seats')->removeColumn('shape')
            ->removeColumn('pos_x')->removeColumn('pos_y')->removeColumn('sort_order')
            ->update();
    }
}
