<?php

declare(strict_types=1);

namespace Mirai\Domain\Admin;

/** Пользователь админ-панели. Пароль — bcrypt-хэш (не хранится в открытом виде). */
final class AdminUser
{
    public function __construct(
        public readonly string $id,
        public readonly string $login,
        public readonly string $passwordHash,
        public readonly Role $role,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) ($row['login'] ?? ''),
            (string) ($row['password_hash'] ?? ''),
            Role::fromString(isset($row['role']) ? (string) $row['role'] : null),
            isset($row['first_name']) ? (string) $row['first_name'] : null,
            isset($row['last_name']) ? (string) $row['last_name'] : null,
        );
    }

    public function displayName(): string
    {
        $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
        return $name !== '' ? $name : $this->login;
    }

    public function verifyPassword(string $password): bool
    {
        return $this->passwordHash !== '' && password_verify($password, $this->passwordHash);
    }
}
