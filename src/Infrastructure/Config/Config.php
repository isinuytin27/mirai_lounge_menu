<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Config;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Единый источник конфигурации. Грузит .env один раз и отдаёт типизированные секции.
 *
 * Заменяет старую глобальную admin_config() и 14+ повторных `require config/config.php`.
 * Секреты берутся ТОЛЬКО из окружения (.env / env_file docker); в коде их нет.
 */
final class Config
{
    /** @param array<string,mixed> $env снимок окружения (имя => значение) */
    private function __construct(private readonly array $env) {}

    /**
     * Собирает конфиг: подхватывает .env из корня проекта (если есть), затем читает окружение.
     * .env не обязателен — в docker переменные приходят через env_file, а дефолты заданы ниже.
     */
    public static function load(string $projectRoot): self
    {
        if (is_file($projectRoot . '/.env')) {
            // safeLoad: не падаем, если .env отсутствует или частично заполнен.
            Dotenv::createImmutable($projectRoot)->safeLoad();
        }

        // Снимок из всех источников (getenv + $_ENV + $_SERVER), $_ENV имеет приоритет.
        $env = [];
        foreach (getenv() as $k => $v) {
            $env[$k] = $v;
        }
        foreach ($_SERVER as $k => $v) {
            if (is_string($v)) {
                $env[$k] = $v;
            }
        }
        foreach ($_ENV as $k => $v) {
            if (is_string($v)) {
                $env[$k] = $v;
            }
        }

        return new self($env);
    }

    /** Явный конструктор для тестов. @param array<string,mixed> $env */
    public static function fromArray(array $env): self
    {
        return new self($env);
    }

    private function str(string $key, string $default = ''): string
    {
        $v = $this->env[$key] ?? null;
        return is_string($v) && $v !== '' ? $v : $default;
    }

    private function int(string $key, int $default): int
    {
        $v = $this->env[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function appVersion(): string
    {
        return $this->str('MIRAI_APP_VERSION', '0.2.0-dev');
    }

    public function appEnv(): string
    {
        $e = strtolower($this->str('APP_ENV', 'prod'));
        return $e === 'dev' ? 'dev' : 'prod';
    }

    public function isProd(): bool
    {
        return $this->appEnv() === 'prod';
    }

    /**
     * Публичный сайт (SEO). Не секреты — дефолты живут в коде, при желании переопределяются env.
     *
     * @return array{name:string,title:string,description:string,keywords:string,canonical_url:string,og_image_path:string,twitter_site:string,theme_color:string}
     */
    public function site(): array
    {
        return [
            'name' => $this->str('MIRAI_SITE_NAME', 'Mirai Lounge'),
            'title' => $this->str('MIRAI_SITE_TITLE', 'Mirai Lounge — лаунж-бар | Меню, бронирование, галерея'),
            'description' => $this->str('MIRAI_SITE_DESCRIPTION', 'Mirai Lounge: меню кухни и барной карты, бронирование столика, атмосфера лаунжа. Актуальные блюда, напитки и залы.'),
            'keywords' => $this->str('MIRAI_SITE_KEYWORDS', 'Mirai Lounge, лаунж, бар, меню, бронирование столика, кальян, коктейли, ресторан'),
            'canonical_url' => $this->str('MIRAI_SITE_CANONICAL', ''),
            'og_image_path' => $this->str('MIRAI_SITE_OG_IMAGE', 'favicon.png'),
            'twitter_site' => $this->str('MIRAI_SITE_TWITTER', ''),
            'theme_color' => $this->str('MIRAI_SITE_THEME_COLOR', '#000000'),
        ];
    }

    /**
     * Подключение к БД (Postgres). Дефолты совпадают с docker-compose, чтобы bare `up` работал.
     * В проде креды приходят из .env (POSTGRES_*).
     *
     * @return array{dsn:string,user:string,password:string,host:string,port:int,name:string}
     */
    public function db(): array
    {
        $host = $this->str('POSTGRES_HOST', 'postgres');
        $port = $this->int('POSTGRES_PORT', 5432);
        $name = $this->str('POSTGRES_DB', 'mirailounge');
        $user = $this->str('POSTGRES_USER', 'mirailounge');
        $password = $this->str('POSTGRES_PASSWORD', 'mirailounge_dev');

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $name);

        return compact('dsn', 'user', 'password', 'host', 'port', 'name');
    }

    /**
     * Сессия стола (QR). FAIL-CLOSED: в проде пустой ключ подписи — фатальная ошибка,
     * а не подделываемые куки (устраняет landmine старого "change-me-..." fallback).
     *
     * @return array{cookie_name:string,ttl_seconds:int,signing_key:string}
     */
    public function tableSession(): array
    {
        $key = $this->str('MIRAI_TABLE_SIGNING_KEY', '');

        if ($key === '') {
            if ($this->isProd()) {
                throw new RuntimeException(
                    'MIRAI_TABLE_SIGNING_KEY не задан. В проде это дыра (подделка куки стола). '
                    . 'Сгенерируйте: php -r "echo bin2hex(random_bytes(32));" и пропишите в .env.'
                );
            }
            // Только dev: фиксированный НЕсекретный ключ, чтобы локально всё работало.
            $key = 'dev-insecure-table-signing-key-do-not-use-in-prod';
        }

        return [
            'cookie_name' => $this->str('MIRAI_TABLE_COOKIE_NAME', 'mirai_table'),
            'ttl_seconds' => $this->int('MIRAI_TABLE_COOKIE_TTL', 28800),
            'signing_key' => $key,
        ];
    }

    /**
     * Telegram. Пустой токен = отправка отключена (не ошибка).
     *
     * @return array{bot_token:string,chat_id:string,api_base:string,http_proxy:string}
     */
    public function telegram(): array
    {
        return [
            'bot_token' => $this->str('MIRAI_TG_BOT_TOKEN', ''),
            'chat_id' => $this->str('MIRAI_TG_CHAT_ID', ''),
            'api_base' => $this->str('MIRAI_TG_API_BASE', 'https://api.telegram.org'),
            'http_proxy' => trim($this->str('MIRAI_TG_HTTP_PROXY', '')),
        ];
    }

    /**
     * Web Push (VAPID). Пустые ключи = push отключён.
     *
     * @return array{subject:string,public_key:string,private_key:string}
     */
    public function webpush(): array
    {
        return [
            'subject' => $this->str('MIRAI_VAPID_SUBJECT', 'mailto:admin@mirailounge.ru'),
            'public_key' => $this->str('MIRAI_VAPID_PUBLIC', ''),
            'private_key' => $this->str('MIRAI_VAPID_PRIVATE', ''),
        ];
    }
}
