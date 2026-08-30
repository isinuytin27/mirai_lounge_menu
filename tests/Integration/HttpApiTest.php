<?php

declare(strict_types=1);

namespace Mirai\Tests\Integration;

use Mirai\Http\AppFactory;
use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Db\Database;
use Mirai\Infrastructure\Security\TableSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * HTTP-контур Menu/Orders через реальный Slim + Postgres.
 * Требует поднятого postgres с применёнными миграциями (см. README.md).
 */
final class HttpApiTest extends TestCase
{
    private ContainerInterface $c;
    private \Slim\App $app;
    private string $tableId = 'ihttp_table';

    protected function setUp(): void
    {
        $this->c = require dirname(__DIR__, 2) . '/src/bootstrap.php';
        try {
            $this->c->get(Database::class)->pdo()->query('SELECT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Postgres недоступен: ' . $e->getMessage());
        }
        $this->app = AppFactory::create($this->c);
        // Rate-limit хранит метку в $_SESSION, живущей весь процесс phpunit —
        // сбрасываем, чтобы тесты не мешали друг другу ложным too_fast.
        $_SESSION = [];
        $this->cleanup();
        $this->c->get(Database::class)->pdo()
            ->prepare('INSERT INTO tables (id, caption, active) VALUES (?, ?, TRUE) ON CONFLICT (id) DO UPDATE SET active = TRUE')
            ->execute([$this->tableId, 'HTTP тест']);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    public function testMenuApiReturnsJson(): void
    {
        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/menu')
        );

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        self::assertTrue($data['ok']);
        self::assertNotEmpty($data['categories']);
    }

    public function testOrderSubmitWithoutTableCookieIsForbidden(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/order-submit')
            ->withParsedBody(['items' => [['id' => 'x', 'qty' => 1]]]);

        $response = $this->app->handle($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('no_table', json_decode((string) $response->getBody(), true)['error']);
    }

    public function testOrderSubmitWithValidTableCookieSucceeds(): void
    {
        $product = $this->c->get(\Mirai\Domain\Menu\MenuRepository::class)->visibleMenu()[0]['products'][0];

        $cfg = $this->c->get(Config::class)->tableSession();
        $token = $this->c->get(TableSession::class)->issue($this->tableId, 'HTTP тест');

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/order-submit')
            ->withCookieParams([$cfg['cookie_name'] => $token])
            ->withParsedBody(['items' => [
                ['id' => $product->slug, 'qty' => 2],
                ['id' => 'nonexistent', 'qty' => 1],
            ]]);

        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        self::assertTrue($data['ok']);
        self::assertNotEmpty($data['order_id']);
        self::assertFalse($data['append']);
    }

    private function cleanup(): void
    {
        $pdo = $this->c->get(Database::class)->pdo();
        $pdo->prepare('DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE table_id = ?)')
            ->execute([$this->tableId]);
        $pdo->prepare('DELETE FROM orders WHERE table_id = ?')->execute([$this->tableId]);
        $pdo->prepare('DELETE FROM tables WHERE id = ?')->execute([$this->tableId]);
    }
}
