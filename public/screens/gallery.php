<?php
require_once dirname(__DIR__) . "/inc/mirai_asset.php";
?>
<section class="screen gallery">
    <?php
    $galleryItems = [];
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    $jsonPath = (string)($cfg["storage"]["gallery_json_path"] ?? "");

    if ($jsonPath !== "" && is_file($jsonPath)) {
        $raw = file_get_contents($jsonPath);
        $data = $raw ? json_decode($raw, true) : null;
        if (is_array($data) && is_array($data["items"] ?? null)) {
            $galleryItems = $data["items"];
        }
    }

    if (empty($galleryItems)) {
        $galleryItems = [
            ["id" => "pos_1", "image" => "assets/img/interior/1-2.webp", "caption" => "Позиция №1"],
        ];
    }
    ?>

    <div class="gallery-wrap" data-gallery>
        <header class="gallery-headline">
            <h1 class="gallery-title">Галерея</h1>
            <button type="button" class="gallery-nav-up" data-gallery-go-home aria-label="На главную">
                <svg class="gallery-nav-up-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
            </button>
        </header>

        <div class="gallery-grid" data-gallery-grid>
            <?php foreach ($galleryItems as $i => $it): ?>
                <?php
                $src = (string)($it["image"] ?? "");
                $caption = (string)($it["caption"] ?? "Позиция №" . ($i + 1));
                if ($src === "") continue;
                $srcUrl = mirai_asset($src);
                ?>
                <button class="gallery-thumb" type="button" data-gallery-thumb data-idx="<?= (int)$i ?>" data-caption="<?= htmlspecialchars($caption) ?>" aria-label="<?= htmlspecialchars($caption) ?>">
                    <img src="<?= htmlspecialchars($srcUrl, ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($caption) ?>" loading="lazy">
                    <div class="gallery-badge">№<?= (int)($i + 1) ?></div>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="gallery-viewer-backdrop" data-gallery-backdrop hidden></div>
        <div class="gallery-viewer" data-gallery-viewer hidden aria-hidden="true">
            <div class="gallery-viewer-head">
                <div>
                    <div class="gallery-viewer-title" data-gallery-title>Позиция №1</div>
                    <div class="gallery-viewer-sub" data-gallery-sub>1/<?= count($galleryItems) ?></div>
                </div>
                <button class="gallery-viewer-close" type="button" data-gallery-close aria-label="Закрыть">✕</button>
            </div>

            <div class="gallery-viewer-stage" data-gallery-stage>
                <div class="gallery-viewer-track" data-gallery-track>
                    <?php foreach ($galleryItems as $i => $it): ?>
                        <?php
                        $src = (string)($it["image"] ?? "");
                        $caption = (string)($it["caption"] ?? "Позиция №" . ($i + 1));
                        if ($src === "") continue;
                        $srcUrl = mirai_asset($src);
                        ?>
                        <div class="gallery-viewer-slide">
                            <img src="<?= htmlspecialchars($srcUrl, ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($caption) ?>" loading="lazy">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="gallery-viewer-nav">
                    <button class="gallery-viewer-btn prev" type="button" data-gallery-prev aria-label="Предыдущая">‹</button>
                    <button class="gallery-viewer-btn next" type="button" data-gallery-next aria-label="Следующая">›</button>
                </div>
            </div>
        </div>
    </div>

</section>