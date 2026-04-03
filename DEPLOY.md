# Деплой Mirai Lounge — расширенная инструкция

Схема: **репозиторий GitHub** → **GitHub Actions** → **SSH + rsync** на сервер. Выкладывается **код**; **меню, заказы, VIP, загрузки картинок и фото интерьера** на сервере **не перезаписываются** (список исключений — `scripts/deploy-excludes.txt`).

Дополнительно: корневой **README**, локальный запуск, Docker на Windows — **`docker/WINDOWS-SETUP.md`**, контейнеры — **`docker/README.md`**.

---

## Содержание

1. [Как это устроено](#как-это-устроено)
2. [Что нужно заранее](#что-нужно-заранее)
3. [Репозиторий на GitHub](#репозиторий-на-github)
4. [Сервер: пользователь, каталог, права](#сервер-пользователь-каталог-права)
5. [SSH-ключ только для деплоя (GitHub → сервер)](#ssh-ключ-только-для-деплоя-github--сервер)
6. [Секреты в GitHub](#секреты-в-github)
7. [Первый деплой и проверка](#первый-деплой-и-проверка)
8. [После каждого деплоя (Docker)](#после-каждого-деплоя-docker)
9. [Что выкладывается и что никогда не затирается](#что-выкладывается-и-что-никогда-не-затирается)
10. [`.env` и переменные окружения](#env-и-переменные-окружения)
11. [Ручной деплой с ноутбука](#ручной-деплой-с-ноутбука)
12. [Бэкап перед обновлением](#бэкап-перед-обновлением)
13. [Откат (rollback)](#откат-rollback)
14. [Устранение неполадок](#устранение-неполадок)
15. [Обновление без GitHub (архив)](#обновление-без-github-архив)
16. [Web Push (VAPID)](#web-push-vapid)
17. [Telegram](#telegram)
18. [Команды Docker](#команды-docker)
19. [URLs](#urls)
20. [Первый запуск с нуля (Windows + Docker)](#первый-запуск-с-нуля-windows--docker)

---

## Как это устроено

| Компонент | Роль |
|-----------|------|
| **`.github/workflows/deploy.yml`** | При push в **`main`** (и вручную) запускает job на машине GitHub. |
| **`rsync`** | Копирует файлы из checkout в **`DEPLOY_PATH`** на сервере, с исключениями из **`scripts/deploy-excludes.txt`**. |
| **Без `--delete`** | Файлы, которых нет в репозитории (старые медиа, `.env`, `data/*.json`), **не удаляются** на сервере. |
| **Docker на сервере** | Сайт крутится в **nginx + php**; после смены кода контейнеры нужно **перезапустить** или **пересобрать** PHP при смене `composer.lock`. |

---

## Что нужно заранее

- Аккаунт **GitHub** и право на push в репозиторий.
- **Сервер** (VPS или свой ПК с белым IP): Docker установлен, открыты порты **80** и **443** (и проброс с роутера, если сайт из интернета).
- **Домен** (например `mirailounge.ru`): A-запись на IP сервера.
- Доступ по **SSH** на сервер под root или sudo (для первичной настройки).

---

## Репозиторий на GitHub

1. Создайте репозиторий (можно пустой, без README, если переносите существующий проект).
2. На своём компьютере в каталоге проекта:
   ```bash
   git init
   git remote add origin <URL-репозитория-HTTPS-или-SSH>
   git add .
   git commit -m "Initial commit"
   git branch -M main
   git push -u origin main
   ```
3. Ветка продакшена в workflow должна совпадать с вашей. По умолчанию **`main`**. Если используете другую ветку — отредактируйте **`deploy.yml`** (`branches:`).

---

## Сервер: пользователь, каталог, права

Рекомендуется **отдельный пользователь** для деплоя (не root), чтобы ограничить ущерб при утечке ключа.

### Пример (Linux)

```bash
sudo adduser deploy
sudo mkdir -p /var/www/mirailounge
sudo chown deploy:deploy /var/www/mirailounge
```

`DEPLOY_PATH` в секретах GitHub — например **`/var/www/mirailounge`**. Внутри должен лежать **корень проекта** (рядом с `composer.json`, папками `public/`, `docker/`, `config/`).

### Первый раз: положить проект на сервер

Любой из вариантов:

```bash
# с вашего ПК
rsync -avz ./ deploy@SERVER:/var/www/mirailounge/

# или git clone на сервере под пользователем deploy
sudo -u deploy git clone <URL> /var/www/mirailounge
```

Дальше весь деплой через Actions будет **обновлять** этот каталог.

---

## SSH-ключ только для деплоя (GitHub → сервер)

Это **не** тот же ключ, что для `git push` в GitHub (если вы пушите по SSH). Для деплоя нужна **отдельная пара**:

1. На **своём ПК** (не на сервере):
   ```bash
   ssh-keygen -t ed25519 -C "github-actions-mirai-deploy" -f ~/.ssh/mirai_deploy -N ""
   ```
2. Содержимое **`~/.ssh/mirai_deploy.pub`** добавьте на **сервер** в файл **`/home/deploy/.ssh/authorized_keys`** (одна строка на ключ). Папка `.ssh` — права `700`, файл `authorized_keys` — `600`.
3. Проверка с ПК:
   ```bash
   ssh -i ~/.ssh/mirai_deploy deploy@ВАШ_СЕРВЕР
   ```
   Должен открыться shell **без пароля**.
4. В GitHub Secret **`DEPLOY_SSH_KEY`** вставьте **полное** содержимое **приватного** файла **`~/.ssh/mirai_deploy`** (включая строки `BEGIN` и `END`).

**Важно:** приватный ключ в репозиторий **не коммитить**. Только в **Secrets**.

---

## Секреты в GitHub

**Repository → Settings → Secrets and variables → Actions → New repository secret**

| Secret | Пример | Описание |
|--------|--------|----------|
| **`DEPLOY_HOST`** | `mirailounge.ru` или `203.0.113.50` | Хост SSH (без `ssh://` и без пользователя). |
| **`DEPLOY_USER`** | `deploy` | Пользователь на сервере (тот, чей `authorized_keys`). |
| **`DEPLOY_PATH`** | `/var/www/mirailounge` | Абсолютный путь к **корню проекта** на сервере. Без завершающего `/` или с — в workflow путь используется как `.../`. |
| **`DEPLOY_SSH_KEY`** | весь PEM/OpenSSH private key | Приватный ключ, см. выше. |

Пока все четыре не заданы, workflow при проверке завершится ошибкой.

---

## Первый деплой и проверка

1. Убедитесь, что в **`main`** есть актуальный код и запушены последние коммиты.
2. **GitHub → Actions** → выберите workflow **Deploy to server** → последний запуск.
3. Зелёная галочка — rsync прошёл. Красный крест — откройте job → шаг **Rsync** → читайте лог (частые причины: неверный ключ, хост, права на `DEPLOY_PATH`, `Permission denied`).

После успеха файлы на сервере обновлены; **исключённые** пути не тронуты.

---

## После каждого деплоя (Docker)

Подключитесь по SSH к серверу:

```bash
ssh deploy@ВАШ_СЕРВЕР
cd /var/www/mirailounge/docker   # или cd $DEPLOY_PATH/docker
```

| Ситуация | Команда |
|----------|---------|
| Менялись **`composer.json` / `composer.lock`**, Dockerfile | `docker compose -f docker-compose.yml build php` затем `docker compose -f docker-compose.yml up -d` |
| Менялись только **PHP/шаблоны/статика**, без Composer | `docker compose -f docker-compose.yml restart php nginx` |
| Не уверены | `docker compose -f docker-compose.yml build php && docker compose -f docker-compose.yml up -d` |

Если `docker-compose.yml` вызываете из **корня** репозитория:

```bash
cd /var/www/mirailounge
docker compose -f docker/docker-compose.yml build php
docker compose -f docker/docker-compose.yml up -d
```

---

## Что выкладывается и что никогда не затирается

### В репозитории и при деплое — код

Исходники PHP, `admin/`, `public/`, `config/` (как в git), `composer.json`, `docker/`, `router.php`, скрипты и т.д.

### Исключения rsync (`scripts/deploy-excludes.txt`)

| Путь | Смысл |
|------|--------|
| `data/menu.json` | Меню |
| `data/gallery.json` | Галерея столов |
| `data/orders.json`, `data/push_subscriptions.json` | Заказы, подписки push |
| `data/vip_events.json`, `data/vip_guests.json` | VIP / корпоратив |
| `public/assets/img/menu/uploads/` | Фото блюд |
| `public/assets/img/gallery/uploads/` | Фото галереи |
| `public/assets/img/vip/partner_uploads/` | Логотипы партнёров |
| `public/assets/img/interior/` | Фото интерьера для экрана галереи |

Править список можно под ваш сервер: добавьте строку в **`deploy-excludes.txt`** и закоммитьте.

### Не в git и не перезаписывается rsync из репозитория

- **`.env`** — в корне проекта на сервере свой; в git не попадает.
- Содержимое **`config/config.php`** на проде может отличаться от репозитория (пароли, ключи). После деплоя при необходимости **вручную** перенесите изменения из репозитория (diff), не затирая секреты.

### Почему без `rsync --delete`

Чтобы каталоги **без** в git (интерьер, uploads) **не удалились** с сервера. Файлы, удалённые из репозитория, на сервере могут **остаться** — почистите вручную при необходимости.

---

## `.env` и переменные окружения

Файл **`.env.example`** в репозитории — шаблон. На сервере один раз создайте **`.env`** (в корне рядом с `composer.json`, его подхватывает `docker-compose` для PHP).

| Переменная | Назначение |
|------------|------------|
| `MIRAI_TG_BOT_TOKEN` | Токен Telegram-бота (переопределяет значение из `config/config.php`, если задано). |
| `MIRAI_TG_CHAT_ID` | ID чата для уведомлений. |
| `MIRAI_TG_HTTP_PROXY` | HTTP-прокси до `api.telegram.org`, если с сервера нет прямого доступа. |
| `MIRAI_VAPID_PUBLIC` | Публичный ключ Web Push. |
| `MIRAI_VAPID_PRIVATE` | Приватный ключ Web Push. |
| `MIRAI_VAPID_SUBJECT` | Обычно `mailto:...`. |

Генерация VAPID: **`php scripts/generate-vapid.php`** или **`docker/gen-vapid.bat`**. После правок **`.env`** перезапустите контейнер **php**.

Остальное (админка, `signing_key`, тексты Telegram по умолчанию) — в **`config/config.php`**: на проде проверьте секреты и не коммитьте боевые значения в публичный репозиторий.

---

## Ручной деплой с ноутбука

Из корня клона:

```bash
rsync -avz \
  --exclude-from=scripts/deploy-excludes.txt \
  -e "ssh -i ~/.ssh/mirai_deploy" \
  ./ deploy@SERVER:/var/www/mirailounge/
```

Затем перезапуск Docker, как в [разделе выше](#после-каждого-деплоя-docker).

---

## Бэкап перед обновлением

Перед крупным обновлением или экспериментом на сервере:

```bash
# пример: архив данных и загрузок
cd /var/www/mirailounge
sudo tar czf ~/mirai-backup-$(date +%Y%m%d).tar.gz \
  data/ .env public/assets/img/menu/uploads/ \
  public/assets/img/gallery/uploads/ \
  public/assets/img/vip/partner_uploads/ \
  public/assets/img/interior/ 2>/dev/null
```

Сохраните **`config/config.php`**, если настраивали вручную.

---

## Откат (rollback)

1. **В коде:** в GitHub откатите коммит (`git revert` или новый коммит с исправлением), запушьте в **`main`** — сработает тот же деплой, затем перезапустите Docker.
2. **В данных:** восстановите из бэкапа каталоги **`data/`** и при необходимости загрузки картинок и **`.env`**.

---

## Устранение неполадок

### GitHub Actions: ошибка на шаге rsync / SSH

| Симптом | Что проверить |
|---------|----------------|
| `Permission denied (publickey)` | Ключ в **`DEPLOY_SSH_KEY`** — приватный, полный; в **`authorized_keys`** на сервере — соответствующий публичный; пользователь **`DEPLOY_USER`** верный. |
| `Could not resolve hostname` | **`DEPLOY_HOST`** без опечаток; DNS с серверов GitHub резолвится (или используйте IP). |
| `Permission denied` при записи файлов | Пользователь деплоя должен владеть **`DEPLOY_PATH`** или иметь групповую запись на каталог. |
| Таймауты SSH | Файрвол, `security group` на VPS, порт 22 не слушается только с вашего IP. |

### Сайт 502 / пустая страница после деплоя

- Перезапустили ли **php** и **nginx** после выкладки?
- Логи: `docker compose -f docker/docker-compose.yml logs php nginx --tail=100`

### Меню / картинки пропали

- Деплой **не должен** был перезаписать исключённые пути. Если правили **`deploy-excludes.txt`** — проверьте синтаксис. Если руками запускали **rsync с `--delete`** — восстановите из бэкапа.

### Конфиг «сбросился»

- Не коммитьте в git прод-пароли. После merge слейте **`config/config.php`** с сервером вручную.

---

## Обновление без GitHub (архив)

Если копируете проект архивом:

1. Сохраните бэкап **`data/`**, `public/assets/img/**/uploads/`, `interior/`, **`.env`**, **`config/config.php`**.
2. Распакуйте новый код.
3. Верните сохранённые файлы и каталоги на место.

---

## Web Push (VAPID)

1. В контейнере или локально: `php scripts/generate-vapid.php`.
2. Вставьте ключи в **`.env`**, перезапустите **php**.
3. Если менялись зависимости: **`build php`** и **`up -d`**.

---

## Telegram

1. В ответе API заказа смотрите **`telegram_ok`**.
2. Логи PHP: `docker compose ... logs php | grep -i telegram`
3. Бот должен быть в чате, **chat_id** актуален; при смене группы на супергруппу id часто меняется на **`-100…`**.

---

## Команды Docker

```bash
docker compose -f docker/docker-compose.yml ps
docker compose -f docker/docker-compose.yml logs -f nginx
docker compose -f docker/docker-compose.yml logs -f php
docker compose -f docker/docker-compose.yml down
```

---

## URLs

| Назначение | URL |
|------------|-----|
| Сайт | `https://mirailounge.ru` |
| Админка | `https://mirailounge.ru/admin/` |
| Заказы зала | `https://mirailounge.ru/orders/` |

---

## Первый запуск с нуля (Windows + Docker)

Подходит для **локального сервера** или Windows-VPS на площадке.

1. Скопировать проект (или клонировать после первого деплоя).
2. **`docker/prepare-deploy.bat`** — напомнит про **`.env`**.
3. **`config/config.php`**: пользователи админки, **`orders_security.signing_key`** (уникальная строка).
4. HTTPS: сертификаты в **`docker/ssl/`**, скрипты **`setup-certs.bat`** в **`docker/ssl/`**, см. комментарии в репозитории.
5. **`docker/start.bat`** или `docker compose -f docker/docker-compose.yml up -d`.
6. DNS: A-запись на IP.
7. Для проверки на том же ПК — **`hosts`**: `127.0.0.1 mirailounge.ru`.

Подробные шаги: **`docker/WINDOWS-SETUP.md`**.

---

## Чеклист «всё готово к продакшену»

- [ ] Репозиторий на GitHub, ветка **`main`** пушится.
- [ ] Секреты **`DEPLOY_*`** заданы, workflow зелёный.
- [ ] На сервере **`DEPLOY_PATH`** существует, права у пользователя деплоя.
- [ ] **`.env`** на сервере заполнен (VAPID, при необходимости Telegram).
- [ ] **`config/config.php`** на проде с прод-паролями и **`signing_key`**.
- [ ] SSL сертификаты на месте, сайт открывается по **HTTPS**.
- [ ] **`data/*.json`** и медиа либо накатаны из бэкапа, либо заполнены через админку.
- [ ] После деплоя выполнены **`build`/`restart`** контейнеров.

---

## Файлы, которые не стоит коммитить в публичный git

- **`.env`**
- **`data/*.json`** с реальными данными (часть перечислена в **`.gitignore`**)
- загрузки **`public/assets/img/**/uploads/`**
- приватные ключи **`docker/ssl/`** (продовые PEM)

---

## Краткая справка по путям на сервере

```
DEPLOY_PATH/                 # например /var/www/mirailounge
  .env                       # только на сервере
  composer.json
  config/config.php
  data/                      # JSON (исключения rsync)
  docker/docker-compose.yml
  public/                    # document root
  admin/
  scripts/deploy-excludes.txt
```

Если измените **структуру** проекта в репозитории, проверьте **`docker-compose.yml`** (volumes) и nginx-конфиги в **`docker/nginx/`**.
