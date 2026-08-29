# Интеграционные тесты (требуют Postgres)

Эти тесты гоняются против реального Postgres, поэтому вынесены в отдельный suite
`integration` (см. `phpunit.xml`) и НЕ запускаются в обычном `--testsuite unit`.

## Как запустить локально

```sh
# 1) поднять только Postgres (порт проброшен на 127.0.0.1:5432)
docker compose -f docker/docker-compose.yml up -d postgres

# 2) применить миграции и залить данные (host видит БД на localhost)
POSTGRES_HOST=127.0.0.1 php vendor/bin/phinx migrate -c phinx.php
POSTGRES_HOST=127.0.0.1 php bin/import-json

# 3) прогнать интеграционные тесты
POSTGRES_HOST=127.0.0.1 php vendor/bin/phpunit --testsuite integration
```

В CI Postgres поднимается как service-контейнер; `POSTGRES_HOST` указывает на него.

Верифицировано вручную 2026-08-30: миграции + импорт (счётчики 1:1 с data/*.json),
MenuRepository.visibleMenu (18 категорий / 114 продуктов), findVisibleProduct,
приём заказа (новый + дозаказ к тому же столу через SELECT FOR UPDATE), close.
