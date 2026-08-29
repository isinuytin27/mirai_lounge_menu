<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Infrastructure\Config\Config;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * robots.txt и sitemap.xml — раньше это были отдельные php-файлы; теперь маршруты нового стека.
 */
final class SeoController
{
    public function __construct(private readonly Config $config) {}

    public function robots(Request $request, Response $response): Response
    {
        $origin = $this->origin($request);
        $body = "User-agent: *\nAllow: /\n\nSitemap: {$origin}/sitemap.xml\n";

        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public function sitemap(Request $request, Response $response): Response
    {
        $origin = $this->origin($request);
        $now = date('c');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . "  <url><loc>{$origin}/</loc><lastmod>{$now}</lastmod></url>\n"
            . '</urlset>' . "\n";

        $response->getBody()->write($xml);

        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    /** Origin из canonical_url конфига, иначе из запроса (Host + схема). */
    private function origin(Request $request): string
    {
        $canonical = $this->config->site()['canonical_url'];
        if ($canonical !== '') {
            return rtrim($canonical, '/');
        }

        $uri = $request->getUri();
        $authority = $uri->getHost();
        $port = $uri->getPort();
        if ($port !== null && !in_array($port, [80, 443], true)) {
            $authority .= ':' . $port;
        }

        return $uri->getScheme() . '://' . $authority;
    }
}
