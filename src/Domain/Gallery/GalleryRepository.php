<?php

declare(strict_types=1);

namespace Mirai\Domain\Gallery;

use Mirai\Infrastructure\Db\Repository;

/** Чтение галереи (таблица gallery_items). Заменяет gallery_storage.php (публичная часть). */
final class GalleryRepository extends Repository
{
    /**
     * Все элементы галереи по порядку.
     *
     * @return list<array{id:string,image:string,caption:?string}>
     */
    public function all(): array
    {
        $rows = $this->fetchAll(
            'SELECT id, image, caption FROM gallery_items ORDER BY sort_order, id'
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'image' => (string) $r['image'],
            'caption' => isset($r['caption']) ? (string) $r['caption'] : null,
        ], $rows);
    }
}
