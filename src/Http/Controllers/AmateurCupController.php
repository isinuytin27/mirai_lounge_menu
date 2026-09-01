<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Tournaments\TournamentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Лендинг регистрации на любительский турнир по CS2 (порт public/amateur_cup/).
 * Данные — из TournamentRepository (Postgres); форма шлёт в /api/tournament-register.
 */
final class AmateurCupController
{
    public function __construct(private readonly TournamentRepository $repo) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $settings = $this->repo->settings();
        $slotsLeft = $this->repo->slotsLeft();
        $maxSlots = (int) ($settings['max_slots'] ?? 0);

        return Twig::fromRequest($request)->render($response, 'amateur-cup.twig', [
            'settings' => $settings,
            'slots_left' => $slotsLeft,
            'max_slots' => $maxSlots,
            'registration_open' => (bool) ($settings['registration_open'] ?? false),
            'rating_options' => TournamentRepository::RATING_OPTIONS,
            'experience_options' => TournamentRepository::EXPERIENCE_OPTIONS,
            'client_config' => [
                'slots_left' => $slotsLeft,
                'max_slots' => $maxSlots,
                'registration_open' => (bool) ($settings['registration_open'] ?? false),
            ],
        ]);
    }
}
