<?php

declare(strict_types=1);

namespace Mirai\Domain\Booking;

use Mirai\Infrastructure\Db\Repository;

/**
 * Карта зала как данные: геометрия столов, зоны-хотспоты, заметки. БД — источник правды
 * (заменяет localStorage аддона). Читает 3D-карта (booking-map.js) и редактор в админке,
 * пишет только редактор (за ролями). Формат наружу — как ждёт JS аддона (camelCase, a/b).
 */
final class HallRepository extends Repository
{
    /**
     * Столы с полной геометрией на изометрии (для карты и редактора).
     *
     * @return list<array<string,mixed>>
     */
    public function tables(): array
    {
        $rows = $this->fetchAll(
            "SELECT id, caption, zone, COALESCE(seats, capacity) AS seats, shape,
                    pos_x, pos_y, pos_w, pos_h, radius, points, descr, phone, photo
             FROM tables WHERE active = TRUE AND pos_x IS NOT NULL
             ORDER BY sort_order, id"
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'label' => (string) ($r['caption'] ?? $r['id']),
            'seats' => $r['seats'] !== null ? (int) $r['seats'] : null,
            'zone' => $r['zone'] !== null ? (string) $r['zone'] : null,
            'shape' => (string) ($r['shape'] ?? 'poly'),
            'x' => (float) $r['pos_x'],
            'y' => (float) $r['pos_y'],
            'w' => $r['pos_w'] !== null ? (float) $r['pos_w'] : 0.1,
            'h' => $r['pos_h'] !== null ? (float) $r['pos_h'] : 0.07,
            'radius' => (int) ($r['radius'] ?? 14),
            'points' => (string) ($r['points'] ?? ''),
            'desc' => $r['descr'] !== null ? (string) $r['descr'] : '',
            'phone' => $r['phone'] !== null ? (string) $r['phone'] : '',
            'photo' => $r['photo'] !== null ? (string) $r['photo'] : '',
        ], $rows);
    }

    /**
     * Зоны-хотспоты (Lounge/ПК-зона) — позиции на видах A/B.
     *
     * @return list<array<string,mixed>>
     */
    public function zones(): array
    {
        $rows = $this->fetchAll(
            'SELECT zone_key, label, kind, accent, title, descr, phone, ax, ay, bx, by, pref, hide_on_tables
             FROM hall_zones WHERE active = TRUE ORDER BY sort_order, zone_key'
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['zone_key'],
            'label' => (string) $r['label'],
            'kind' => (string) $r['kind'],
            'accent' => (string) $r['accent'],
            'title' => (string) ($r['title'] ?? ''),
            'desc' => (string) ($r['descr'] ?? ''),
            'phone' => (string) ($r['phone'] ?? ''),
            'a' => [(float) $r['ax'], (float) $r['ay']],
            'b' => $r['bx'] !== null ? [(float) $r['bx'], (float) $r['by']] : [(float) $r['ax'], (float) $r['ay']],
            'pref' => (float) $r['pref'],
            'hideOnTables' => self::boolish($r['hide_on_tables']),
        ], $rows);
    }

    /**
     * Заметки персонала на карте.
     *
     * @return list<array<string,mixed>>
     */
    public function notes(): array
    {
        $rows = $this->fetchAll(
            'SELECT id, text, photo, x, y, view FROM hall_notes ORDER BY sort_order, id'
        );

        return array_map(static fn (array $r): array => [
            'id' => 'n' . $r['id'],
            'text' => (string) ($r['text'] ?? ''),
            'photo' => (string) ($r['photo'] ?? ''),
            'x' => (float) $r['x'],
            'y' => (float) $r['y'],
            'view' => (string) ($r['view'] ?? 'a'),
        ], $rows);
    }

    // ---------------------------------------------------------- запись ----

    /**
     * Сохранить геометрию столов (только существующие; новые столы тут не заводятся).
     *
     * @param list<array<string,mixed>> $positions
     */
    public function saveTables(array $positions): void
    {
        $sql = "UPDATE tables SET
                    caption = :label, seats = :seats, shape = :shape,
                    pos_x = :x, pos_y = :y, pos_w = :w, pos_h = :h,
                    radius = :radius, points = :points, descr = :descr, phone = :phone
                WHERE id = :id";
        $this->db->transactional(function () use ($positions, $sql): void {
            $st = $this->pdo()->prepare($sql);
            foreach ($positions as $p) {
                $id = (string) ($p['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $st->execute([
                    'label' => (string) ($p['label'] ?? $id),
                    'seats' => isset($p['seats']) && $p['seats'] !== '' ? (int) $p['seats'] : null,
                    'shape' => (string) ($p['shape'] ?? 'poly'),
                    'x' => (float) ($p['x'] ?? 0),
                    'y' => (float) ($p['y'] ?? 0),
                    'w' => (float) ($p['w'] ?? 0.1),
                    'h' => (float) ($p['h'] ?? 0.07),
                    'radius' => (int) ($p['radius'] ?? 14),
                    'points' => (string) ($p['points'] ?? ''),
                    'descr' => self::nn($p['desc'] ?? null),
                    'phone' => self::nn($p['phone'] ?? null),
                    'id' => $id,
                ]);
            }
        });
    }

    /**
     * Полная замена зон (редактор может добавлять/удалять).
     *
     * @param list<array<string,mixed>> $zones
     */
    public function saveZones(array $zones): void
    {
        $this->db->transactional(function () use ($zones): void {
            $pdo = $this->pdo();
            $pdo->exec('DELETE FROM hall_zones');
            $st = $pdo->prepare(
                'INSERT INTO hall_zones (zone_key, label, kind, accent, title, descr, phone, ax, ay, bx, by, pref, hide_on_tables, sort_order)
                 VALUES (:key, :label, :kind, :accent, :title, :descr, :phone, :ax, :ay, :bx, :by, :pref, :hide, :sort)'
            );
            $sort = 0;
            foreach ($zones as $z) {
                $key = (string) ($z['id'] ?? '');
                if ($key === '') {
                    continue;
                }
                $a = is_array($z['a'] ?? null) ? $z['a'] : [0, 0];
                $b = is_array($z['b'] ?? null) ? $z['b'] : $a;
                $st->execute([
                    'key' => $key,
                    'label' => (string) ($z['label'] ?? $key),
                    'kind' => (string) ($z['kind'] ?? 'zone'),
                    'accent' => (string) ($z['accent'] ?? 'neon'),
                    'title' => self::nn($z['title'] ?? null),
                    'descr' => self::nn($z['desc'] ?? null),
                    'phone' => self::nn($z['phone'] ?? null),
                    'ax' => (float) ($a[0] ?? 0),
                    'ay' => (float) ($a[1] ?? 0),
                    'bx' => (float) ($b[0] ?? 0),
                    'by' => (float) ($b[1] ?? 0),
                    'pref' => (float) ($z['pref'] ?? 0),
                    'hide' => !empty($z['hideOnTables']) ? 'true' : 'false',
                    'sort' => $sort++,
                ]);
            }
        });
    }

    /**
     * Полная замена заметок.
     *
     * @param list<array<string,mixed>> $notes
     */
    public function saveNotes(array $notes): void
    {
        $this->db->transactional(function () use ($notes): void {
            $pdo = $this->pdo();
            $pdo->exec('DELETE FROM hall_notes');
            $st = $pdo->prepare(
                'INSERT INTO hall_notes (text, photo, x, y, view, sort_order)
                 VALUES (:text, :photo, :x, :y, :view, :sort)'
            );
            $sort = 0;
            foreach ($notes as $n) {
                $view = ($n['view'] ?? 'a') === 'b' ? 'b' : 'a';
                $st->execute([
                    'text' => self::nn($n['text'] ?? null),
                    'photo' => self::nn($n['photo'] ?? null),
                    'x' => (float) ($n['x'] ?? 0),
                    'y' => (float) ($n['y'] ?? 0),
                    'view' => $view,
                    'sort' => $sort++,
                ]);
            }
        });
    }

    private static function nn(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

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
