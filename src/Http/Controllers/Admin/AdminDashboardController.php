<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Admin\AdminUser;
use Mirai\Http\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/** Обзор админ-панели (новый стек). Пока — навигация по доменам + статус переноса. */
final class AdminDashboardController
{
    public function __invoke(Request $request, Response $response): Response
    {
        /** @var AdminUser $user */
        $user = $request->getAttribute(AuthMiddleware::ATTR);

        return Twig::fromRequest($request)->render($response, 'admin/dashboard.twig', [
            'user' => $user,
        ]);
    }
}
