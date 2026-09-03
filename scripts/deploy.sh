#!/usr/bin/env bash
#
# Быстрый деплой mirailounge с рабочей машины на сервер (обе — Ubuntu).
#   ./scripts/deploy.sh              — собрать фронт, залить, поднять, миграции
#   ./scripts/deploy.sh --first      — то же + импорт справочников (первый деплой)
#   ./scripts/deploy.sh --dry-run    — показать, что зальётся (rsync -n), без изменений
#   ./scripts/deploy.sh --no-build   — пропустить сборку Vite (если dist уже свежий)
#
# Настройка (один раз): cp scripts/deploy.env.example scripts/deploy.env && отредактировать.
# .env и TLS-серты живут ТОЛЬКО на сервере (в исключениях rsync) — см. scripts/server-bootstrap.sh.
set -euo pipefail
cd "$(dirname "$0")/.."   # корень репозитория

# ── флаги ────────────────────────────────────────────────────────────────
BUILD=1; DRY=0; FIRST=0
for a in "$@"; do
  case "$a" in
    --no-build) BUILD=0 ;;
    --dry-run)  DRY=1 ;;
    --first)    FIRST=1 ;;
    -h|--help)  sed -n '3,9p' "$0"; exit 0 ;;
    *) echo "✗ Неизвестный флаг: $a" >&2; exit 1 ;;
  esac
done

# ── конфиг ───────────────────────────────────────────────────────────────
ENV_FILE="scripts/deploy.env"
if [ -f "$ENV_FILE" ]; then set -a; . "$ENV_FILE"; set +a; fi
SSH_TARGET="${SSH_TARGET:-}"
SSH_PORT="${SSH_PORT:-22}"
REMOTE_PATH="${REMOTE_PATH:-/var/www/mirailounge}"
HEALTH_URL="${HEALTH_URL:-}"

if [ -z "$SSH_TARGET" ]; then
  echo "✗ Не задан SSH_TARGET. Скопируй scripts/deploy.env.example → scripts/deploy.env и заполни." >&2
  exit 1
fi
SSH=(ssh -p "$SSH_PORT" "$SSH_TARGET")

# macOS ставит openrsync — он давится нашими --exclude-from/--include и битыми
# симлинками. Берём настоящий rsync (Homebrew), если он есть.
RSYNC_BIN="rsync"
for c in /opt/homebrew/bin/rsync /usr/local/bin/rsync; do
  [ -x "$c" ] && RSYNC_BIN="$c" && break
done
if "$RSYNC_BIN" --version 2>&1 | grep -qi openrsync; then
  echo "✗ Найден только openrsync (macOS), он не тянет наш деплой." >&2
  echo "  Поставь настоящий rsync:  brew install rsync   — и запусти скрипт снова." >&2
  exit 1
fi

step(){ printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }

# ── 1) сборка фронта (Vite → public/dist) ────────────────────────────────
if [ "$BUILD" = 1 ]; then
  step "Vite build (frontend)"
  ( cd frontend && npm ci --no-audit --no-fund --silent && npm run build )
fi

# ── 2) rsync на сервер ───────────────────────────────────────────────────
# --delete держит сервер в точном соответствии репо; исключения защищают
# БД (data/pg), .env, серты, загрузки. png-ассеты возвращаем include'ами.
RSYNC=(-az --delete
  # uploads/ исключаем ДО широкого include витрины (иначе include перекрыл бы
  # исключение и --delete снёс бы загруженные фото — их владелец www-data).
  --exclude='/public/assets/img/vitrina/uploads/'
  --include='/public/assets/booking/***'
  --include='/public/assets/img/vitrina/***'
  --exclude-from=scripts/deploy-excludes.txt
  -e "ssh -p $SSH_PORT")

if [ "$DRY" = 1 ]; then
  step "rsync --dry-run (ничего не меняем)"
  "$RSYNC_BIN" -n "${RSYNC[@]}" ./ "$SSH_TARGET:$REMOTE_PATH/"
  echo "✓ Это список того, что зальётся. Убери --dry-run для реального деплоя."
  exit 0
fi

step "rsync → $SSH_TARGET:$REMOTE_PATH"
"$RSYNC_BIN" "${RSYNC[@]}" ./ "$SSH_TARGET:$REMOTE_PATH/"

# ── 3) удалённо: платформа + стек + миграции ─────────────────────────────
step "Удалённо: платформа Postgres + стек + миграции"
"${SSH[@]}" bash -se <<REMOTE
set -e
cd "$REMOTE_PATH/docker"
docker compose -f platform/docker-compose.yml up -d      # общий Postgres (создаёт сеть)
docker compose build php                                 # vendor собирается в образе
# Cache-busting ассетов (?v=): свежая версия на каждый деплой. Меняет .env →
# compose пересоздаёт php → заодно свежие opcache и скомпилированный кэш Twig.
STAMP=\$(date +%Y%m%d-%H%M%S)
if grep -q '^MIRAI_APP_VERSION=' ../.env 2>/dev/null; then
  sed -i "s|^MIRAI_APP_VERSION=.*|MIRAI_APP_VERSION=\$STAMP|" ../.env
else
  echo "MIRAI_APP_VERSION=\$STAMP" >> ../.env
fi
docker compose up -d                                     # nginx + php (пересоздаётся из-за .env)
# Первый запуск Postgres инициализирует кластер (~10с) — ждём готовности до миграций.
echo "ждём Postgres…"
for i in \$(seq 1 40); do
  docker exec mirai-postgres pg_isready -U mirailounge -d mirailounge >/dev/null 2>&1 && break
  sleep 1
done
docker compose exec -T php php vendor/bin/phinx migrate -c phinx.php
# Сброс скомпилированного кэша Twig — на случай изменившихся шаблонов.
docker compose exec -T -u root php sh -c 'rm -rf /var/www/mirailounge/var/cache/twig/* 2>/dev/null || true'
# Каталоги загрузок из админок должны писаться php-fpm (www-data), иначе 500 при аплоаде.
docker compose exec -T -u root php sh -c 'for d in menu/uploads gallery/uploads vip/partner_uploads hall/uploads vitrina/uploads; do p=/var/www/mirailounge/public/assets/img/\$d; mkdir -p "\$p"; chown -R www-data:www-data "\$p"; done' 2>/dev/null || true
REMOTE

# ── 4) первый деплой: справочники ────────────────────────────────────────
if [ "$FIRST" = 1 ]; then
  step "Первый деплой: импорт справочников (рекомендатор + витрина)"
  "${SSH[@]}" bash -se <<REMOTE
set -e
cd "$REMOTE_PATH/docker"
docker compose exec -T php php bin/import-recommender
docker compose exec -T php php bin/import-vitrina
REMOTE
  echo "  • Меню из старого JSON:  положи data/*.json на сервер и запусти  bin/import-json  (ОДИН раз)"
  echo "  • Создать администратора:"
  echo "      ssh -p $SSH_PORT $SSH_TARGET \"cd $REMOTE_PATH/docker && docker compose exec php php bin/create-admin <логин> <пароль> owner\""
fi

# ── готово ───────────────────────────────────────────────────────────────
step "Готово ✓"
if [ -n "$HEALTH_URL" ]; then
  code=$(curl -s -o /dev/null -w '%{http_code}' "$HEALTH_URL" || echo "—")
  echo "  health $HEALTH_URL → HTTP $code"
else
  echo "  Проверь сайт вручную (напр. https://mirailounge.ru/_health)."
fi
