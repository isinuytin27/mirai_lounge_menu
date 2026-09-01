<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

use Mirai\Infrastructure\Db\Repository;

/**
 * Витрина кальянов: кальяны (products + витринные поля), чаши, витринные напитки.
 * Отдаёт данные в форме, которую ждёт фронт карусели (design_handoff_vitrina_kalyanov).
 */
final class HookahShowcaseRepository extends Repository implements BowlPricing
{
    /**
     * Наценка чаши: extra (за 1 кальян) × units (число шахт кальяна).
     *
     * @return array{extra:int,name:string,units:int}|null
     */
    public function surcharge(string $productSlug, string $bowlSlug): ?array
    {
        $bowl = $this->fetchOne(
            'SELECT name, extra FROM hookah_bowls WHERE slug = :slug AND active = TRUE',
            ['slug' => $bowlSlug]
        );
        if ($bowl === null) {
            return null;
        }

        $row = $this->fetchOne(
            'SELECT s.anchors FROM hookah_showcase s JOIN products p ON p.id = s.product_id WHERE p.slug = :slug',
            ['slug' => $productSlug]
        );
        $units = 1;
        if ($row !== null) {
            $anchors = json_decode((string) $row['anchors'], true);
            if (is_array($anchors) && $anchors !== []) {
                $units = count($anchors);
            }
        }

        return ['extra' => (int) $bowl['extra'], 'name' => (string) $bowl['name'], 'units' => $units];
    }

    /**
     * @return array{hookahs:list<array<string,mixed>>,bowls:list<array<string,mixed>>,drinks:list<array<string,mixed>>}
     */
    public function all(): array
    {
        return [
            'hookahs' => $this->hookahs(),
            'bowls' => $this->bowls(),
            'drinks' => $this->drinks(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function hookahs(): array
    {
        $rows = $this->fetchAll(
            "SELECT p.slug, p.name, p.price, p.description, p.prep_time,
                    s.image, s.img_w, s.img_h, s.anchors, s.model, s.vol, s.shaft, s.flask, s.heat
             FROM hookah_showcase s
             JOIN products p ON p.id = s.product_id
             WHERE s.active = TRUE AND p.visible = TRUE
             ORDER BY s.sort_order, p.name"
        );

        return array_map(static function (array $r): array {
            $anchors = json_decode((string) $r['anchors'], true);
            /** @var list<array{cx:float,w:float}> $anchorList */
            $anchorList = [];
            if (is_array($anchors)) {
                foreach ($anchors as $a) {
                    if (is_array($a)) {
                        $anchorList[] = ['cx' => (float) ($a['cx'] ?? 0.5), 'w' => (float) ($a['w'] ?? 1)];
                    }
                }
            }

            return [
                'slug' => (string) $r['slug'],
                'name' => (string) $r['name'],
                'price' => (int) $r['price'],
                'desc' => (string) ($r['description'] ?? ''),
                'time' => (string) ($r['prep_time'] ?? ''),
                'model' => (string) ($r['model'] ?? ''),
                'image' => (string) $r['image'],
                'iw' => (int) $r['img_w'],
                'ih' => (int) $r['img_h'],
                'anchors' => $anchorList,
            ];
        }, $rows);
    }

    /** @return list<array<string,mixed>> */
    private function bowls(): array
    {
        $rows = $this->fetchAll(
            'SELECT slug, name, image, img_w, img_h, f, extra, kind
             FROM hookah_bowls WHERE active = TRUE ORDER BY sort_order, id'
        );

        return array_map(static fn (array $r): array => [
            'slug' => (string) $r['slug'],
            'name' => (string) $r['name'],
            'image' => (string) $r['image'],
            'iw' => (int) $r['img_w'],
            'ih' => (int) $r['img_h'],
            'f' => (float) $r['f'],
            'extra' => (int) $r['extra'],
            'kind' => (string) ($r['kind'] ?? ''),
        ], $rows);
    }

    /** @return list<array<string,mixed>> */
    private function drinks(): array
    {
        $rows = $this->fetchAll(
            'SELECT name, image FROM hookah_drinks WHERE active = TRUE ORDER BY sort_order, id'
        );

        return array_map(static fn (array $r): array => [
            'name' => (string) $r['name'],
            'image' => (string) $r['image'],
        ], $rows);
    }

    // ------------------------------------------------------------ админка ----

    /**
     * Кальяны-витрина для админки (со всеми полями + имя/цена товара).
     * @return list<array<string,mixed>>
     */
    public function adminHookahs(): array
    {
        return $this->fetchAll(
            "SELECT s.id, p.slug, p.name, p.price, s.image, s.img_w, s.img_h, s.anchors, s.model, s.active, s.sort_order
             FROM hookah_showcase s JOIN products p ON p.id = s.product_id
             ORDER BY s.sort_order, p.name"
        );
    }

    /** Обновить витринные поля кальяна по id строки. @param array<string,mixed> $d */
    public function updateHookah(int $id, array $d): void
    {
        // anchors: валидируем как JSON-массив {cx,w}; при ошибке не трогаем.
        $anchorsJson = null;
        $parsed = json_decode((string) ($d['anchors'] ?? ''), true);
        if (is_array($parsed)) {
            $anchorsJson = json_encode(array_values($parsed));
        }
        $sql = 'UPDATE hookah_showcase SET image = :image, img_w = :w, img_h = :h, model = :model, active = :active'
            . ($anchorsJson !== null ? ', anchors = CAST(:anchors AS jsonb)' : '')
            . ' WHERE id = :id';
        $params = [
            'image' => (string) ($d['image'] ?? ''),
            'w' => (int) ($d['img_w'] ?? 0),
            'h' => (int) ($d['img_h'] ?? 0),
            'model' => self::nn($d['model'] ?? null),
            'active' => !empty($d['active']) ? 'true' : 'false',
            'id' => $id,
        ];
        if ($anchorsJson !== null) {
            $params['anchors'] = $anchorsJson;
        }
        $this->execute($sql, $params);
    }

    /** @return list<array<string,mixed>> */
    public function adminBowls(): array
    {
        return $this->fetchAll('SELECT id, slug, name, image, img_w, img_h, f, extra, kind, active, sort_order FROM hookah_bowls ORDER BY sort_order, id');
    }

    /** @param array<string,mixed> $d */
    public function saveBowl(?int $id, array $d): void
    {
        if ($id !== null) {
            $this->execute(
                'UPDATE hookah_bowls SET name=:name, image=:image, img_w=:w, img_h=:h, f=:f, extra=:extra, kind=:kind, active=:active WHERE id=:id',
                ['name' => (string) $d['name'], 'image' => (string) ($d['image'] ?? ''), 'w' => (int) ($d['img_w'] ?? 0),
                 'h' => (int) ($d['img_h'] ?? 0), 'f' => (float) ($d['f'] ?? 0.5), 'extra' => (int) ($d['extra'] ?? 0),
                 'kind' => self::nn($d['kind'] ?? null), 'active' => !empty($d['active']) ? 'true' : 'false', 'id' => $id]
            );
            return;
        }
        $this->execute(
            "INSERT INTO hookah_bowls (slug, name, image, img_w, img_h, f, extra, kind, sort_order)
             VALUES (:slug, :name, :image, :w, :h, :f, :extra, :kind,
                     (SELECT COALESCE(MAX(sort_order),-1)+1 FROM hookah_bowls))",
            ['slug' => self::slugify((string) $d['name']), 'name' => (string) $d['name'], 'image' => (string) ($d['image'] ?? ''),
             'w' => (int) ($d['img_w'] ?? 0), 'h' => (int) ($d['img_h'] ?? 0), 'f' => (float) ($d['f'] ?? 0.5),
             'extra' => (int) ($d['extra'] ?? 0), 'kind' => self::nn($d['kind'] ?? null)]
        );
    }

    public function deleteBowl(int $id): void
    {
        $this->execute('DELETE FROM hookah_bowls WHERE id = :id', ['id' => $id]);
    }

    /** @return list<array<string,mixed>> */
    public function adminDrinks(): array
    {
        return $this->fetchAll('SELECT id, name, image, active, sort_order FROM hookah_drinks ORDER BY sort_order, id');
    }

    /** @param array<string,mixed> $d */
    public function saveDrink(?int $id, array $d): void
    {
        if ($id !== null) {
            $this->execute('UPDATE hookah_drinks SET name=:name, image=:image, active=:active WHERE id=:id',
                ['name' => (string) $d['name'], 'image' => (string) ($d['image'] ?? ''), 'active' => !empty($d['active']) ? 'true' : 'false', 'id' => $id]);
            return;
        }
        $this->execute(
            'INSERT INTO hookah_drinks (name, image, sort_order) VALUES (:name, :image, (SELECT COALESCE(MAX(sort_order),-1)+1 FROM hookah_drinks))',
            ['name' => (string) $d['name'], 'image' => (string) ($d['image'] ?? '')]
        );
    }

    public function deleteDrink(int $id): void
    {
        $this->execute('DELETE FROM hookah_drinks WHERE id = :id', ['id' => $id]);
    }

    private static function nn(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private static function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/u', '_', $s) ?? '';
        return trim($s, '_') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
}
