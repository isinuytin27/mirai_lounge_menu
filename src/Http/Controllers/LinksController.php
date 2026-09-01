<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Служебная страница ссылок для сотрудников (link-in-bio: команда, каналы, адреса).
 * Порт public/links/ — контент статичный, отрендерён в links.twig. noindex.
 */
final class LinksController
{
    public function __invoke(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'links.twig');
    }
}
