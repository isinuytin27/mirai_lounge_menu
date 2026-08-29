<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Security;

/**
 * Подписанная сессия стола (QR-заказ). Порт старого public/inc/mirai_table_session.php,
 * но крипто отделено от HTTP/куки: здесь только выпуск и проверка токена.
 *
 * Токен: base64url(json{tid,cap,exp}) . hmac_sha256(body, key). Проверка через hash_equals.
 * Ре-валидация tid против реальных столов (tables) — забота вызывающего слоя, не крипто.
 */
final class TableSession
{
    public function __construct(
        private readonly string $signingKey,
        private readonly int $ttlSeconds = 28800,
    ) {}

    /** Выпустить токен для стола. $now — для тестов. */
    public function issue(string $tableId, string $caption, ?int $now = null): string
    {
        $now ??= time();
        $payload = [
            'tid' => $tableId,
            'cap' => $caption,
            'exp' => $now + $this->ttlSeconds,
        ];
        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $body = $this->b64urlEncode($json);
        $sig = hash_hmac('sha256', $body, $this->signingKey);

        return $body . '.' . $sig;
    }

    /**
     * Проверить токен: подпись + срок. Возвращает payload или null.
     *
     * @return array{tid:string,cap:string,exp:int}|null
     */
    public function verify(string $token, ?int $now = null): ?array
    {
        $now ??= time();

        if ($this->signingKey === '' || $token === '') {
            return null;
        }

        $dot = strrpos($token, '.');
        if ($dot === false || $dot === 0 || $dot === strlen($token) - 1) {
            return null;
        }

        $body = substr($token, 0, $dot);
        $sig = substr($token, $dot + 1);
        $expected = hash_hmac('sha256', $body, $this->signingKey);
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $json = $this->b64urlDecode($body);
        if ($json === false) {
            return null;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < $now) {
            return null;
        }

        $tid = trim((string) ($payload['tid'] ?? ''));
        if ($tid === '') {
            return null;
        }
        $cap = trim((string) ($payload['cap'] ?? ''));
        if ($cap === '') {
            $cap = $tid;
        }

        return ['tid' => $tid, 'cap' => $cap, 'exp' => $exp];
    }

    private function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $b64): string|false
    {
        $pad = strlen($b64) % 4;
        if ($pad !== 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        return base64_decode(strtr($b64, '-_', '+/'), true);
    }
}
