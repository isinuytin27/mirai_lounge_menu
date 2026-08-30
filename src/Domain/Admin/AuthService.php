<?php

declare(strict_types=1);

namespace Mirai\Domain\Admin;

/**
 * Аутентификация админ-панели: проверка пароля + сессия. Заменяет admin/lib/auth.php.
 * Сессия — нативная PHP; в ней храним только id пользователя.
 */
final class AuthService
{
    private const SESSION_KEY = 'admin_user_id';

    public function __construct(private readonly AdminUserRepository $users) {}

    /** Проверка логина/пароля. Возвращает пользователя или null. */
    public function attempt(string $login, string $password): ?AdminUser
    {
        $user = $this->users->findByLogin(trim($login));

        return $user !== null && $user->verifyPassword($password) ? $user : null;
    }

    public function login(AdminUser $user): void
    {
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user->id;
    }

    public function logout(): void
    {
        $this->startSession();
        unset($_SESSION[self::SESSION_KEY]);
    }

    public function currentUser(): ?AdminUser
    {
        $this->startSession();
        $id = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($id) && $id !== '' ? $this->users->findById($id) : null;
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
