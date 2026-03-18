<?php
declare(strict_types=1);

/**
 * Базовые настройки проекта.
 * Пока без БД: админка пишет меню в JSON.
 */

return [
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
    ],
];

