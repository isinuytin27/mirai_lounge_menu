<?php

declare(strict_types=1);

namespace Mirai\Http\Middleware;

use Mirai\Infrastructure\Security\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

/** Проверяет CSRF-токен `_csrf` на мутациях (POST/PUT/PATCH/DELETE). */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Csrf $csrf) {}

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $body = $request->getParsedBody();
            $token = is_array($body) && isset($body['_csrf']) ? (string) $body['_csrf'] : null;

            if (!$this->csrf->isValid($token)) {
                $response = (new Response())->withStatus(419);
                $response->getBody()->write('419 — сессия формы истекла, обновите страницу');
                return $response;
            }
        }

        return $handler->handle($request);
    }
}
