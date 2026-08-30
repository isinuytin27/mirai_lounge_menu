<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\View;

/**
 * Мост между Vite-манифестом и Twig. По имени entry отдаёт HTML-теги <script>/<link>
 * с актуальными хешированными файлами из public/dist. Cache-busting «бесплатный»:
 * имя файла меняется при изменении содержимого.
 */
final class ViteAssets
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $manifest = null;

    public function __construct(
        private readonly string $publicDir,
        private readonly string $base = '/dist/',
    ) {}

    /** HTML-теги (css + js) для entry, напр. "entries/menu.js". */
    public function tags(string $entry): string
    {
        $manifest = $this->manifest();
        if (!isset($manifest[$entry])) {
            // Манифеста нет (фронт не собран) — не падаем, но заметно в консоли.
            return "<!-- vite: entry '{$entry}' не найден в манифесте (запустите npm run build) -->";
        }

        $chunk = $manifest[$entry];
        $html = '';

        foreach ((array) ($chunk['css'] ?? []) as $css) {
            $html .= '<link rel="stylesheet" href="' . $this->url((string) $css) . '">' . "\n";
        }
        if (isset($chunk['file'])) {
            $html .= '<script type="module" src="' . $this->url((string) $chunk['file']) . '"></script>' . "\n";
        }

        return $html;
    }

    /** @return array<string,array<string,mixed>> */
    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        // Vite 5/6 кладёт манифест в .vite/manifest.json; старые версии — в корень dist.
        foreach (['/dist/.vite/manifest.json', '/dist/manifest.json'] as $rel) {
            $path = $this->publicDir . $rel;
            if (is_file($path)) {
                $decoded = json_decode((string) file_get_contents($path), true);
                if (is_array($decoded)) {
                    /** @var array<string,array<string,mixed>> $decoded */
                    return $this->manifest = $decoded;
                }
            }
        }

        return $this->manifest = [];
    }

    private function url(string $file): string
    {
        return rtrim($this->base, '/') . '/' . ltrim($file, '/');
    }
}
