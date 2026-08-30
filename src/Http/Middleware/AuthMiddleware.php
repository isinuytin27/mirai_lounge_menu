<?php

declare(strict_types=1);

namespace Mirai\Http\Middleware;

use Mirai\Domain\Admin\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

/**
 * Требует вход в админку. Незалогиненного — на /admin/login. Залогиненного пользователя
 * кладёт в атрибут запроса `admin_user`. Заменяет admin_require_login().
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public const ATTR = 'admin_user';

    public function __construct(private readonly AuthService $auth) {}

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $user = $this->auth->currentUser();
        if ($user === null) {
            return (new Response())->withStatus(302)->withHeader('Location', '/admin/login');
        }

        return $handler->handle($request->withAttribute(self::ATTR, $user));
    }
}
