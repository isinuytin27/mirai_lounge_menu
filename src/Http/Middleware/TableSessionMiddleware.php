<?php

declare(strict_types=1);

namespace Mirai\Http\Middleware;

use Mirai\Domain\Orders\TableRegistry;
use Mirai\Infrastructure\Security\TableSession;
use Mirai\Infrastructure\Config\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Читает подписанную cookie стола, проверяет подпись (TableSession) и ре-валидирует
 * стол против реестра (стол — первокласс, а не элемент галереи). Результат кладёт
 * в атрибут запроса `table` = ['tid'=>..,'caption'=>..] либо null.
 *
 * Порт mirai_table_read_session(), но без побочных эффектов (не пишет cookie).
 * Выдача cookie по QR (?table=) — задача отдельного контроллера.
 */
final class TableSessionMiddleware implements MiddlewareInterface
{
    public const ATTR = 'table';

    public function __construct(
        private readonly TableSession $session,
        private readonly TableRegistry $tables,
        private readonly Config $config,
    ) {}

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $table = null;

        $cookieName = $this->config->tableSession()['cookie_name'];
        $cookies = $request->getCookieParams();
        $token = isset($cookies[$cookieName]) ? (string) $cookies[$cookieName] : '';

        $payload = $token !== '' ? $this->session->verify($token) : null;
        if ($payload !== null && $this->tables->activeExists($payload['tid'])) {
            // Подпись из cookie считается снимком; актуальную подпись берём из реестра, если есть.
            $caption = $this->tables->captionOf($payload['tid']) ?? $payload['cap'];
            $table = ['tid' => $payload['tid'], 'caption' => $caption];
        }

        return $handler->handle($request->withAttribute(self::ATTR, $table));
    }
}
