<?php
declare(strict_types=1);

/**
 * Базовые настройки проекта.
 * Пока без БД: админка пишет меню в JSON.
 */

return [
    /*
     * Публичный сайт (SEO). canonical_url пустой — собирается из запроса (Host + HTTPS).
     * og_image_url — полный URL картинки для соцсетей (1200×630 лучше всего); пустой — из текущего origin + og_image_path.
     */
    "site" => [
        "name" => "Mirai Lounge",
        "title" => "Mirai Lounge — лаунж-бар | Меню, бронирование, галерея",
        "description" => "Mirai Lounge: меню кухни и барной карты, бронирование столика, атмосфера лаунжа. Актуальные блюда, напитки и залы.",
        "keywords" => "Mirai Lounge, лаунж, бар, меню, бронирование столика, кальян, коктейли, ресторан",
        "canonical_url" => "",
        "og_image_path" => "favicon.png",
        "twitter_site" => "",
        "theme_color" => "#000000",
    ],

    "admin" => [
        // Список пользователей админки (логин => пароль).
        // Позже вынесем в env/БД/секреты и/или хэширование.
        "users" => [
            "1mp_64" => "2586tolyA",
            "isinuytin27" => "Doccaptian1",
        ],
    ],

    "storage" => [
        // Файл с меню (будущий источник для гостевого меню)
        "menu_json_path" => dirname(__DIR__) . "/data/menu.json",

        // Куда складываем загруженные фото (публичная папка)
        "menu_upload_dir" => dirname(__DIR__) . "/public/assets/img/menu/uploads",
        "menu_upload_public_prefix" => "assets/img/menu/uploads",

        // Галерея
        "gallery_json_path" => dirname(__DIR__) . "/data/gallery.json",
        "gallery_upload_dir" => dirname(__DIR__) . "/public/assets/img/gallery/uploads",
        "gallery_upload_public_prefix" => "assets/img/gallery/uploads",

        // Заказы гостей (JSON). Не коммитить заполненный файл в репозиторий.
        "orders_json_path" => dirname(__DIR__) . "/data/orders.json",
        "push_subscriptions_json_path" => dirname(__DIR__) . "/data/push_subscriptions.json",

        // Корпоратив / VIP (NFC, лимит бара)
        "vip_events_json_path" => dirname(__DIR__) . "/data/vip_events.json",
        "vip_guests_json_path" => dirname(__DIR__) . "/data/vip_guests.json",
        "vip_partner_upload_dir" => dirname(__DIR__) . "/public/assets/img/vip/partner_uploads",
        "vip_partner_upload_public_prefix" => "assets/img/vip/partner_uploads",
    ],

    /*
     * Подпись cookie сессии стола (QR). Смените в продакшене на длинную случайную строку.
     */
    "orders_security" => [
        "table_cookie_name" => "mirai_table",
        "table_cookie_ttl_seconds" => 28800,
        "signing_key" => "change-me-mirai-table-session-key",
    ],

    /*
     * Telegram: новый заказ / дозаказ.
     * Если заданы MIRAI_TG_BOT_TOKEN и MIRAI_TG_CHAT_ID в .env — они важнее значений ниже.
     * Не публикуйте репозиторий с боевым токеном; при утечке — /revoke в @BotFather и новый токен.
     */
    "telegram" => [
        "bot_token" => (string)(getenv("MIRAI_TG_BOT_TOKEN") ?: "8718718166:AAFj2ns-VvmxDcAQ56zkGDuHcIgNv-n8QQY"),
        "chat_id" => (string)(getenv("MIRAI_TG_CHAT_ID") ?: "-5270256930"),
        /** HTTP(S) прокси для запросов к api.telegram.org, например http://127.0.0.1:7890 (если с сервера TG недоступен напрямую) */
        "http_proxy" => trim((string)(getenv("MIRAI_TG_HTTP_PROXY") ?: "")),
    ],

    /*
     * Web Push (VAPID). Сгенерировать: php scripts/generate-vapid.php
     * Пустые ключи — уведомления на телефон не отправляются (Telegram остаётся).
     */
    "webpush" => [
        "subject" => (string)(getenv("MIRAI_VAPID_SUBJECT") ?: "mailto:admin@mirailounge.ru"),
        "public_key" => (string)(getenv("MIRAI_VAPID_PUBLIC") ?: ""),
        "private_key" => (string)(getenv("MIRAI_VAPID_PRIVATE") ?: ""),
    ],
];

