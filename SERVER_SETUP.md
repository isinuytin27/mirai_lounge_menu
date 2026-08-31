# Настройка сервера — Ubuntu Server под платформу Mirai

Пошагово: чистый Ubuntu Server → безопасность → Docker → общий Postgres → деплой сайтов
из GitHub Actions. Хост: `87.251.104.230`. Мультисайт: mirailounge.ru (+ поддомены),
miraileague.ru, шкл65.рф; booking — отдельный сервис (см. интеграцию аддона).

---

## 0. Установка Ubuntu Server

- **Ubuntu Server 24.04 LTS** (минимальная установка, без GUI).
- При установке: создать пользователя (напр. `mirai`), поставить галку **Install OpenSSH server**, добавить свой публичный SSH-ключ (Import SSH identity → GitHub, если ключ там).
- Разметка: обычная (весь диск), либо LVM. Для БД желательно ≥ 40 GB под `/`.

После установки зайти по SSH:
```bash
ssh mirai@87.251.104.230
```

## 1. Базовая настройка и безопасность

```bash
# обновления
sudo apt update && sudo apt upgrade -y
sudo apt install -y ca-certificates curl gnupg ufw fail2ban unattended-upgrades

# автообновления безопасности
sudo dpkg-reconfigure -plow unattended-upgrades

# firewall: только SSH + HTTP + HTTPS
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# fail2ban (защита SSH от брутфорса) — включён по умолчанию
sudo systemctl enable --now fail2ban
```

**SSH-хардненинг** — `sudo nano /etc/ssh/sshd_config.d/hardening.conf`:
```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```
```bash
sudo systemctl restart ssh
```
> ⚠️ Перед этим убедись, что заходишь по ключу (иначе потеряешь доступ).

## 2. Docker + Compose

```bash
# официальный репозиторий Docker
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# работать docker без sudo
sudo usermod -aG docker $USER
newgrp docker   # или перелогиниться
docker run --rm hello-world   # проверка
```

## 3. Каталоги проекта

```bash
sudo mkdir -p /var/www/mirailounge
sudo chown -R $USER:$USER /var/www
# сюда GitHub Actions зальёт код сайта mirailounge (DEPLOY_PATH = /var/www/mirailounge)
```

## 4. Деплой-ключ для GitHub Actions

На сервере создаём **отдельный** ключ только для деплоя:
```bash
ssh-keygen -t ed25519 -f ~/.ssh/deploy_key -N "" -C "gh-actions-deploy"
cat ~/.ssh/deploy_key.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```
Приватную часть (`~/.ssh/deploy_key`) кладём в GitHub Secrets:
```bash
# на своей МАШИНЕ (скопировав приватный ключ с сервера):
gh secret set DEPLOY_HOST --body "87.251.104.230"
gh secret set DEPLOY_USER --body "mirai"
gh secret set DEPLOY_PATH --body "/var/www/mirailounge"
gh secret set DEPLOY_SSH_KEY < deploy_key   # приватный ключ
```
> Приватный ключ на сервере после копирования удали: `rm ~/.ssh/deploy_key`.

## 5. Общий Postgres (платформа)

Postgres — один на все сайты, отдельным стеком (редеплой сайта его не трогает).

`/var/www/mirailounge/.env` (секреты, не в git):
```ini
APP_ENV=prod
POSTGRES_DB=mirailounge
POSTGRES_USER=mirailounge
POSTGRES_PASSWORD=<СИЛЬНЫЙ_ПАРОЛЬ>
POSTGRES_HOST=postgres
POSTGRES_PORT=5432
# КРИТИЧНО: без ключа prod падает на старте (fail-closed). Сгенерировать:
#   php -r 'echo bin2hex(random_bytes(32));'  или  openssl rand -hex 32
MIRAI_TABLE_SIGNING_KEY=<64_hex>
# Telegram (НОВЫЙ токен — старый утёк в историю git, отозвать через BotFather)
MIRAI_TG_BOT_TOKEN=
MIRAI_TG_CHAT_ID=
# VAPID (php scripts/generate-vapid.php)
MIRAI_VAPID_PUBLIC=
MIRAI_VAPID_PRIVATE=
```
Поднять платформу (после первого деплоя кода — см. шаг 7):
```bash
cd /var/www/mirailounge
docker compose -f docker/platform/docker-compose.yml up -d
```
Для соседних сайтов заводим свою database в том же инстансе:
```bash
docker exec -it mirai-postgres psql -U mirailounge -d postgres \
  -c "CREATE ROLE miraileague LOGIN PASSWORD '***'; CREATE DATABASE miraileague OWNER miraileague;"
```

## 6. TLS-сертификаты (готовые, без Let's Encrypt)

```bash
sudo mkdir -p /var/www/mirailounge/docker/ssl/mirailounge.ru
# положить сюда с reg.ru:
#   fullchain.pem  (сертификат + цепочка CA)
#   privkey.pem    (приватный ключ)
sudo chmod 600 /var/www/mirailounge/docker/ssl/mirailounge.ru/privkey.pem
```
> nginx-vhost (`docker/nginx/conf.d/00-ssl-mirailounge.conf`) уже ссылается на эти пути.
> Серты вне git (в `.gitignore`/rsync-excludes) — деплой их не перезапишет.

## 7. Первый деплой и разовый импорт данных

1. Задать секреты (шаг 4) и `.env` (шаг 5), положить серты (шаг 6).
2. Смёрджить `rebuild/foundation → main` — GitHub Actions зальёт код и поднимет стек.
3. **Разово** перенести старые данные (`data/*.json`, если есть на сервере) в Postgres:
```bash
cd /var/www/mirailounge
docker compose exec -T php php bin/import-json
# Граф рекомендаций (гастропары): справочник тегов/категорий + маппинг. Идемпотентно —
# перезаливает справочник из resources/menu-association-model.json (можно повторять).
docker compose exec -T php php bin/import-recommender
```
> `import-json` идемпотентный, но запускать его нужно ТОЛЬКО один раз — иначе затрёт
> правки из новой админки старым снимком JSON. В пайплайне его нет.
> `import-recommender` можно гонять повторно (обновляет только справочник рекомендатора).
4. Создать первого администратора (если нет в импорте):
```bash
docker compose exec -T php php bin/create-admin <login> <пароль> owner
```

## 8. Проверка

- `https://mirailounge.ru/` — витрина открывается
- `https://mirailounge.ru/_health` — `{"status":"ok"}`
- `https://mirailounge.ru/admin` → вход, дашборд (все домены Postgres)
- `docker compose ps` — nginx/php/postgres healthy; miraipro не задет

## 9. Эксплуатация

```bash
# логи
docker compose logs -f php
# бэкап БД (по cron)
docker exec mirai-postgres pg_dump -U mirailounge mirailounge | gzip > ~/backups/db_$(date +%F).sql.gz
# место на диске / состояние
docker system df ; docker compose ps
```
Cron-бэкап: `crontab -e` →
```
0 4 * * * docker exec mirai-postgres pg_dump -U mirailounge mirailounge | gzip > /home/mirai/backups/db_$(date +\%F).sql.gz
```

---

## Дальнейшие сайты платформы

Каждый сайт — свой каталог/репозиторий + своя database в общем Postgres, за общим
nginx-reverse-proxy по `server_name`. Booking-аддон — отдельный сервис на поддомене
`booking.mirailounge.ru` (см. план интеграции аддона).
