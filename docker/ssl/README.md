# Сертификаты для mirailounge.ru

Общий обзор деплоя и стека: **DEPLOY.md** и **docker/README.md** в корне репозитория; структура проекта — **README**.

## Быстрый способ

1. Скопируйте в папку `docker/ssl/` три файла от Reg.ru:
   - `certificate.crt`
   - `certificate_ca.crt`
   - `certificate.key`

2. Запустите скрипт:
   - **Windows:** дважды кликните `setup-certs.bat`
   - **Mac:** в терминале `cd docker/ssl && chmod +x setup-certs.sh && ./setup-certs.sh`

3. Перезапустите Nginx (или весь стек):
   ```powershell
   docker compose -f docker/docker-compose.yml restart nginx
   ```
   Либо сначала сертификаты, затем запуск: `docker\start.bat` (или `docker\run.bat`).

## Вручную

1. Создайте папку `docker/ssl/mirailounge.ru/`

2. Соберите `fullchain.pem` — откройте в блокноте `certificate.crt`, скопируйте всё, вставьте в новый файл. Затем откройте `certificate_ca.crt`, скопируйте всё, допишите в тот же файл. Сохраните как `fullchain.pem` в `docker/ssl/mirailounge.ru/`

3. Скопируйте `certificate.key` в `docker/ssl/mirailounge.ru/` и переименуйте в `privkey.pem`

4. Должно получиться:
   ```
   docker/ssl/mirailounge.ru/
   ├── fullchain.pem
   └── privkey.pem
   ```
