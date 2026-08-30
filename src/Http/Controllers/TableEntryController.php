<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Orders\TableRegistry;
use Mirai\Infrastructure\Security\TableCookie;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * QR-вход стола: /t?table=<id> валидируется против реестра столов, ставит подписанную
 * cookie и редиректит на витрину. Порт mirai_table_handle_query_param().
 */
final class TableEntryController
{
    public function __construct(
        private readonly TableRegistry $tables,
        private readonly TableCookie $cookie,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $tableId = trim((string) ($request->getQueryParams()['table'] ?? ''));
        // /t — выделенный роут входа; после обработки уводим гостя на витрину.
        $target = '/';

        if ($tableId === '' || !$this->tables->activeExists($tableId)) {
            return $response->withStatus(302)->withHeader('Location', $target);
        }

        $caption = $this->tables->captionOf($tableId) ?? $tableId;
        $https = $request->getUri()->getScheme() === 'https';

        return $response
            ->withStatus(302)
            ->withHeader('Set-Cookie', $this->cookie->header($tableId, $caption, $https))
            ->withHeader('Location', $target);
    }
}
