<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Db;

use Mirai\Domain\Menu\MenuLine;
use PDO;
use RuntimeException;

/**
 * Одноразовый идемпотентный перенос data/*.json -> Postgres.
 * Повторный запуск обновляет существующие строки (upsert по id / уникальному ключу),
 * поэтому импорт можно гонять сколько угодно раз без дублей.
 */
final class JsonImporter
{
    /** @var array<string,int> счётчики импортированных строк по таблицам */
    private array $counts = [];

    public function __construct(
        private readonly Database $db,
        private readonly string $dataDir,
    ) {}

    /** @return array<string,int> счётчики по таблицам */
    public function run(): array
    {
        $this->counts = [];

        $this->db->transactional(function (): void {
            $this->importMenu();
            $this->importGallery();
            $this->importOrders();       // создаёт tables из заказов + order_items
            $this->importVip();
            $this->importTournaments();
            $this->importTickets();
            $this->importAdminUsers();
            $this->importPushSubscriptions();
        });

        return $this->counts;
    }

    // ---------- домены ----------

    private function importMenu(): void
    {
        $data = $this->load('menu.json');
        $categories = $this->rows($data['categories'] ?? []);
        foreach ($categories as $i => $c) {
            $categoryId = (string) $c['id'];
            $this->upsert('menu_categories', [
                'id' => $categoryId,
                'title' => (string) ($c['title'] ?? ''),
                // Историческое правило -> данные (один раз при импорте).
                'line' => MenuLine::forCategory($categoryId),
                'sort_order' => $i,
                'updated_at' => $data['updated_at'] ?? null,
            ]);
        }

        $products = $this->rows($data['products'] ?? []);
        foreach ($products as $i => $p) {
            $this->upsert('menu_products', [
                'id' => (string) $p['id'],
                'category_id' => $this->nullableStr($p['category_id'] ?? null),
                'name' => (string) ($p['name'] ?? ''),
                'price' => (int) ($p['price'] ?? 0),
                'description' => $this->nullableStr($p['description'] ?? null),
                'description_short' => $this->nullableStr($p['description_short'] ?? null),
                'image' => $this->nullableStr($p['image'] ?? null),
                'weight' => $this->nullableStr($p['weight'] ?? null),
                'visible' => $this->bool($p['visible'] ?? true),
                'sort_order' => $i,
                'updated_at' => $this->nullableStr($p['updated_at'] ?? null),
            ]);
        }
    }

    private function importGallery(): void
    {
        $data = $this->load('gallery.json');
        foreach ($this->rows($data['items'] ?? []) as $i => $g) {
            $this->upsert('gallery_items', [
                'id' => (string) $g['id'],
                'image' => (string) ($g['image'] ?? ''),
                'caption' => $this->nullableStr($g['caption'] ?? null),
                'sort_order' => $i,
                'updated_at' => $data['updated_at'] ?? null,
            ]);
        }
    }

    private function importOrders(): void
    {
        $data = $this->load('orders.json');
        $orders = $this->rows($data['orders'] ?? []);

        // Сначала создаём столы из уникальных (table_id, table_caption) — стол первокласс.
        $tables = [];
        foreach ($orders as $o) {
            $tid = $this->nullableStr($o['table_id'] ?? null);
            if ($tid !== null && !isset($tables[$tid])) {
                $tables[$tid] = $this->nullableStr($o['table_caption'] ?? null);
            }
        }
        foreach ($tables as $tid => $caption) {
            $this->upsert('tables', [
                'id' => $tid,
                'caption' => $caption,
                'active' => true,
            ]);
        }

        foreach ($orders as $o) {
            $orderId = (string) $o['id'];
            $this->upsert('orders', [
                'id' => $orderId,
                'table_id' => $this->nullableStr($o['table_id'] ?? null),
                'table_caption' => $this->nullableStr($o['table_caption'] ?? null),
                'status' => (string) ($o['status'] ?? 'open'),
                'created_at' => $this->nullableStr($o['created_at'] ?? null),
                'updated_at' => $this->nullableStr($o['updated_at'] ?? null),
                'closed_at' => $this->nullableStr($o['closed_at'] ?? null),
            ]);

            // Позиции: delete-then-insert по order_id для идемпотентности.
            $this->pdo()->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$orderId]);
            foreach ($this->rows($o['items'] ?? []) as $i => $it) {
                $this->insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $this->nullableStr($it['product_id'] ?? null),
                    'name' => (string) ($it['name'] ?? ''),
                    'qty' => (int) ($it['qty'] ?? 1),
                    'price' => (int) ($it['price'] ?? 0),
                    'line' => $this->nullableStr($it['line'] ?? null),
                    'category_id' => $this->nullableStr($it['category_id'] ?? null),
                    'added_at' => $this->nullableStr($it['added_at'] ?? null),
                    'sort_order' => $i,
                ]);
            }
        }
    }

    private function importVip(): void
    {
        $events = $this->load('vip_events.json');
        foreach ($this->rows($events['events'] ?? []) as $e) {
            $this->upsert('vip_events', [
                'id' => (string) $e['id'],
                'slug' => (string) ($e['slug'] ?? ''),
                'organization' => $this->nullableStr($e['organization'] ?? null),
                'event_date' => $this->nullableStr($e['event_date'] ?? null),
                'bar_free_limit' => (int) ($e['bar_free_limit'] ?? 0),
                'bar_line' => $this->nullableStr($e['bar_line'] ?? null),
                'active' => $this->bool($e['active'] ?? false),
            ]);
        }

        $guests = $this->load('vip_guests.json');
        foreach ($this->rows($guests['guests'] ?? []) as $g) {
            $guestId = (string) $g['id'];
            $this->upsert('vip_guests', [
                'id' => $guestId,
                'event_id' => (string) ($g['event_id'] ?? ''),
                'token' => (string) ($g['token'] ?? ''),
                'first_name' => $this->nullableStr($g['first_name'] ?? null),
                'last_name' => $this->nullableStr($g['last_name'] ?? null),
                'organization' => $this->nullableStr($g['organization'] ?? null),
                'free_used' => (int) ($g['free_used'] ?? 0),
            ]);

            // lines[] -> vip_consumptions (delete-then-insert по guest_id)
            $this->pdo()->prepare('DELETE FROM vip_consumptions WHERE guest_id = ?')->execute([$guestId]);
            foreach ($this->rows($g['lines'] ?? []) as $ln) {
                $this->insert('vip_consumptions', [
                    'guest_id' => $guestId,
                    'product_id' => $this->nullableStr($ln['product_id'] ?? null),
                    'line' => $this->nullableStr($ln['line'] ?? null),
                    'paid_by_guest' => $this->bool($ln['paid_by_guest'] ?? false),
                    'created_at' => $this->nullableStr($ln['created_at'] ?? $ln['added_at'] ?? null),
                ]);
            }
        }
    }

    private function importTournaments(): void
    {
        $data = $this->load('tournaments.json');
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        // Единственный турнир с фиксированным id=1 — идемпотентно.
        $this->upsert('tournaments', [
            'id' => 1,
            'title' => $this->nullableStr($settings['title'] ?? null),
            'max_slots' => (int) ($settings['max_slots'] ?? 0),
            'format' => $this->nullableStr($settings['format'] ?? null),
            'roster' => $this->nullableStr($settings['roster'] ?? null),
            'deadline' => $this->nullableStr($settings['deadline'] ?? null),
            'fee' => $this->nullableStr($settings['fee'] ?? null),
            'registration_open' => $this->bool($settings['registration_open'] ?? false),
            'updated_at' => $this->nullableStr($data['updated_at'] ?? null),
        ]);

        foreach ($this->rows($data['applications'] ?? []) as $a) {
            $this->upsert('tournament_applications', [
                'id' => (string) ($a['id'] ?? ''),
                'tournament_id' => 1,
                'status' => (string) ($a['status'] ?? 'new'),
                'team_name' => $this->nullableStr($a['team_name'] ?? null),
                'rating' => $this->nullableStr($a['rating'] ?? null),
                'experience' => $this->nullableStr($a['experience'] ?? null),
                'captain_name' => $this->nullableStr($a['captain_name'] ?? null),
                'captain_steam' => $this->nullableStr($a['captain_steam'] ?? null),
                'captain_telegram' => $this->nullableStr($a['captain_telegram'] ?? null),
                'captain_phone' => $this->nullableStr($a['captain_phone'] ?? null),
                'comment' => $this->nullableStr($a['comment'] ?? null),
                'players' => $this->json($a['players'] ?? null),
                'sources' => $this->json($a['sources'] ?? null),
                'created_at' => $this->nullableStr($a['created_at'] ?? null),
            ]);
        }
    }

    private function importTickets(): void
    {
        $data = $this->load('tickets.json');
        foreach ($this->rows($data['tickets'] ?? []) as $t) {
            $this->upsert('tickets', [
                'id' => (string) $t['id'],
                'title' => (string) ($t['title'] ?? ''),
                'description' => $this->nullableStr($t['description'] ?? null),
                'category' => $this->nullableStr($t['category'] ?? null),
                'priority' => $this->nullableStr($t['priority'] ?? null),
                'status' => (string) ($t['status'] ?? 'open'),
                'created_by' => $this->nullableStr($t['created_by'] ?? null),
                'created_at' => $this->nullableStr($t['created_at'] ?? null),
                'updated_at' => $this->nullableStr($t['updated_at'] ?? null),
            ]);
        }
    }

    private function importAdminUsers(): void
    {
        $data = $this->load('admin_users.json');
        foreach ($this->rows($data['users'] ?? []) as $u) {
            $this->upsert('admin_users', [
                'id' => (string) $u['id'],
                'login' => (string) ($u['login'] ?? ''),
                'password_hash' => (string) ($u['password_hash'] ?? ''),
                'first_name' => $this->nullableStr($u['first_name'] ?? null),
                'last_name' => $this->nullableStr($u['last_name'] ?? null),
                'role' => (string) ($u['role'] ?? 'staff'),
                'created_at' => $this->nullableStr($u['created_at'] ?? null),
                'updated_at' => $this->nullableStr($u['updated_at'] ?? null),
            ]);
        }
    }

    private function importPushSubscriptions(): void
    {
        $data = $this->load('push_subscriptions.json');
        foreach ($this->rows($data['subscriptions'] ?? []) as $s) {
            $endpoint = trim((string) ($s['endpoint'] ?? ''));
            if ($endpoint === '') {
                continue;
            }
            $keys = is_array($s['keys'] ?? null) ? $s['keys'] : [];
            $this->upsert('push_subscriptions', [
                'endpoint' => $endpoint,
                'p256dh' => $this->nullableStr($keys['p256dh'] ?? null),
                'auth' => $this->nullableStr($keys['auth'] ?? null),
            ], 'endpoint');
        }
    }

    // ---------- инфраструктура ----------

    private function pdo(): PDO
    {
        return $this->db->pdo();
    }

    /** @return array<string,mixed> */
    private function load(string $file): array
    {
        $path = $this->dataDir . '/' . $file;
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Некорректный JSON в {$path}");
        }
        /** @var array<string,mixed> $decoded */
        return $decoded;
    }

    /**
     * @param mixed $value
     * @return list<array<string,mixed>>
     */
    private function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * INSERT ... ON CONFLICT (<conflict>) DO UPDATE — upsert.
     * @param array<string,mixed> $row
     */
    private function upsert(string $table, array $row, string $conflict = 'id'): void
    {
        $cols = array_keys($row);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);
        $updates = [];
        foreach ($cols as $c) {
            if ($c !== $conflict) {
                $updates[] = "{$c} = EXCLUDED.{$c}";
            }
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s',
            $table,
            implode(', ', $cols),
            implode(', ', $placeholders),
            $conflict,
            $updates === [] ? "{$conflict} = EXCLUDED.{$conflict}" : implode(', ', $updates),
        );
        $this->pdo()->prepare($sql)->execute($this->bind($row));
        $this->counts[$table] = ($this->counts[$table] ?? 0) + 1;
    }

    /**
     * Простой INSERT (для child-строк, которые предварительно удалены).
     * @param array<string,mixed> $row
     */
    private function insert(string $table, array $row): void
    {
        $cols = array_keys($row);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', $placeholders),
        );
        $this->pdo()->prepare($sql)->execute($this->bind($row));
        $this->counts[$table] = ($this->counts[$table] ?? 0) + 1;
    }

    /**
     * Приводит значения к пригодным для PDO (bool -> 't'/'f' Postgres понимает нативно через PDO bool).
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function bind(array $row): array
    {
        $bound = [];
        foreach ($row as $k => $v) {
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $bound[':' . $k] = $v;
        }
        return $bound;
    }

    private function nullableStr(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = is_scalar($v) ? (string) $v : '';
        return $s === '' ? null : $s;
    }

    private function bool(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    private function json(mixed $v): ?string
    {
        if ($v === null || $v === []) {
            return null;
        }
        $encoded = json_encode($v, JSON_UNESCAPED_UNICODE);
        return $encoded === false ? null : $encoded;
    }
}
