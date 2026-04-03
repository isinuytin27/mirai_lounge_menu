# Docker: Nginx + PHP-FPM

Один сайт: **mirailounge.ru** (гостевое меню, админка, заказы).

**Деплой на сервер (копирование папки, SSL, данные меню):** в корне проекта — **`DEPLOY.md`**.

## Быстрый старт

1. Установите Docker (см. **WINDOWS-SETUP.md** — пошаговый гайд)
2. Один раз: **prepare-deploy.bat** (создаст `.env` из шаблона, напомнит про `config.php`)
3. Для **HTTPS** (mirailounge.ru): положите сертификаты Reg.ru в `docker/ssl/`, затем **ssl/setup-certs.bat**
4. Запустите **start.bat** или **run.bat**
5. Для локальной проверки добавьте **mirailounge.ru** в hosts
6. Сайт: https://mirailounge.ru (после сертификатов) или http://localhost

## Требования

- Windows 10/11 (build 19041+)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) с WSL2
- Порты 80 и 443 свободны

## Структура

```
new_mirai_lounge_menu/
├── docker/
│   ├── docker-compose.yml
│   ├── nginx/
│   │   ├── nginx.conf
│   │   └── conf.d/
│   │       ├── mirailounge.conf
│   │       └── 00-ssl-mirailounge.conf
│   └── php/
│       └── Dockerfile
├── public/              гостевой сайт, API, orders
├── admin/               админка (в т.ч. assets/js/dashboard.js, assets/css/admin.css)
├── config/
├── data/
└── vendor/              после composer install (Web Push)
```

Описание папок и правил по фронту (без инлайн-CSS/JS в шаблонах): **README** в корне репозитория.

## Запуск

Из корня проекта:

```powershell
docker compose -f docker/docker-compose.yml up -d
```

Первый запуск соберёт PHP-образ (1–2 минуты).

## Проверка

- **mirailounge.ru** → http://localhost (или добавьте домен в hosts)

### Локальный hosts (для теста)

```
127.0.0.1  mirailounge.ru
127.0.0.1  www.mirailounge.ru
```

Файл: `C:\Windows\System32\drivers\etc\hosts` (от имени администратора).

## Остановка

```powershell
docker compose -f docker/docker-compose.yml down
```

## Web Push (VAPID) без PHP на ПК

После **`docker\start.bat`** запустите **`docker\gen-vapid.bat`** — скрипт выполняется в контейнере **mirai-php**. Строки **`MIRAI_VAPID_PUBLIC`** и **`MIRAI_VAPID_PRIVATE`** добавьте в **`.env`** и перезапустите стек. Если образ PHP собирали давно, выполните **`docker compose -f docker/docker-compose.yml build php`**.

## SSL (HTTPS)

Сертификаты Reg.ru и скрипт сборки: **docker/ssl/README.md**. Конфиг с `listen 443 ssl` уже в **nginx/conf.d/00-ssl-mirailounge.conf**.

## Права на запись (data, uploads)

На Windows права обычно не мешают. Если при загрузке фото или сохранении `menu.json` возникают ошибки:

- Проверьте папки `data/`, `public/assets/img/menu/uploads/`, `public/assets/img/gallery/uploads/`
- В Docker Desktop: Settings → Resources → File sharing — добавьте диск с проектом

## Логи

```powershell
docker compose -f docker/docker-compose.yml logs -f nginx
docker compose -f docker/docker-compose.yml logs -f php
```
