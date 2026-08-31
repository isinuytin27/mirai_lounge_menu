<?php

declare(strict_types=1);

namespace Mirai\Domain\Admin;

use Mirai\Infrastructure\Db\Repository;

/** Чтение/запись админ-пользователей (Postgres). Заменяет admin_users_storage.php. */
final class AdminUserRepository extends Repository
{
    private const COLS = 'id, login, password_hash, role, first_name, last_name';

    public function findByLogin(string $login): ?AdminUser
    {
        $row = $this->fetchOne(
            'SELECT ' . self::COLS . ' FROM admin_users WHERE login = :login',
            ['login' => $login]
        );

        return $row === null ? null : AdminUser::fromRow($row);
    }

    public function findById(string $id): ?AdminUser
    {
        $row = $this->fetchOne(
            'SELECT ' . self::COLS . ' FROM admin_users WHERE id = :id',
            ['id' => $id]
        );

        return $row === null ? null : AdminUser::fromRow($row);
    }

    /** @return list<AdminUser> все пользователи */
    public function all(): array
    {
        $rows = $this->fetchAll('SELECT ' . self::COLS . ' FROM admin_users ORDER BY role, login');

        return array_map(static fn (array $r): AdminUser => AdminUser::fromRow($r), $rows);
    }

    public function countOwners(): int
    {
        return (int) ($this->fetchOne("SELECT count(*) AS c FROM admin_users WHERE role = 'owner'")['c'] ?? 0);
    }

    /** Сменить роль (с защитой последнего владельца — вызывающий проверяет). */
    public function setRole(string $id, Role $role): void
    {
        $this->execute('UPDATE admin_users SET role = :role, updated_at = :now WHERE id = :id',
            ['role' => $role->value, 'now' => date('c'), 'id' => $id]);
    }

    public function resetPassword(string $id, string $plainPassword): void
    {
        $this->execute('UPDATE admin_users SET password_hash = :h, updated_at = :now WHERE id = :id',
            ['h' => password_hash($plainPassword, PASSWORD_BCRYPT), 'now' => date('c'), 'id' => $id]);
    }

    public function delete(string $id): void
    {
        $this->execute('DELETE FROM admin_users WHERE id = :id', ['id' => $id]);
    }

    /** Создать или обновить пользователя (по login). Пароль хэшируется, если задан. */
    public function upsert(
        string $login,
        Role $role,
        ?string $plainPassword,
        ?string $firstName = null,
        ?string $lastName = null,
    ): void {
        $existing = $this->findByLogin($login);

        if ($existing === null) {
            $hash = $plainPassword !== null && $plainPassword !== ''
                ? password_hash($plainPassword, PASSWORD_BCRYPT)
                : '';
            $this->execute(
                'INSERT INTO admin_users (id, login, password_hash, role, first_name, last_name, created_at)
                 VALUES (:id, :login, :hash, :role, :fn, :ln, :now)',
                [
                    'id' => 'usr_' . bin2hex(random_bytes(6)),
                    'login' => $login,
                    'hash' => $hash,
                    'role' => $role->value,
                    'fn' => $firstName,
                    'ln' => $lastName,
                    'now' => date('c'),
                ]
            );
            return;
        }

        // Обновление: пароль меняем только если передан новый.
        if ($plainPassword !== null && $plainPassword !== '') {
            $this->execute(
                'UPDATE admin_users SET password_hash = :hash, role = :role, updated_at = :now WHERE id = :id',
                ['hash' => password_hash($plainPassword, PASSWORD_BCRYPT), 'role' => $role->value, 'now' => date('c'), 'id' => $existing->id]
            );
        } else {
            $this->execute(
                'UPDATE admin_users SET role = :role, updated_at = :now WHERE id = :id',
                ['role' => $role->value, 'now' => date('c'), 'id' => $existing->id]
            );
        }
    }
}
