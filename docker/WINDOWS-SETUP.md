# Пошаговый гайд: mirailounge.ru на Windows (Docker)

Один гайд от установки до продакшн.

---

## Часть 1. Установка (один раз)

### Шаг 1. Виртуализация в BIOS

1. Перезагрузка → вход в BIOS (Del, F2 или F12 при загрузке).
2. Найти **Virtualization Technology** / **VT-x** / **AMD-V**.
3. Включить (Enable) → сохранить и выйти.

---

### Шаг 2. WSL2

1. **PowerShell от имени администратора**.
2. Выполнить:
   ```powershell
   wsl --install
   ```
3. Перезагрузить ПК.
4. Проверить:
   ```powershell
   wsl --list --verbose
   ```
   Должна быть версия **2**.

---

### Шаг 3. Docker Desktop

1. Скачать: https://www.docker.com/products/docker-desktop/
2. Установить, отметить:
   - **Use WSL 2 based engine**
   - **Add shortcut to desktop**
3. Перезагрузить ПК.
4. Запустить Docker Desktop, дождаться загрузки (иконка в трее).

---

### Шаг 4. Настройки Docker Desktop

1. **Settings** (шестерёнка).
2. **Resources** → **File sharing** — добавить диск с проектом (C:\, D:\ и т.д.).
3. **Resources** → **WSL Integration** — включить для Ubuntu.
4. **General** → **Start Docker Desktop when you sign in** — включить.
5. **Apply & Restart**.

---

## Часть 2. Запуск сайта

### Шаг 5. Разместить проект

Скопировать проект, например в `D:\projects\new_mirai_lounge_menu\`.

Проверить наличие папок:
- `data/`
- `public/assets/img/menu/uploads/`
- `public/assets/img/gallery/uploads/`

Один раз можно запустить **`docker\prepare-deploy.bat`** (создаст `.env` из `.env.example`, напомнит про секреты в `config/config.php`).

---

## Деплой на сервер «копированием папки»

Полная пошаговая инструкция в корне репозитория: **`DEPLOY.md`**. Ниже — краткий чеклист.

1. Скопировать **весь** проект на машину с Docker (как сейчас).
2. Убедиться, что в архиве есть **`composer.json`** и **`composer.lock`** — при первом `docker compose up` образ PHP соберёт **`vendor`** внутри контейнера (локально Composer не обязателен).
3. При необходимости скопировать **`.env.example` → `.env`** в корне и задать `MIRAI_TG_*`, `MIRAI_VAPID_*` (Telegram и Web Push).
4. В **`config/config.php`** сменить **`orders_security.signing_key`** и проверить пароли админки.
5. Положить файлы Reg.ru в **`docker/ssl/`** → запустить **`docker\ssl\setup-certs.bat`** (получится `docker/ssl/mirailounge.ru/fullchain.pem` и `privkey.pem`).
6. Запустить **`docker\start.bat`** (то же, что **`run.bat`**): поднимутся Nginx и PHP.

После обновления зависимостей PHP на другой машине пересоберите образ:

```powershell
cd D:\путь\к\проекту
docker compose -f docker/docker-compose.yml build php
docker compose -f docker/docker-compose.yml up -d
```

---

### Шаг 6. Hosts (для локальной проверки)

1. Блокнот **от имени администратора**.
2. Файл → Открыть → `C:\Windows\System32\drivers\etc\hosts`.
3. Добавить:
   ```
   127.0.0.1  mirailounge.ru
   127.0.0.1  www.mirailounge.ru
   ```
4. Сохранить.

---

### Шаг 7. Брандмауэр (для доступа из интернета)

1. **Win + R** → `wf.msc` → Enter.
2. **Правила для входящих подключений** → **Создать правило**.
3. Тип: **Порт** → Далее.
4. TCP, порты: **80, 443** → Далее.
5. Разрешить подключение → Далее.
6. Все профили → Далее.
7. Имя: `Docker HTTP/HTTPS` → Готово.

---

### Шаг 8. Запуск

**Вариант А** — двойной клик по `docker/start.bat` или `docker/run.bat`.

**Вариант Б** — из папки проекта:
```powershell
docker compose -f docker/docker-compose.yml up -d
```

Первый запуск соберёт PHP (1–2 минуты).

---

### Шаг 9. Проверка

- https://mirailounge.ru (после `setup-certs.bat`) или http://localhost
- http://mirailounge.ru/admin/

---

## Часть 3. Продакшн

### Шаг 10. Доступ по внешнему IP

Сайт будет доступен из интернета, если:

1. **DNS** — A-запись **mirailounge.ru** (и при необходимости **www**) указывает на ваш внешний IP.
2. **Роутер** — проброс портов 80 и 443 на IP этого ПК (если за NAT).
3. **Брандмауэр** — порты 80 и 443 открыты (Шаг 7).

### Шаг 11. SSL (HTTPS)

HTTPS для mirailounge.ru: **docker/ssl/README.md** и **`docker/ssl/setup-certs.bat`**. Конфиг Nginx: **`docker/nginx/conf.d/00-ssl-mirailounge.conf`**.

---

## Команды

| Действие   | Команда                                                      |
|------------|--------------------------------------------------------------|
| Запуск     | `docker/start.bat`, `docker/run.bat` или `docker compose -f docker/docker-compose.yml up -d` |
| Остановка  | `docker/stop.bat` или `docker compose -f docker/docker-compose.yml down`  |
| Логи       | `docker compose -f docker/docker-compose.yml logs -f`        |
| Перезапуск | `docker compose -f docker/docker-compose.yml restart`        |

---

## Проблемы

### Сайт не запускается

**1. Проверить, что Docker запущен**

- Иконка Docker в трее (без анимации загрузки).
- Если нет — запустить Docker Desktop.

**2. Проверить контейнеры**

```powershell
docker compose -f docker/docker-compose.yml ps
```

Должны быть `mirai-nginx` и `mirai-php` в статусе `Up`. Если `Exited` — смотреть логи:

```powershell
docker compose -f docker/docker-compose.yml logs
```

**3. Проверить путь к проекту**

Команду запуска выполнять из папки, где лежат `public/`, `admin/`, `docker/`. Например:

```powershell
cd D:\projects\new_mirai_lounge_menu
docker compose -f docker/docker-compose.yml up -d
```

**4. Порты 80/443 заняты**

```powershell
netstat -ano | findstr ":80"
```

Остановить IIS, Skype и др. Или в `docker-compose.yml` заменить `80:80` на `8080:80` и открывать http://mirailounge.ru:8080

**5. File sharing**

Docker Desktop → Settings → Resources → File sharing — должен быть добавлен диск с проектом. Apply & Restart.

---

**Docker не устанавливается / не запускается**

- Включить виртуализацию в BIOS.
- Выполнить `wsl --install` и перезагрузить.
- Включить «Платформа виртуальных машин» и «Подсистема Windows для Linux» (Параметры → Приложения → Дополнительные компоненты).
- Обновить Windows (build 19041+).

**«The system cannot find the path specified»**

- Docker Desktop → Settings → Resources → File sharing — добавить диск с проектом.
- Apply & Restart.

**Ошибки при загрузке фото**

- Проверить наличие `public/assets/img/menu/uploads/`.
- Перезапустить: `docker compose -f docker/docker-compose.yml restart`.

---

## Разработка и структура кода

После того как Docker запускается, см. корневой **README**: локальный PHP без Docker, дерево папок, данные `data/*.json`, соглашения (стили и скрипты в `assets/`, а не инлайн в шаблонах).
