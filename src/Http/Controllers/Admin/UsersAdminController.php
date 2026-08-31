<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Admin\AdminUserRepository;
use Mirai\Domain\Admin\Role;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/** Пользователи админки — Postgres (owner). Заменяет admin/users.php + write admin_users_storage.php. */
final class UsersAdminController
{
    public function __construct(private readonly AdminUserRepository $repo) {}

    public function index(Request $request, Response $response): Response
    {
        $labels = [];
        foreach (Role::cases() as $r) {
            $labels[$r->value] = $r->label();
        }

        return Twig::fromRequest($request)->render($response, 'admin/users/index.twig', [
            'users' => $this->repo->all(),
            'roles' => $labels,
            'flash' => $request->getQueryParams()['ok'] ?? null,
            'error' => $request->getQueryParams()['err'] ?? null,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $login = trim((string) ($b['login'] ?? ''));
        $password = (string) ($b['password'] ?? '');
        if ($login === '' || $password === '') {
            return $this->back($response, err: 'Логин и пароль обязательны');
        }
        if ($this->repo->findByLogin($login) !== null) {
            return $this->back($response, err: 'Логин уже занят');
        }
        $this->repo->upsert($login, Role::fromString((string) ($b['role'] ?? 'staff')), $password,
            trim((string) ($b['first_name'] ?? '')) ?: null, trim((string) ($b['last_name'] ?? '')) ?: null);

        return $this->back($response, ok: 'Пользователь создан');
    }

    public function setRole(Request $request, Response $response, array $args): Response
    {
        $id = (string) $args['id'];
        $new = Role::fromString((string) (((array) $request->getParsedBody())['role'] ?? ''));
        $user = $this->repo->findById($id);
        if ($user === null) {
            return $this->back($response);
        }
        // Нельзя понизить последнего владельца.
        if ($user->role === Role::Owner && $new !== Role::Owner && $this->repo->countOwners() <= 1) {
            return $this->back($response, err: 'Нельзя снять роль с последнего владельца');
        }
        $this->repo->setRole($id, $new);

        return $this->back($response, ok: 'Роль обновлена');
    }

    public function resetPassword(Request $request, Response $response, array $args): Response
    {
        $pass = (string) (((array) $request->getParsedBody())['password'] ?? '');
        if (strlen($pass) >= 4) {
            $this->repo->resetPassword((string) $args['id'], $pass);
            return $this->back($response, ok: 'Пароль сброшен');
        }

        return $this->back($response, err: 'Пароль слишком короткий');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $this->repo->findById((string) $args['id']);
        if ($user !== null && $user->role === Role::Owner && $this->repo->countOwners() <= 1) {
            return $this->back($response, err: 'Нельзя удалить последнего владельца');
        }
        $this->repo->delete((string) $args['id']);

        return $this->back($response, ok: 'Пользователь удалён');
    }

    private function back(Response $response, ?string $ok = null, ?string $err = null): Response
    {
        $q = $ok !== null ? '?ok=' . rawurlencode($ok) : ($err !== null ? '?err=' . rawurlencode($err) : '');

        return $response->withStatus(302)->withHeader('Location', '/admin/users' . $q);
    }
}
