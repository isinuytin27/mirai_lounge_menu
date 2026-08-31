<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

use Mirai\Infrastructure\Db\Repository;

/**
 * Ассоциативный движок гастропар (модель из аддона mirai-booking). Считает сопутствующие
 * товары не по ручным связкам, а по графам категория↔категория и тег↔тег + role-правилам.
 * Заменяет product_pairings: товар несёт лишь категорию (rec_category) и теги, рекомендации
 * выводятся автоматически. Флагман: кальян → авторский чай/чай/закуски.
 *
 * @phpstan-type CatMeta array{tags:list<string>,margin:float,group:string}
 */
final class Recommender extends Repository
{
    /** @var array<string,CatMeta>|null */
    private ?array $categories = null;
    /** @var array<string,float>|null веса пар категорий (симметрично, ключ "a\x1fb") */
    private ?array $catPairs = null;
    /** @var array<string,float>|null веса пар тегов (симметрично) */
    private ?array $tagPairs = null;
    /** @var array<string,mixed>|null */
    private ?array $config = null;

    /**
     * Сопутствующие товары для набора товаров.
     * Возвращает slug => топ-K PairedProduct (по убыванию score).
     *
     * @param list<Product> $products кандидаты и одновременно цели (обычно всё видимое меню)
     * @return array<string,list<PairedProduct>>
     */
    public function pairingsFor(array $products): array
    {
        $this->load();
        $cfg = $this->config ?? [];
        $weights = $cfg['weights'] ?? [];
        $output = $cfg['output'] ?? [];
        $topK = (int) ($output['top_k'] ?? 5);
        $minScore = (float) ($output['min_score'] ?? 0.15);

        $out = [];
        foreach ($products as $target) {
            $scored = [];
            foreach ($products as $cand) {
                if ($cand->slug === $target->slug) {
                    continue;
                }
                $score = $this->score($target, $cand, $weights);
                if ($score >= $minScore) {
                    $scored[] = [$score, $cand];
                }
            }
            usort($scored, static fn (array $x, array $y): int => $y[0] <=> $x[0]);
            $scored = array_slice($scored, 0, $topK);

            $out[$target->slug] = array_map(
                static fn (array $s): PairedProduct => new PairedProduct(
                    $s[1]->slug,
                    $s[1]->name,
                    $s[1]->price,
                    $s[1]->image,
                    'gastro',
                    round($s[0], 3),
                    null,
                ),
                $scored
            );
        }

        return $out;
    }

    /** Итоговый score пары target→candidate по весам модели. */
    private function score(Product $target, Product $cand, array $weights): float
    {
        $ct = $target->recCategory;
        $cc = $cand->recCategory;
        if ($ct === null || $cc === null) {
            return 0.0;
        }
        $catsMeta = $this->categories ?? [];
        $tt = $this->tagsOf($target);
        $tc = $this->tagsOf($cand);

        $score = 0.0;

        // 1) Пара категорий.
        $score += (float) ($weights['category_pair'] ?? 1) * $this->catPairWeight($ct, $cc);

        // 2) Гармония тегов (средний вес рёбер тег↔тег между наборами).
        $score += (float) ($weights['tag_harmony'] ?? 0.7) * $this->tagHarmony($tt, $tc);

        // 3) Role-правила (напр. якорь=кальян → горячее/безалк/шот/лёгкое/снэк).
        $score += (float) ($weights['role_rule'] ?? 0.6) * $this->roleBoost($tt, $tc);

        // 4) Маржа кандидата (легонько тянет к маржинальным позициям).
        $margin = $catsMeta[$cc]['margin'] ?? 0.0;
        $score += (float) ($weights['margin'] ?? 0.25) * $margin;

        // 5) Штраф за ту же категорию (не советуем «коктейль к коктейлю»).
        if ($ct === $cc) {
            $score -= (float) ($weights['same_category_penalty'] ?? 0.6);
        }

        // 6) Кросс-селл предпочитает другую сторону (еда ↔ напиток).
        $score += $this->crossSell($catsMeta[$ct]['group'] ?? '', $catsMeta[$cc]['group'] ?? '');

        return $score;
    }

    /** @return list<string> теги товара: своё переопределение или дефолт категории. */
    private function tagsOf(Product $p): array
    {
        if ($p->recTags !== []) {
            return $p->recTags;
        }
        $cat = $p->recCategory;
        return $cat !== null ? ($this->categories[$cat]['tags'] ?? []) : [];
    }

    private function catPairWeight(string $a, string $b): float
    {
        $pairs = $this->catPairs ?? [];
        return $pairs[$a . "\x1f" . $b] ?? $pairs[$b . "\x1f" . $a] ?? 0.0;
    }

    /**
     * @param list<string> $tt
     * @param list<string> $tc
     */
    private function tagHarmony(array $tt, array $tc): float
    {
        if ($tt === [] || $tc === []) {
            return 0.0;
        }
        $pairs = $this->tagPairs ?? [];
        $sum = 0.0;
        foreach ($tt as $a) {
            foreach ($tc as $b) {
                if ($a === $b) {
                    $sum += 0.3; // одинаковый вкус — умеренный бонус
                    continue;
                }
                $sum += $pairs[$a . "\x1f" . $b] ?? $pairs[$b . "\x1f" . $a] ?? 0.0;
            }
        }
        return $sum / (count($tt) * count($tc));
    }

    /**
     * @param list<string> $tt теги цели
     * @param list<string> $tc теги кандидата
     */
    private function roleBoost(array $tt, array $tc): float
    {
        $rules = $this->config['role_rules'] ?? [];
        $boost = 0.0;
        foreach ($rules as $rule) {
            $ifTarget = (string) ($rule['if_target'] ?? '');
            if ($ifTarget === '' || !in_array($ifTarget, $tt, true)) {
                continue;
            }
            $candTags = $rule['boost_candidate_tags'] ?? [];
            if ($candTags === []) {
                continue;
            }
            $overlap = count(array_intersect($candTags, $tc));
            if ($overlap > 0) {
                $boost += (float) ($rule['w'] ?? 0.5) * ($overlap / count($candTags));
            }
        }
        return $boost;
    }

    private function crossSell(string $groupT, string $groupC): float
    {
        if (($this->config['cross_sell_prefers_other_side'] ?? false) !== true) {
            return 0.0;
        }
        $food = $this->config['food_groups'] ?? [];
        $drink = $this->config['drink_groups'] ?? [];
        $side = static function (string $g) use ($food, $drink): ?string {
            if (in_array($g, $food, true)) {
                return 'food';
            }
            if (in_array($g, $drink, true)) {
                return 'drink';
            }
            return null; // кальян — вне сторон
        };
        $st = $side($groupT);
        $sc = $side($groupC);
        if ($st === null || $sc === null) {
            return 0.0;
        }
        return $st !== $sc ? 0.3 : -0.15;
    }

    private function load(): void
    {
        if ($this->categories !== null) {
            return;
        }

        $this->categories = [];
        foreach ($this->fetchAll('SELECT slug, cat_group, default_tags, margin FROM rec_categories') as $r) {
            $tags = json_decode((string) $r['default_tags'], true);
            $this->categories[(string) $r['slug']] = [
                'tags' => is_array($tags) ? array_values(array_map('strval', $tags)) : [],
                'margin' => (float) $r['margin'],
                'group' => (string) $r['cat_group'],
            ];
        }

        $this->catPairs = [];
        foreach ($this->fetchAll('SELECT a, b, weight FROM rec_category_pairs') as $r) {
            $this->catPairs[$r['a'] . "\x1f" . $r['b']] = (float) $r['weight'];
        }

        $this->tagPairs = [];
        foreach ($this->fetchAll('SELECT a, b, weight FROM rec_tag_pairs') as $r) {
            $this->tagPairs[$r['a'] . "\x1f" . $r['b']] = (float) $r['weight'];
        }

        $row = $this->fetchOne('SELECT data FROM rec_config ORDER BY id LIMIT 1');
        $cfg = $row !== null ? json_decode((string) $row['data'], true) : null;
        $this->config = is_array($cfg) ? $cfg : [];
    }
}
