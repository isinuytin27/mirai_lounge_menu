<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Orders\TableRegistry;
use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Security\TableSession;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * QR-вход стола: ?table=<id> валидируется против реестра столов, ставит подписанную
 * cookie и редиректит на путь без query. Порт mirai_table_handle_query_param().
 */
final class TableEntryController
{
    public function __construct(
        private readonly TableSession $session,
        private readonly TableRegistry $tables,
        private readonly Config $config,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $tableId = trim((string) ($request->getQueryParams()['table'] ?? ''));
        $path = $request->getUri()->getPath();
        if ($path === '') {
            $path = '/';
        }

        if ($tableId === '' || !$this->tables->activeExists($tableId)) {
            // Нет/неизвестный стол — просто уходим на путь без query, без cookie.
            return $response->withStatus(302)->withHeader('Location', $path);
        }

        $caption = $this->tables->captionOf($tableId) ?? $tableId;
        $cfg = $this->config->tableSession();
        $token = $this->session->issue($tableId, $caption);

        $cookie = $this->buildCookie($cfg['cookie_name'], $token, $cfg['ttl_seconds'], $request);

        return $response
            ->withStatus(302)
            ->withHeader('Set-Cookie', $cookie)
            ->withHeader('Location', $path);
    }

    private function buildCookie(string $name, string $value, int $ttl, Request $request): string
    {
        $expires = gmdate('D, d-M-Y H:i:s T', time() + $ttl);
        $https = $request->getUri()->getScheme() === 'https';

        $parts = [
            $name . '=' . $value,
            'Expires=' . $expires,
            'Max-Age=' . $ttl,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        ];
        if ($https) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }
}
