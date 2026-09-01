<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Menu\TopSalesRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Курирование «Хитов продаж» (стрип вверху меню): добавить товар, бейдж/заметка,
 * порядок, вкл/выкл, удалить. За ролью admin_panel.
 */
final class TopSalesAdminController
{
    public function __construct(private readonly TopSalesRepository $repo) {}

    public function index(Request $request, Response $response): Response
    {
        $data = $this->repo->adminAll();

        return Twig::fromRequest($request)->render($response, 'admin/top-sales/index.twig', [
            'items' => $data['items'],
            'available' => $data['available'],
            'flash' => $request->getQueryParams()['ok'] ?? null,
        ]);
    }

    public function add(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $pid = (int) ($b['product_id'] ?? 0);
        if ($pid > 0) {
            $this->repo->add($pid, (string) ($b['badge'] ?? ''), (string) ($b['note'] ?? ''));
        }

        return $this->back($response, 'Добавлено');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $this->repo->update((int) $args['id'], (string) ($b['badge'] ?? ''), (string) ($b['note'] ?? ''), isset($b['active']));

        return $this->back($response, 'Сохранено');
    }

    public function move(Request $request, Response $response, array $args): Response
    {
        $this->repo->move((int) $args['id'], (string) $args['dir']);

        return $this->back($response, 'Порядок изменён');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->repo->delete((int) $args['id']);

        return $this->back($response, 'Удалено');
    }

    private function back(Response $response, string $msg): Response
    {
        return $response->withStatus(302)->withHeader('Location', '/admin/top-sales?ok=' . rawurlencode($msg));
    }
}
