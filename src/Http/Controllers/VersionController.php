<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Infrastructure\Config\Config;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Страница версии. Пилот вью-слоя — доказывает, что Twig отдаёт HTML сквозь новый стек.
 */
final class VersionController
{
    public function __construct(private readonly Config $config) {}

    public function __invoke(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'version.twig', [
            'version' => $this->config->appVersion(),
            'env' => $this->config->appEnv(),
        ]);
    }
}
