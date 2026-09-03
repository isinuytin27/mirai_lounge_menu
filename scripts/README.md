# Быстрый деплой (рабочая машина → сервер, обе Ubuntu)

Прямой `rsync + ssh` без GitHub Actions. Один раз настраиваешь — потом деплой одной командой.

## Настройка (один раз)

```bash
# 1) на рабочей машине — доступы к серверу
cp scripts/deploy.env.example scripts/deploy.env
nano scripts/deploy.env          # SSH_TARGET / SSH_PORT / REMOTE_PATH

# 2) подготовить сервер (Docker + каталог + .env со сгенерированными ключами)
scp -P <порт> scripts/server-bootstrap.sh deploy@<сервер>:/tmp/
ssh -t -p <порт> deploy@<сервер> "sudo bash /tmp/server-bootstrap.sh"   # -t обязателен для sudo

# 3) положить TLS-серты на сервер (rsync их не несёт — живут только там):
#    /var/www/mirailounge/docker/ssl/mirailounge.ru/{fullchain,privkey}.pem
```

Сеть/SSH/firewall свежего сервера (проброс 80/443, SSH-порт, ufw, fail2ban) —
если ещё не сделаны, см. `SERVER_SETUP.md`, Часть I. Это разовая OS-настройка.

## Деплой

```bash
./scripts/deploy.sh            # собрать фронт → залить → поднять стек → миграции
./scripts/deploy.sh --first    # + импорт справочников (рекомендатор, витрина) — ПЕРВЫЙ раз
./scripts/deploy.sh --dry-run  # показать, что зальётся, ничего не меняя
./scripts/deploy.sh --no-build # без пересборки Vite (dist уже свежий)
```

Что делает `deploy.sh`:
1. `npm run build` (Vite → `public/dist/`).
2. `rsync -az --delete` на сервер (исключения защищают БД `data/pg`, `.env`, серты, загрузки; png-ассеты возвращаются include'ами).
3. Удалённо: `docker compose` платформа Postgres → сборка php → подъём → `phinx migrate`.
4. С `--first` — ещё импорт справочников; подсказывает про `import-json` и `create-admin`.

## Первый деплой целиком

```bash
./scripts/deploy.sh --first
# затем создать администратора:
ssh -p <порт> deploy@<сервер> \
  "cd /var/www/mirailounge/docker && docker compose exec php php bin/create-admin <логин> <пароль> owner"
# (если есть старые данные) положить data/*.json на сервер и один раз:
#   docker compose exec -T php php bin/import-json
```

## Откат / ручные команды на сервере

```bash
cd /var/www/mirailounge/docker
docker compose ps
docker compose logs -f php
docker compose exec -T php php vendor/bin/phinx rollback -c phinx.php   # откат миграции
docker compose restart php nginx
```

> ⚠️ `deploy.sh` использует `rsync --delete` с `scripts/deploy-excludes.txt`. Никогда не
> гоняй ручной `rsync --delete` без этого файла исключений — снесёт БД/секреты. Сомневаешься —
> сперва `--dry-run`.
