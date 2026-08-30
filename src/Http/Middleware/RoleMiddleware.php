<?php

declare(strict_types=1);

namespace Mirai\Http\Middleware;

use Mirai\Domain\Admin\AdminUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

/**
 * Проверка права роли на маршрут. Ставится ПОСЛЕ AuthMiddleware (использует admin_user).
 * Права: admin_panel | users | tickets | vip (см. Role). Заменяет admin_require_role().
 */
final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $capability) {}

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $user = $request->getAttribute(AuthMiddleware::ATTR);
        $role = $user instanceof AdminUser ? $user->role : null;

        $ok = $role !== null && match ($this->capability) {
            'admin_panel' => $role->isAdminPanel(),
            'users' => $role->canManageUsers(),
            'tickets' => $role->canAccessTickets(),
            'vip' => $role->canAccessVip(),
            default => false,
        };

        if (!$ok) {
            $response = (new Response())->withStatus(403);
            $response->getBody()->write('403 — недостаточно прав');
            return $response;
        }

        return $handler->handle($request);
    }
}
