<?php

declare(strict_types=1);

namespace Mirai\Domain\Support;

use Mirai\Infrastructure\Db\Repository;

/** Тикеты персонала (Postgres). Заменяет tickets_storage.php. */
final class TicketRepository extends Repository
{
    /** @return list<Ticket> свежие сверху */
    public function all(): array
    {
        $rows = $this->fetchAll(
            'SELECT id, title, description, category, priority, status, created_by, created_at
             FROM tickets ORDER BY (status = :closed), created_at DESC NULLS LAST',
            ['closed' => 'closed']
        );

        return array_map(static fn (array $r): Ticket => Ticket::fromRow($r), $rows);
    }

    public function create(string $title, ?string $description, string $category, string $priority, ?string $createdBy): void
    {
        $cat = isset(Ticket::CATEGORIES[$category]) ? $category : 'other';
        $pri = isset(Ticket::PRIORITIES[$priority]) ? $priority : 'normal';

        $this->execute(
            'INSERT INTO tickets (id, title, description, category, priority, status, created_by, created_at)
             VALUES (:id, :title, :descr, :cat, :pri, :status, :by, :now)',
            [
                'id' => 'tkt_' . bin2hex(random_bytes(6)),
                'title' => $title,
                'descr' => $description ?: null,
                'cat' => $cat,
                'pri' => $pri,
                'status' => 'open',
                'by' => $createdBy,
                'now' => date('c'),
            ]
        );
    }

    public function setStatus(string $id, string $status): void
    {
        if (!isset(Ticket::STATUSES[$status])) {
            return;
        }
        $this->execute(
            'UPDATE tickets SET status = :status, updated_at = :now WHERE id = :id',
            ['status' => $status, 'now' => date('c'), 'id' => $id]
        );
    }

    public function delete(string $id): void
    {
        $this->execute('DELETE FROM tickets WHERE id = :id', ['id' => $id]);
    }

    /** @return array{open:int,in_progress:int,closed:int} */
    public function counts(): array
    {
        $out = ['open' => 0, 'in_progress' => 0, 'closed' => 0];
        foreach ($this->fetchAll('SELECT status, count(*) AS c FROM tickets GROUP BY status') as $r) {
            $s = (string) $r['status'];
            if (isset($out[$s])) {
                $out[$s] = (int) $r['c'];
            }
        }
        return $out;
    }
}
