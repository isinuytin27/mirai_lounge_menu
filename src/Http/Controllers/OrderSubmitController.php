<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Orders\OrderSubmissionService;
use Mirai\Http\Middleware\TableSessionMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Приём заказа гостя. Порт public/api/order-submit.php.
 * Контракт сохранён: POST {items:[{id,qty}]} -> {ok,order_id,append,telegram_ok},
 * коды ошибок no_table / empty_items / no_valid_items (too_fast даёт rate-limit middleware).
 */
final class OrderSubmitController
{
    public function __construct(private readonly OrderSubmissionService $service) {}

    public function __invoke(Request $request, Response $response): Response
    {
        // Стол приходит из TableSessionMiddleware (проверенная cookie).
        /** @var array{tid:string,caption:string}|null $table */
        $table = $request->getAttribute(TableSessionMiddleware::ATTR);
        if ($table === null) {
            return $this->json($response, 403, ['ok' => false, 'error' => 'no_table']);
        }

        $body = $request->getParsedBody();
        $items = is_array($body) ? ($body['items'] ?? null) : null;

        $result = $this->service->submit($table['tid'], $table['caption'], $items);

        if (!$result->ok) {
            $status = $result->error === 'empty_items' ? 400 : 400;
            return $this->json($response, $status, ['ok' => false, 'error' => $result->error]);
        }

        return $this->json($response, 200, [
            'ok' => true,
            'order_id' => $result->orderId,
            'append' => $result->append,
            'telegram_ok' => $result->telegramOk,
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
