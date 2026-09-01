<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Orders\OrderRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Панель заказов зала/бара/кухни (порт старого public/orders/). Персонал видит открытые
 * и закрытые заказы, деталь с группировкой по линиям выдачи, текст для кухни, закрытие.
 * За нашей Auth (роль orders — все роли, включая сотрудника зала).
 */
final class OrdersPanelController
{
    public function __construct(private readonly OrderRepository $orders) {}

    /** GET /orders — список заказов. */
    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'orders/index.twig', [
            'orders' => $this->orders->listForStaff(),
        ]);
    }

    /** GET /orders/{id} — деталь заказа. */
    public function show(Request $request, Response $response, array $args): Response
    {
        $order = $this->orders->find((string) $args['id']);
        if ($order === null) {
            return $response->withStatus(302)->withHeader('Location', '/orders?err=notfound');
        }

        return Twig::fromRequest($request)->render($response, 'orders/show.twig', [
            'order' => $order,
            'groups' => $order->groupByLine(),
            'kitchen_text' => $order->kitchenText(),
        ]);
    }

    /** POST /orders/{id}/close — закрыть заказ. */
    public function close(Request $request, Response $response, array $args): Response
    {
        $this->orders->close((string) $args['id']);

        return $response->withStatus(302)->withHeader('Location', '/orders/' . rawurlencode((string) $args['id']));
    }
}
