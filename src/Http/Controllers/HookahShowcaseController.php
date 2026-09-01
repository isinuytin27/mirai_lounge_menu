<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Menu\HookahShowcaseRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * JSON витрины кальянов (кальяны + чаши + напитки) — для экрана-карусели.
 */
final class HookahShowcaseController
{
    public function __construct(private readonly HookahShowcaseRepository $repo) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $this->repo->all();
        $response->getBody()->write(
            (string) json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE)
        );

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
