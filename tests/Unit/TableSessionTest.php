<?php

declare(strict_types=1);

namespace Mirai\Tests\Unit;

use Mirai\Infrastructure\Security\TableSession;
use PHPUnit\Framework\TestCase;

final class TableSessionTest extends TestCase
{
    private function session(): TableSession
    {
        return new TableSession('test-signing-key-abcdef', 3600);
    }

    public function testRoundTrip(): void
    {
        $s = $this->session();
        $token = $s->issue('pos_1', 'Стол №6', now: 1_000);
        $payload = $s->verify($token, now: 1_100);

        self::assertNotNull($payload);
        self::assertSame('pos_1', $payload['tid']);
        self::assertSame('Стол №6', $payload['cap']);
        self::assertSame(1_000 + 3600, $payload['exp']);
    }

    public function testExpiredTokenRejected(): void
    {
        $s = $this->session();
        $token = $s->issue('pos_1', 'Стол №6', now: 1_000);

        self::assertNull($s->verify($token, now: 1_000 + 3600 + 1));
    }

    public function testTamperedBodyRejected(): void
    {
        $s = $this->session();
        $token = $s->issue('pos_1', 'Стол №6', now: 1_000);
        [$body, $sig] = explode('.', $token, 2);
        $forged = $body . 'x.' . $sig;

        self::assertNull($s->verify($forged, now: 1_100));
    }

    public function testWrongKeyRejected(): void
    {
        $token = (new TableSession('key-A', 3600))->issue('pos_1', 'Стол №6', now: 1_000);
        $other = new TableSession('key-B', 3600);

        self::assertNull($other->verify($token, now: 1_100));
    }

    public function testEmptyKeyNeverVerifies(): void
    {
        $s = new TableSession('', 3600);
        // с пустым ключом даже собственный токен не проходит (fail-closed на уровне verify)
        $token = $s->issue('pos_1', 'x', now: 1_000);

        self::assertNull($s->verify($token, now: 1_100));
    }

    public function testGarbageTokensRejected(): void
    {
        $s = $this->session();

        self::assertNull($s->verify('', now: 1_100));
        self::assertNull($s->verify('nodot', now: 1_100));
        self::assertNull($s->verify('.onlysig', now: 1_100));
        self::assertNull($s->verify('onlybody.', now: 1_100));
    }

    public function testCaptionFallsBackToTid(): void
    {
        $s = $this->session();
        $token = $s->issue('pos_9', '', now: 1_000);
        $payload = $s->verify($token, now: 1_100);

        self::assertNotNull($payload);
        self::assertSame('pos_9', $payload['cap']);
    }
}
