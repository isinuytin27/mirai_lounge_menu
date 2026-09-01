<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Отдельная страница 3D-карты зала (изометрия view-a/b + столы 11–18). Встраивается
 * в экран брони через iframe — изолирована от SPA (свой CSS/JS/жесты). Данные о
 * занятости и создание брони — через /api/booking/* (booking-store.js + виджет).
 */
final class BookingMapController
{
    public function __invoke(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'booking/map.twig');
    }
}
