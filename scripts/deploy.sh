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

step(){ printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }

# ── 1) сборка фронта (Vite → public/dist) ────────────────────────────────
if [ "$BUILD" = 1 ]; then
  step "Vite build (frontend)"
  ( cd frontend && npm ci --no-audit --no-fund --silent && npm run build )
fi

# ── 2) rsync на сервер ───────────────────────────────────────────────────
# --delete держит сервер в точном соответствии репо; исключения защищают
# БД (data/pg), .env, серты, загрузки. png-ассеты возвращаем include'ами.
RSYNC=(rsync -az --delete
  --include='/public/assets/booking/***'
  --include='/public/assets/img/vitrina/***'
  --exclude-from=scripts/deploy-excludes.txt
  -e "ssh -p $SSH_PORT")

if [ "$DRY" = 1 ]; then
  step "rsync --dry-run (ничего не меняем)"
  rsync -n "${RSYNC[@]}" ./ "$SSH_TARGET:$REMOTE_PATH/"
  echo "✓ Это список того, что зальётся. Убери --dry-run для реального деплоя."
  exit 0
fi

step "rsync → $SSH_TARGET:$REMOTE_PATH"
rsync "${RSYNC[@]}" ./ "$SSH_TARGET:$REMOTE_PATH/"

# ── 3) удалённо: платформа + стек + миграции ─────────────────────────────
step "Удалённо: платформа Postgres + стек + миграции"
"${SSH[@]}" bash -se <<REMOTE
set -e
cd "$REMOTE_PATH/docker"
docker compose -f platform/docker-compose.yml up -d      # общий Postgres (создаёт сеть)
docker compose build php                                 # vendor собирается в образе
docker compose up -d                                     # nginx + php
docker compose exec -T php php vendor/bin/phinx migrate -c phinx.php
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
