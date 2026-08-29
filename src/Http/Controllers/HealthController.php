<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Infrastructure\Config\Config;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Технический health-check. Без БД — проверяет, что HTTP-стек жив и Config читается.
 */
final class HealthController
{
    public function __construct(private readonly Config $config) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $payload = [
            'status' => 'ok',
            'app' => 'mirai-lounge',
            'version' => $this->config->appVersion(),
            'env' => $this->config->appEnv(),
            'time' => date('c'),
        ];

        $response->getBody()->write(
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
