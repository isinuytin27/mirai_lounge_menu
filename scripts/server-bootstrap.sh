#!/usr/bin/env bash
#
# Разовая подготовка чистого Ubuntu-сервера под mirailounge: Docker + каталог + .env.
# Ставит Docker Engine, кладёт лимиты логов, создаёт /var/www/mirailounge и генерирует
# .env со случайным MIRAI_TABLE_SIGNING_KEY и паролем Postgres.
#
# Запуск НА СЕРВЕРЕ (с рабочей машины один раз):
#   scp -P <порт> scripts/server-bootstrap.sh deploy@<сервер>:/tmp/
#   ssh -p <порт> deploy@<сервер> "sudo bash /tmp/server-bootstrap.sh"
#
# Полный контекст (сеть, SSH, firewall, TLS) — в SERVER_SETUP.md. Здесь только то,
# что скриптуется безопасно и повторяется.
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "✗ Запусти через sudo: sudo bash $0" >&2; exit 1; }

DEPLOY_USER="${DEPLOY_USER:-deploy}"
APP_DIR="${APP_DIR:-/var/www/mirailounge}"
step(){ printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }

id "$DEPLOY_USER" >/dev/null 2>&1 || { echo "✗ Нет пользователя '$DEPLOY_USER'. Создай: adduser $DEPLOY_USER && usermod -aG sudo $DEPLOY_USER" >&2; exit 1; }

step "Docker Engine (официальный установщик)"
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com -o /tmp/get-docker.sh && sh /tmp/get-docker.sh
else
  echo "  Docker уже установлен: $(docker --version)"
fi
usermod -aG docker "$DEPLOY_USER" || true
systemctl enable --now docker

step "Лимиты логов Docker (иначе съедят диск)"
mkdir -p /etc/docker
cat > /etc/docker/daemon.json <<'JSON'
{ "log-driver": "json-file", "log-opts": { "max-size": "10m", "max-file": "3" } }
JSON
systemctl restart docker

step "Каталог проекта $APP_DIR"
mkdir -p "$APP_DIR/docker/ssl/mirailounge.ru"
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$APP_DIR"

step ".env (генерируем ключ и пароль автоматически)"
if [ ! -f "$APP_DIR/.env" ]; then
  KEY="$(openssl rand -hex 32)"
  PGPW="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)"
  cat > "$APP_DIR/.env" <<ENV
APP_ENV=prod

POSTGRES_DB=mirailounge
POSTGRES_USER=mirailounge
POSTGRES_PASSWORD=$PGPW
POSTGRES_HOST=postgres
POSTGRES_PORT=5432

# fail-closed: без ключа prod не поднимется
MIRAI_TABLE_SIGNING_KEY=$KEY

# Telegram/WebPush — заполнить, когда будут (можно позже)
MIRAI_TG_BOT_TOKEN=
MIRAI_TG_CHAT_ID=
MIRAI_VAPID_PUBLIC=
MIRAI_VAPID_PRIVATE=
MIRAI_VAPID_SUBJECT=mailto:admin@mirailounge.ru
ENV
  chown "$DEPLOY_USER:$DEPLOY_USER" "$APP_DIR/.env"
  chmod 600 "$APP_DIR/.env"
  echo "  ✓ .env создан (SIGNING_KEY и POSTGRES_PASSWORD сгенерированы)"
else
  echo "  .env уже существует — не трогаю"
fi

cat <<DONE

✓ Сервер подготовлен. Осталось два ручных шага:

  1) TLS-сертификаты (Reg.ru, БЕЗ Let's Encrypt) — положить на сервер:
       $APP_DIR/docker/ssl/mirailounge.ru/fullchain.pem   (сертификат + цепочка CA)
       $APP_DIR/docker/ssl/mirailounge.ru/privkey.pem     (приватный ключ, chmod 600)

  2) С рабочей машины — первый деплой:
       ./scripts/deploy.sh --first

Сеть/SSH/firewall (проброс 80/443, нестандартный SSH-порт, ufw, fail2ban) —
если ещё не сделаны, см. SERVER_SETUP.md, Часть I.
DONE
