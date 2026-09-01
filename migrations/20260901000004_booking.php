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
        $this->table('tables')
            ->addColumn('zone', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('seats', 'integer', ['null' => true])
            ->addColumn('pos_x', 'decimal', ['precision' => 6, 'scale' => 4, 'null' => true])
            ->addColumn('pos_y', 'decimal', ['precision' => 6, 'scale' => 4, 'null' => true])
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

        // --- Сид столов зала (Lounge) из карты аддона (map.html: столы 11–18) ---
        $hall = [
            ['t11', 'Стол 11', 0.305, 0.315], ['t12', 'Стол 12', 0.385, 0.270],
            ['t13', 'Стол 13', 0.552, 0.385], ['t14', 'Стол 14', 0.300, 0.430],
            ['t15', 'Стол 15', 0.300, 0.515], ['t16', 'Стол 16', 0.298, 0.585],
            ['t17', 'Стол 17', 0.415, 0.415], ['t18', 'Стол 18', 0.445, 0.485],
        ];
        $sort = 0;
        foreach ($hall as [$id, $label, $x, $y]) {
            $this->execute(sprintf(
                "INSERT INTO tables (id, caption, capacity, active, zone, seats, pos_x, pos_y, sort_order)
                 VALUES ('%s', '%s', 4, TRUE, 'Lounge', 4, %s, %s, %d)
                 ON CONFLICT (id) DO UPDATE SET
                   zone = EXCLUDED.zone, seats = EXCLUDED.seats,
                   pos_x = EXCLUDED.pos_x, pos_y = EXCLUDED.pos_y, sort_order = EXCLUDED.sort_order",
                $id,
                $label,
                $x,
                $y,
                $sort++
            ));
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM tables WHERE id IN ('t11','t12','t13','t14','t15','t16','t17','t18')");
        $this->table('waitlist')->drop()->save();
        $this->table('bookings')->drop()->save();
        $this->table('tables')
            ->removeColumn('zone')->removeColumn('seats')
            ->removeColumn('pos_x')->removeColumn('pos_y')->removeColumn('sort_order')
            ->update();
    }
}
