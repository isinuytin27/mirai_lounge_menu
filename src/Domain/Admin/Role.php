<?php

declare(strict_types=1);

namespace Mirai\Domain\Admin;

/** Роли админ-панели и их права. Порт ADMIN_ROLES + admin_users_role_can_* из старого кода. */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Владелец',
            self::Admin => 'Администратор',
            self::Manager => 'Менеджер',
            self::Staff => 'Сотрудник зала',
        };
    }

    /** Доступ в админ-панель (не только зал). */
    public function isAdminPanel(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager], true);
    }

    public function canManageUsers(): bool
    {
        return $this === self::Owner;
    }

    public function canAccessTickets(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canAccessVip(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Manager], true);
    }

    /** Панель заказов (зал/бар/кухня) — доступна всем ролям, включая сотрудника зала. */
    public function canAccessOrders(): bool
    {
        return true;
    }

    /** Безопасный разбор строки роли (неизвестное -> staff). */
    public static function fromString(?string $role): self
    {
        return self::tryFrom((string) $role) ?? self::Staff;
    }
}
