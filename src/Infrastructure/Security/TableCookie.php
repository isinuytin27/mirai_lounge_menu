<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Security;

use Mirai\Infrastructure\Config\Config;

/**
 * Сборка Set-Cookie для сессии стола. Общая для QR-входа (/t и /?table=),
 * чтобы логика выдачи cookie не дублировалась.
 */
final class TableCookie
{
    public function __construct(
        private readonly TableSession $session,
        private readonly Config $config,
    ) {}

    public function name(): string
    {
        return $this->config->tableSession()['cookie_name'];
    }

    /** Значение заголовка Set-Cookie с подписанным токеном стола. */
    public function header(string $tableId, string $caption, bool $https): string
    {
        $cfg = $this->config->tableSession();
        $token = $this->session->issue($tableId, $caption);
        $ttl = $cfg['ttl_seconds'];

        $parts = [
            $cfg['cookie_name'] . '=' . $token,
            'Expires=' . gmdate('D, d-M-Y H:i:s T', time() + $ttl),
            'Max-Age=' . $ttl,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        ];
        if ($https) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }
}
