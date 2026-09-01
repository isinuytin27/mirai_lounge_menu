<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Tournaments\TournamentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Приём заявки на турнир с публичной страницы. Порт public/api/tournament-register.php,
 * пишет в Postgres. Контракт сохранён: {ok, id, slots_left} | {ok:false, error, fields?}.
 */
final class TournamentRegisterController
{
    public function __construct(private readonly TournamentRepository $repo) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->json($response, 400, ['ok' => false, 'error' => 'bad_json']);
        }

        $str = static fn (mixed $v, int $max): string => mb_substr(trim((string) (is_scalar($v) ? $v : '')), 0, $max);

        $data = [
            'team_name' => $str($body['team_name'] ?? '', 40),
            'rating' => $str($body['rating'] ?? '', 60),
            'experience' => $str($body['experience'] ?? '', 60),
            'captain_name' => $str($body['captain_name'] ?? '', 40),
            'captain_steam' => $str($body['captain_steam'] ?? '', 300),
            'captain_telegram' => $str($body['captain_telegram'] ?? '', 60),
            'captain_phone' => $str($body['captain_phone'] ?? '', 32),
            'comment' => $str($body['comment'] ?? '', 500),
        ];

        // Игроки (до 5).
        $players = [];
        foreach ((array) ($body['players'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $nick = $str($p['nick'] ?? '', 40);
            if ($nick === '') {
                continue;
            }
            $players[] = ['nick' => $nick, 'steam' => $str($p['steam'] ?? '', 300)];
            if (count($players) >= 5) {
                break;
            }
        }

        // Источники (из справочника).
        $sources = [];
        foreach ((array) ($body['sources'] ?? []) as $s) {
            $s = trim((string) (is_scalar($s) ? $s : ''));
            if (isset(TournamentRepository::SOURCES[$s]) && !in_array($s, $sources, true)) {
                $sources[] = $s;
            }
        }

        // Валидация (как в старом эндпоинте).
        $errors = [];
        if ($data['team_name'] === '') $errors[] = 'team_name';
        if ($data['rating'] === '') $errors[] = 'rating';
        if ($data['captain_name'] === '') $errors[] = 'captain_name';
        if ($data['captain_steam'] === '' || !str_contains(strtolower($data['captain_steam']), 'steamcommunity.com')) $errors[] = 'captain_steam';
        if ($data['captain_telegram'] === '') $errors[] = 'captain_telegram';
        if ($data['captain_phone'] === '') $errors[] = 'captain_phone';
        if ($players === []) $errors[] = 'players';
        if ($sources === []) $errors[] = 'sources';
        // Согласие: клиент (amateur-cup.js) шлёт два флага — оба обязательны.
        if (empty($body['agree_rules']) || empty($body['agree_privacy'])) $errors[] = 'consent';

        if ($errors !== []) {
            return $this->json($response, 422, ['ok' => false, 'error' => 'validation', 'fields' => $errors]);
        }

        $result = $this->repo->addApplication($data + ['players' => $players, 'sources' => $sources]);
        if (!$result['ok']) {
            return $this->json($response, 409, ['ok' => false, 'error' => $result['error'] ?? 'error']);
        }

        return $this->json($response, 200, ['ok' => true, 'id' => $result['id'], 'slots_left' => $this->repo->slotsLeft()]);
    }

    /** @param array<string,mixed> $payload */
    private function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
