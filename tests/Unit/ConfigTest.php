<?php

declare(strict_types=1);

namespace Mirai\Tests\Unit;

use Mirai\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    public function testDbDefaultsMatchComposeDevDefaults(): void
    {
        $db = Config::fromArray([])->db();

        self::assertSame('mirailounge', $db['name']);
        self::assertSame('mirailounge', $db['user']);
        self::assertSame('mirailounge_dev', $db['password']);
        self::assertSame('pgsql:host=postgres;port=5432;dbname=mirailounge', $db['dsn']);
    }

    public function testDbReadsPostgresEnv(): void
    {
        $db = Config::fromArray([
            'POSTGRES_HOST' => '127.0.0.1',
            'POSTGRES_PORT' => '55432',
            'POSTGRES_DB' => 'lounge_prod',
            'POSTGRES_USER' => 'appuser',
            'POSTGRES_PASSWORD' => 's3cret',
        ])->db();

        self::assertSame('pgsql:host=127.0.0.1;port=55432;dbname=lounge_prod', $db['dsn']);
        self::assertSame('appuser', $db['user']);
        self::assertSame('s3cret', $db['password']);
    }

    public function testTableSigningKeyFailsClosedInProd(): void
    {
        $config = Config::fromArray(['APP_ENV' => 'prod']);

        $this->expectException(RuntimeException::class);
        $config->tableSession();
    }

    public function testTableSigningKeyHasDevFallbackOutsideProd(): void
    {
        $session = Config::fromArray(['APP_ENV' => 'dev'])->tableSession();

        self::assertNotSame('', $session['signing_key']);
        self::assertSame('mirai_table', $session['cookie_name']);
        self::assertSame(28800, $session['ttl_seconds']);
    }

    public function testProdSigningKeyAcceptedWhenProvided(): void
    {
        $session = Config::fromArray([
            'APP_ENV' => 'prod',
            'MIRAI_TABLE_SIGNING_KEY' => 'a-real-long-random-key',
        ])->tableSession();

        self::assertSame('a-real-long-random-key', $session['signing_key']);
    }

    public function testAppEnvNormalisesToProdByDefault(): void
    {
        self::assertTrue(Config::fromArray([])->isProd());
        self::assertSame('dev', Config::fromArray(['APP_ENV' => 'DEV'])->appEnv());
    }
}
