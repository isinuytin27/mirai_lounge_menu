<?php
require_once dirname(__DIR__) . "/inc/mirai_asset.php";
?>
<section class="screen home">

    <div class="home-content">

        <div class="home-center">
            <img src="<?= htmlspecialchars(mirai_asset("assets/img/logo/logo-vert.svg"), ENT_QUOTES, "UTF-8") ?>" class="logo" alt="Mirai Lounge">

            <div class="home-swipe-hint">
                <p class="home-swipe-hint-text">Свайп в сторону раздела или нажмите на название</p>
                <span class="home-swipe-hint-motion" aria-hidden="true">
                    <span class="home-swipe-hint-track"></span>
                    <span class="home-swipe-hint-marker"></span>
                </span>
            </div>
        </div>

        <div class="nav-hints" role="navigation" aria-label="Разделы сайта">

            <button type="button" class="hint hint-top" data-nav-x="1" data-nav-y="0">О НАС</button>

            <button type="button" class="hint hint-left" data-nav-x="0" data-nav-y="1">БРОНЬ</button>

            <button type="button" class="hint hint-right" data-nav-x="2" data-nav-y="1">МЕНЮ</button>

            <button type="button" class="hint hint-bottom" data-nav-x="1" data-nav-y="2">ГАЛЕРЕЯ</button>

        </div>

    </div>

</section>