<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Admin\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/** Вход/выход админ-панели (Postgres-аутентификация). Заменяет admin/login.php + logout. */
final class AdminAuthController
{
    public function __construct(private readonly AuthService $auth) {}

    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->auth->currentUser() !== null) {
            return $response->withStatus(302)->withHeader('Location', '/admin');
        }

        return Twig::fromRequest($request)->render($response, 'admin/login.twig', ['error' => null]);
    }

    public function login(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $login = (string) ($body['login'] ?? '');
        $password = (string) ($body['password'] ?? '');

        $user = $this->auth->attempt($login, $password);
        if ($user === null) {
            return Twig::fromRequest($request)->render(
                $response->withStatus(401),
                'admin/login.twig',
                ['error' => 'Неверный логин или пароль.']
            );
        }

        $this->auth->login($user);

        return $response->withStatus(302)->withHeader('Location', '/admin');
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();

        return $response->withStatus(302)->withHeader('Location', '/admin/login');
    }
}
