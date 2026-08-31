<?php

declare(strict_types=1);

namespace Mirai\Domain\Tournaments;

use Mirai\Infrastructure\Db\Repository;

/**
 * Турниры (Postgres). Заменяет tournaments_storage.php. Модель — один активный турнир (id=1),
 * настройки + заявки.
 */
final class TournamentRepository extends Repository
{
    public const STATUSES = ['new' => 'Новая', 'accepted' => 'Принята', 'rejected' => 'Отклонена'];
    public const SOURCES = ['instagram' => 'Instagram', 'staff' => 'От персонала', 'friends' => 'От друзей'];
    private const TOURNAMENT_ID = 1;

    /** @return array<string,mixed> настройки (с дефолтами, если строки нет) */
    public function settings(): array
    {
        $row = $this->fetchOne('SELECT * FROM tournaments WHERE id = :id', ['id' => self::TOURNAMENT_ID]);

        return $row ?? [
            'id' => self::TOURNAMENT_ID, 'title' => null, 'max_slots' => 10, 'format' => '5 на 5',
            'roster' => 'до 5 игроков', 'deadline' => '', 'fee' => '', 'registration_open' => true,
        ];
    }

    /** @param array<string,mixed> $s */
    public function saveSettings(array $s): void
    {
        $this->execute(
            'INSERT INTO tournaments (id, title, max_slots, format, roster, deadline, fee, registration_open, updated_at)
             VALUES (:id, :title, :max, :format, :roster, :deadline, :fee, :open, :now)
             ON CONFLICT (id) DO UPDATE SET title=EXCLUDED.title, max_slots=EXCLUDED.max_slots, format=EXCLUDED.format,
                roster=EXCLUDED.roster, deadline=EXCLUDED.deadline, fee=EXCLUDED.fee,
                registration_open=EXCLUDED.registration_open, updated_at=EXCLUDED.updated_at',
            [
                'id' => self::TOURNAMENT_ID,
                'title' => $s['title'] ?? null,
                'max' => (int) ($s['max_slots'] ?? 10),
                'format' => $s['format'] ?? null,
                'roster' => $s['roster'] ?? null,
                'deadline' => $s['deadline'] ?? null,
                'fee' => $s['fee'] ?? null,
                'open' => !empty($s['registration_open']) ? 'true' : 'false',
                'now' => date('c'),
            ]
        );
    }

    /** @return list<array<string,mixed>> заявки, свежие сверху */
    public function applications(): array
    {
        $rows = $this->fetchAll(
            'SELECT id, status, team_name, rating, experience, captain_name, captain_steam,
                    captain_telegram, captain_phone, comment, players, sources, created_at
             FROM tournament_applications WHERE tournament_id = :id ORDER BY created_at DESC NULLS LAST',
            ['id' => self::TOURNAMENT_ID]
        );
        foreach ($rows as &$r) {
            $r['players'] = is_string($r['players'] ?? null) ? (json_decode($r['players'], true) ?: []) : [];
            $r['sources'] = is_string($r['sources'] ?? null) ? (json_decode($r['sources'], true) ?: []) : [];
        }
        return $rows;
    }

    public function counts(): array
    {
        $out = ['total' => 0, 'new' => 0, 'accepted' => 0, 'rejected' => 0];
        foreach ($this->fetchAll('SELECT status, count(*) AS c FROM tournament_applications WHERE tournament_id = :id GROUP BY status', ['id' => self::TOURNAMENT_ID]) as $r) {
            $out['total'] += (int) $r['c'];
            $s = (string) $r['status'];
            if (isset($out[$s])) {
                $out[$s] = (int) $r['c'];
            }
        }
        return $out;
    }

    public function slotsLeft(): int
    {
        $max = (int) ($this->settings()['max_slots'] ?? 10);
        $used = (int) ($this->fetchOne('SELECT count(*) AS c FROM tournament_applications WHERE tournament_id = :id', ['id' => self::TOURNAMENT_ID])['c'] ?? 0);
        return max(0, $max - $used);
    }

    /**
     * Добавить заявку. Возвращает [ok, error?, id?].
     * @param array<string,mixed> $a
     * @return array{ok:bool,error?:string,id?:string}
     */
    public function addApplication(array $a): array
    {
        $s = $this->settings();
        if (empty($s['registration_open'])) {
            return ['ok' => false, 'error' => 'closed'];
        }
        if ($this->slotsLeft() <= 0) {
            return ['ok' => false, 'error' => 'full'];
        }

        $id = 'app_' . bin2hex(random_bytes(6));
        $this->execute(
            'INSERT INTO tournament_applications
                (id, tournament_id, status, team_name, rating, experience, captain_name, captain_steam,
                 captain_telegram, captain_phone, comment, players, sources, created_at)
             VALUES (:id, :tid, :status, :team, :rating, :exp, :cn, :cs, :ct, :cp, :comment, :players, :sources, :now)',
            [
                'id' => $id, 'tid' => self::TOURNAMENT_ID, 'status' => 'new',
                'team' => $a['team_name'] ?? '', 'rating' => $a['rating'] ?? null, 'exp' => $a['experience'] ?? null,
                'cn' => $a['captain_name'] ?? '', 'cs' => $a['captain_steam'] ?? '', 'ct' => $a['captain_telegram'] ?? '',
                'cp' => $a['captain_phone'] ?? '', 'comment' => $a['comment'] ?? null,
                'players' => json_encode($a['players'] ?? [], JSON_UNESCAPED_UNICODE),
                'sources' => json_encode($a['sources'] ?? [], JSON_UNESCAPED_UNICODE),
                'now' => date('c'),
            ]
        );

        return ['ok' => true, 'id' => $id];
    }

    public function setStatus(string $id, string $status): void
    {
        if (!isset(self::STATUSES[$status])) {
            return;
        }
        $this->execute('UPDATE tournament_applications SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
    }

    public function delete(string $id): void
    {
        $this->execute('DELETE FROM tournament_applications WHERE id = :id', ['id' => $id]);
    }
}
