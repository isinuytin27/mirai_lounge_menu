# Деплой Mirai Lounge

Актуальная инструкция под новый стек (**Slim 4 + Postgres + Vite**, единый front controller).
Коротко и по шагам. Глубокий референс по ОС/сети/firewall свежего сервера — в
[`SERVER_SETUP.md`](SERVER_SETUP.md), Часть I.

| | |
|---|---|
| **Стек** | Slim 4 · Twig · Phinx · Postgres 16 · Vite 6 · Docker Compose |
| **Хост** | Ubuntu, за NAT (`87.251.104.230` → роутер → сервер), вход по паролю |
| **Деплой** | GitHub Actions + **self-hosted runner** (push → авто-деплой); ручной фолбэк `scripts/deploy.sh` |
| **Данные** | Postgres в томе `data/pg`; `rsync --delete` их НЕ трогает (исключения) |

---

## Два пути деплоя

1. **Основной — GitHub Actions.** Push в `main` → CI на GitHub (тесты, сборка) →
   CD на self-hosted runner **на сервере** (локальный rsync + docker compose + миграции).
   Ни SSH, ни секретов, ни проброса — runner сам ходит исходящим HTTPS в GitHub.
2. **Фолбэк — вручную** с рабочей машины: `./scripts/deploy.sh` (rsync + ssh),
   когда GitHub недоступен. Настройка — [`scripts/README.md`](scripts/README.md).

---

## Разовая настройка (первый раз)

### 1. Сервер (ОС, сеть, доступ)
Если сервер ещё «сырой» — netplan, статический адрес, проброс 80/443, SSH на
нестандартном порту, `ufw`, `fail2ban`: см. [`SERVER_SETUP.md`](SERVER_SETUP.md), Часть I.
Нужен пользователь `deploy` (в группе `sudo`).

### 2. Подготовка под приложение — одной командой
```bash
# с рабочей машины (обе — Ubuntu)
scp -P <ssh-порт> scripts/server-bootstrap.sh deploy@<сервер>:/tmp/
ssh -t -p <ssh-порт> deploy@<сервер> "sudo bash /tmp/server-bootstrap.sh"   # -t: sudo нужен TTY
```
Скрипт ставит Docker + лимиты логов, создаёт `/var/www/mirailounge` и генерирует
`.env` со **случайными** `MIRAI_TABLE_SIGNING_KEY` и паролём Postgres (fail-closed:
без ключа prod не поднимется).

### 3. TLS-сертификаты (Reg.ru, без Let's Encrypt)
```bash
# положить на сервер (rsync их НЕ несёт — живут только там):
/var/www/mirailounge/docker/ssl/mirailounge.ru/fullchain.pem   # сертификат + цепочка CA
/var/www/mirailounge/docker/ssl/mirailounge.ru/privkey.pem     # приватный ключ (chmod 600)
```

### 4. Self-hosted runner (для авто-деплоя)
На сервере под `deploy` (**Settings → Actions → Runners → New self-hosted runner** даст токен):
```bash
mkdir -p ~/actions-runner && cd ~/actions-runner
# скачать актуальный runner с GitHub, распаковать, затем:
./config.sh --url https://github.com/isinuytin27/mirai_lounge_menu \
  --token <токен из интерфейса> \
  --labels self-hosted,mirai-prod --unattended
sudo ./svc.sh install deploy && sudo ./svc.sh start   # автозапуск с машиной
```
В Settings → Actions → Runners появится строка со статусом **Idle**. Секреты
`DEPLOY_*` не нужны — можно удалить.

---

## Первый деплой

```bash
# смёрджить ветку в main → пайплайн соберёт и выложит сам
git checkout main && git merge rebuild/foundation && git push
```
После того как стек поднялся — **разово** наполнить справочники (на сервере):
```bash
cd /var/www/mirailounge/docker
docker compose exec -T php php bin/import-recommender   # граф гастропар
docker compose exec -T php php bin/import-vitrina       # витрина кальянов (кальяны/чаши/напитки)
# если есть старые данные — положить data/*.json и ОДИН раз:
docker compose exec -T php php bin/import-json
# первый администратор:
docker compose exec php php bin/create-admin <логин> <пароль> owner
```
> Миграции (`phinx migrate`) применяет сам пайплайн на каждый деплой. Импорт
> данных в пайплайн НЕ входит — он разовый (повтор `import-json` затрёт правки из админки).

---

## Обычный деплой

**Просто пуш:**
```bash
git push            # в main → GitHub Actions соберёт и выложит на сервер
```
**Или вручную** (фолбэк, с рабочей машины):
```bash
./scripts/deploy.sh              # собрать фронт → залить → поднять → миграции
./scripts/deploy.sh --dry-run    # посмотреть, что зальётся, ничего не меняя
```

---

## Проверка

```bash
curl -I https://mirailounge.ru/            # 200/301
curl -s https://mirailounge.ru/_health     # {"status":"ok"}
```
Руками: `/` (витрина+меню), `/vitrina` (карусель кальянов), `/booking/map` (3D-зал),
`/orders` (панель заказов), `/admin` (вход).

---

## Эксплуатация

```bash
cd /var/www/mirailounge/docker
docker compose ps
docker compose logs -f php
docker compose restart php nginx
docker compose exec -T php php vendor/bin/phinx rollback -c phinx.php   # откат миграции
docker exec -it mirai-postgres psql -U mirailounge -d mirailounge       # psql

# бэкап БД (по cron)
docker exec mirai-postgres pg_dumpall -U mirailounge | gzip > ~/backups/pg-$(date +%F).sql.gz
```

## Частые проблемы
| Симптом | Причина / лечение |
|---|---|
| 500 на всех страницах | Пустой `MIRAI_TABLE_SIGNING_KEY` при `APP_ENV=prod` (fail-closed). Проверь `.env` |
| `could not translate host name "postgres"` | Платформа не поднята: `docker compose -f platform/docker-compose.yml up -d` |
| `password authentication failed` | `POSTGRES_PASSWORD` менялся после инициализации тома — чинить `ALTER ROLE` |
| Страницы без стилей | Нет `public/dist/` — артефакт/сборка Vite не доехали |
| Витрина пустая | Не запускали `bin/import-vitrina` |
| Job висит в очереди | Runner offline: `sudo ~/actions-runner/svc.sh status` |

---

## Что деплой НЕ перезаписывает (см. `scripts/deploy-excludes.txt`)
Том Postgres (`data/pg`), `.env`, TLS-серты (`docker/ssl/`), загрузки из админок
(`*/uploads/`), локальный `scripts/deploy.env`. png-ассеты меню/карты/витрины
возвращаются в выкладку явными `--include`.
