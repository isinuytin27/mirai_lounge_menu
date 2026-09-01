<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Экран-витрина выбора кальяна (standalone). Данные тянет из /api/hookah-showcase.
 * Открывается при заходе в раздел «Кальян» и как отдельный роут /vitrina.
 */
final class VitrinaController
{
    public function __invoke(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'vitrina.twig');
    }
}
