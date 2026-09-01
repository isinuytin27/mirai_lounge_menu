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
}
