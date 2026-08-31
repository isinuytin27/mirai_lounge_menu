<?php

declare(strict_types=1);

namespace Mirai\Domain\Vip;

use Mirai\Infrastructure\Db\Repository;
use PDO;

/**
 * VIP/корпоративы (Postgres). Заменяет vip_storage.php.
 * События -> гости (с токеном) -> списания с лимитом бесплатных напитков бара.
 */
final class VipRepository extends Repository
{
    // ---------- события ----------

    /** @return list<array<string,mixed>> события с числом гостей */
    public function events(): array
    {
        return $this->fetchAll(
            'SELECT e.*, (SELECT count(*) FROM vip_guests g WHERE g.event_id = e.id) AS guests_count
             FROM vip_events e ORDER BY e.event_date DESC NULLS LAST, e.created_at DESC NULLS LAST'
        );
    }

    /** @return array<string,mixed>|null */
    public function findEventBySlug(string $slug): ?array
    {
        return $this->fetchOne('SELECT * FROM vip_events WHERE slug = :slug', ['slug' => $slug]);
    }

    /** @return array<string,mixed>|null */
    public function findEventById(string $id): ?array
    {
        return $this->fetchOne('SELECT * FROM vip_events WHERE id = :id', ['id' => $id]);
    }

    /** @param array<string,mixed> $d */
    public function saveEvent(array $d, ?string $id = null): string
    {
        $id ??= 'evt_' . bin2hex(random_bytes(6));
        $this->execute(
            'INSERT INTO vip_events (id, slug, organization, event_date, bar_free_limit, bar_line, active, created_at)
             VALUES (:id, :slug, :org, :date, :limit, :line, :active, :now)
             ON CONFLICT (id) DO UPDATE SET slug=EXCLUDED.slug, organization=EXCLUDED.organization,
                event_date=EXCLUDED.event_date, bar_free_limit=EXCLUDED.bar_free_limit,
                bar_line=EXCLUDED.bar_line, active=EXCLUDED.active, updated_at=:now',
            [
                'id' => $id,
                'slug' => (string) $d['slug'],
                'org' => $d['organization'] ?: null,
                'date' => $d['event_date'] ?: null,
                'limit' => (int) ($d['bar_free_limit'] ?? 2),
                'line' => $d['bar_line'] ?: 'bar',
                'active' => !empty($d['active']) ? 'true' : 'false',
                'now' => date('c'),
            ]
        );

        return $id;
    }

    public function deleteEvent(string $id): void
    {
        $this->execute('DELETE FROM vip_events WHERE id = :id', ['id' => $id]);
    }

    // ---------- гости ----------

    /** @return list<array<string,mixed>> гости события + число списаний */
    public function guests(string $eventId): array
    {
        return $this->fetchAll(
            'SELECT g.*, (SELECT count(*) FROM vip_consumptions c WHERE c.guest_id = g.id) AS consumed
             FROM vip_guests g WHERE g.event_id = :eid ORDER BY g.last_name, g.first_name',
            ['eid' => $eventId]
        );
    }

    /** @return array<string,mixed>|null гость по токену в рамках события */
    public function findGuestForEvent(string $eventId, string $token): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM vip_guests WHERE event_id = :eid AND token = :token',
            ['eid' => $eventId, 'token' => $token]
        );
    }

    public function addGuest(string $eventId, string $firstName, string $lastName, ?string $org): string
    {
        $id = 'guest_' . bin2hex(random_bytes(5));
        $this->execute(
            'INSERT INTO vip_guests (id, event_id, token, first_name, last_name, organization, free_used, created_at)
             VALUES (:id, :eid, :token, :fn, :ln, :org, 0, :now)',
            [
                'id' => $id, 'eid' => $eventId, 'token' => 'vip_' . bin2hex(random_bytes(12)),
                'fn' => $firstName, 'ln' => $lastName, 'org' => $org ?: null, 'now' => date('c'),
            ]
        );

        return $id;
    }

    public function deleteGuest(string $id): void
    {
        $this->execute('DELETE FROM vip_guests WHERE id = :id', ['id' => $id]);
    }

    /** @return list<array<string,mixed>> списания гостя */
    public function consumptions(string $guestId): array
    {
        return $this->fetchAll(
            'SELECT * FROM vip_consumptions WHERE guest_id = :id ORDER BY created_at DESC',
            ['id' => $guestId]
        );
    }

    // ---------- списание (транзакция) ----------

    /**
     * Списать напиток бара гостю. Бесплатно (до лимита) либо за счёт гостя.
     *
     * @param array<string,mixed> $event
     * @param array<string,mixed> $guest
     * @return array{ok:bool,error?:string,free_used?:int,free_left?:int}
     */
    public function consume(array $event, array $guest, string $productId, string $line, bool $paidByGuest): array
    {
        $eventLine = (string) ($event['bar_line'] ?? 'bar');
        if ($line !== $eventLine) {
            return ['ok' => false, 'error' => 'not_bar'];
        }

        $limit = (int) ($event['bar_free_limit'] ?? 2);
        $guestId = (string) $guest['id'];

        return $this->db->transactional(function (PDO $pdo) use ($guestId, $productId, $line, $paidByGuest, $limit): array {
            // Блокируем гостя, читаем актуальный free_used.
            $stmt = $pdo->prepare('SELECT free_used FROM vip_guests WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $guestId]);
            $freeUsed = (int) $stmt->fetchColumn();

            if (!$paidByGuest) {
                if ($freeUsed >= $limit) {
                    return ['ok' => false, 'error' => 'limit_reached'];
                }
                $pdo->prepare('UPDATE vip_guests SET free_used = free_used + 1, updated_at = :now WHERE id = :id')
                    ->execute(['now' => date('c'), 'id' => $guestId]);
                $freeUsed++;
            }

            $pdo->prepare(
                'INSERT INTO vip_consumptions (guest_id, product_id, line, paid_by_guest, created_at)
                 VALUES (:gid, :pid, :line, :paid, :now)'
            )->execute(['gid' => $guestId, 'pid' => $productId, 'line' => $line, 'paid' => $paidByGuest ? 'true' : 'false', 'now' => date('c')]);

            return ['ok' => true, 'free_used' => $freeUsed, 'free_left' => max(0, $limit - $freeUsed)];
        });
    }
}
