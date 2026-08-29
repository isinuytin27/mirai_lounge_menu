<?php

declare(strict_types=1);

namespace Mirai\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

/**
 * Простой антифлуд по сессии: не чаще одного успешного прохода раз в N секунд
 * на ключ. Порт проверки too_fast из старого order-submit.php.
 *
 * Возвращает 429 {ok:false,error:"too_fast"} при слишком частых запросах.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $key,
        private readonly int $minIntervalSeconds = 2,
    ) {}

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionKey = 'mirai_rl_' . $this->key;
        $now = time();
        $last = (int) ($_SESSION[$sessionKey] ?? 0);

        if ($last > 0 && $now - $last < $this->minIntervalSeconds) {
            $response = new Response(429);
            $response->getBody()->write(
                (string) json_encode(['ok' => false, 'error' => 'too_fast'], JSON_UNESCAPED_UNICODE)
            );
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $_SESSION[$sessionKey] = $now;

        return $handler->handle($request);
    }
}
