<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Редактор карты зала (админка) — БД как источник правды вместо localStorage аддона.
 *
 *   tables      — полная геометрия столов на изометрии (w/h/полигон/скругление + мета)
 *   hall_zones  — зоны-хотспоты (Lounge/ПК-зона): позиции для видов A/B, лейбл, телефон
 *   hall_notes  — заметки персонала на карте (текст + фото, привязка к точке и виду)
 *
 * 3D-карта (booking-map.js) и редактор читают/пишут это через /api/booking/hall и
 * /admin/booking/hall. Значения по умолчанию — из seedPositions/seedHotspots аддона.
 */
final class HallEditor extends AbstractMigration
{
    public function up(): void
    {
        $ts = ['timezone' => true];

        // --- Полная геометрия столов (доли 0..1 на изометрии) ---
        $this->table('tables')
            ->addColumn('pos_w', 'decimal', ['precision' => 7, 'scale' => 4, 'null' => true])
            ->addColumn('pos_h', 'decimal', ['precision' => 7, 'scale' => 4, 'null' => true])
            ->addColumn('radius', 'integer', ['default' => 14])
            ->addColumn('points', 'text', ['null' => true])     // полигон стола (SVG points)
            ->addColumn('descr', 'text', ['null' => true])      // подпись в попапе
            ->addColumn('phone', 'text', ['null' => true])
            ->addColumn('photo', 'text', ['null' => true])
            ->update();

        // --- Зоны-хотспоты ---
        $this->table('hall_zones')
            ->addColumn('zone_key', 'string', ['limit' => 40])
            ->addColumn('label', 'text')
            ->addColumn('kind', 'string', ['limit' => 16, 'default' => 'zone'])  // zone|phone
            ->addColumn('accent', 'string', ['limit' => 16, 'default' => 'neon']) // neon|cyan|…
            ->addColumn('title', 'text', ['null' => true])
            ->addColumn('descr', 'text', ['null' => true])
            ->addColumn('phone', 'text', ['null' => true])
            ->addColumn('ax', 'decimal', ['precision' => 7, 'scale' => 4, 'default' => 0]) // позиция на виде A
            ->addColumn('ay', 'decimal', ['precision' => 7, 'scale' => 4, 'default' => 0])
            ->addColumn('bx', 'decimal', ['precision' => 7, 'scale' => 4, 'null' => true]) // на виде B
            ->addColumn('by', 'decimal', ['precision' => 7, 'scale' => 4, 'null' => true])
            ->addColumn('pref', 'decimal', ['precision' => 4, 'scale' => 3, 'default' => 0]) // предпочт. вид 0..1
            ->addColumn('hide_on_tables', 'boolean', ['default' => false])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addIndex('zone_key', ['unique' => true])
            ->create();

        // --- Заметки персонала ---
        $this->table('hall_notes')
            ->addColumn('text', 'text', ['null' => true])
            ->addColumn('photo', 'text', ['null' => true])   // путь загруженного фото
            ->addColumn('x', 'decimal', ['precision' => 7, 'scale' => 4, 'default' => 0])
            ->addColumn('y', 'decimal', ['precision' => 7, 'scale' => 4, 'default' => 0])
            ->addColumn('view', 'string', ['limit' => 1, 'default' => 'a']) // a|b
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

        // --- Сид геометрии столов (seedPositions аддона: 11–18) ---
        // [id, w, h]  (x/y уже засеяны в 20260901000004)
        $geo = [
            ['t11', 0.115, 0.0856], ['t12', 0.105, 0.0645], ['t13', 0.160, 0.0707],
            ['t14', 0.105, 0.0781], ['t15', 0.105, 0.0781], ['t16', 0.100, 0.0744],
            ['t17', 0.115, 0.0625], ['t18', 0.115, 0.0856],
        ];
        foreach ($geo as [$id, $w, $h]) {
            $this->execute(sprintf(
                "UPDATE tables SET pos_w = %s, pos_h = %s, radius = 14, shape = 'poly',
                    points = '0,51 63,0 100,49 37,100',
                    descr = 'Lounge · бронирование стола заранее', phone = '+74242218080'
                 WHERE id = '%s'",
                $w,
                $h,
                $id
            ));
        }

        // --- Сид зон-хотспотов (seedHotspots аддона) ---
        $this->execute(
            "INSERT INTO hall_zones (zone_key, label, kind, accent, title, descr, phone, ax, ay, bx, by, pref, hide_on_tables, sort_order) VALUES
             ('lounge', 'Lounge', 'zone', 'neon', 'Зона со столами', 'Столы и места для отдыха. Можно забронировать заранее.', '', 0.515, 0.498, 0.418, 0.452, 0, TRUE, 0),
             ('pc', 'ПК-зона', 'phone', 'cyan', 'Игровые станции', 'Бронирование по телефону', '+7 4242 21-80-80', 0.338, 0.342, 0.717, 0.560, 0, FALSE, 1)"
        );
    }

    public function down(): void
    {
        $this->table('hall_notes')->drop()->save();
        $this->table('hall_zones')->drop()->save();
        $this->table('tables')
            ->removeColumn('pos_w')->removeColumn('pos_h')->removeColumn('radius')
            ->removeColumn('points')->removeColumn('descr')->removeColumn('phone')->removeColumn('photo')
            ->update();
    }
}
