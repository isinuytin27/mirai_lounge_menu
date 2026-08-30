<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Security;

/** CSRF-токен в сессии. Токен кладём в формы, проверяем на мутациях. */
final class Csrf
{
    private const KEY = 'csrf_token';

    public function token(): string
    {
        $this->start();
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    public function isValid(?string $token): bool
    {
        $this->start();
        $stored = $_SESSION[self::KEY] ?? '';

        return is_string($token) && $token !== '' && is_string($stored) && $stored !== ''
            && hash_equals($stored, $token);
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
