# Платформа Mirai — общий Postgres

Единый Postgres-инстанс для **всех** сайтов хоста. Вынесен из стеков сайтов, чтобы
редеплой любого сайта не трогал базу. Каждый сайт использует **свою database** в этом
инстансе (логическая изоляция), а не отдельный сервер.

## Запуск

```sh
# 1) поднять платформу (создаёт сеть mirai-platform + контейнер mirai-postgres)
docker compose -f docker/platform/docker-compose.yml up -d

# 2) затем поднимать стеки сайтов — они подключаются к сети mirai-platform
docker compose -f docker/docker-compose.yml up -d
```

Порядок важен: платформа поднимается ДО сайтов (сайты используют external-сеть
`mirai-platform`).

## Как подключается сайт

В `docker-compose.yml` сайта:
- сервис php подключается к сетям `default` (для nginx) и `platform` (external, `mirai-platform`);
- приложение ходит в БД по `host=postgres` (сетевой алиас postgres на mirai-platform).

## Новый сайт = новая database

Каждому сайту — своя database и роль. Пример для miraileague:

```sh
docker exec -it mirai-postgres psql -U mirailounge -d postgres -c \
  "CREATE ROLE miraileague LOGIN PASSWORD '***';"
docker exec -it mirai-postgres psql -U mirailounge -d postgres -c \
  "CREATE DATABASE miraileague OWNER miraileague;"
```

Затем сайт указывает свои `POSTGRES_DB/USER/PASSWORD` в своём `.env` и работает
с той же сетью `mirai-platform`.

## Данные и бэкапы

- Том данных: `data/pg` (bind mount на хосте).
- Бэкап всех баз: `docker exec mirai-postgres pg_dumpall -U mirailounge > dump.sql`
- Бэкап одной базы: `docker exec mirai-postgres pg_dump -U mirailounge mirailounge > mirailounge.sql`

## Локальный доступ

Порт проброшен на `127.0.0.1:5432` — для миграций/инструментов с хоста
(`POSTGRES_HOST=127.0.0.1 php vendor/bin/phinx migrate -c phinx.php`).
