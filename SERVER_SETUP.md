# Настройка сервера с нуля — bare-metal Ubuntu → mirailounge.ru

Путь от BIOS физической машины до работающего `https://mirailounge.ru`
с автодеплоем через self-hosted runner. Сервер — `192.168.88.81` за роутером
с белым адресом `87.251.104.230`.

**Составлено по актуальному коду репозитория.** `DEPLOY.md` описывает предыдущий
стек (JSON-хранилище, без Postgres) и в части архитектуры устарел — при
расхождениях верить этому файлу.

| | |
|---|---|
| **Инфраструктура** | nginx (alpine) · PHP-FPM 8.2 · Postgres 16 |
| **Приложение** | Slim 4 · Twig · Phinx · Vite 6 |
| **Хост** | Bare-metal · Ubuntu 24.04 LTS · за NAT, `192.168.88.81` |
| **Доступ** | Пароли (без ключей) · SSH только из LAN · деплой через runner |

---

## 🔴 Сделать ДО того, как машина окажется в интернете

**1. Перевыпустить TLS-сертификат.** Приватный ключ `docker/ssl/certificate.key`
был закоммичен в `5b1e317` и удалён только в `e82475c` — он остаётся в истории git
и в бэкапах GitHub. Ключ скомпрометирован: выпустите новую пару, старую отзовите.

**2. Отозвать Telegram-бота.** В истории `config/config.php` лежит живой токен
`8718718166:AAFj…` и `chat_id -5270256930`. @BotFather → `/mybots` → Revoke current token.

**3. Сгенерировать `MIRAI_TABLE_SIGNING_KEY`.** Старый код падал на дефолт
`change-me-…`, которым подделывается кука стола. Новый `Config` при `APP_ENV=prod`
бросает исключение на пустом ключе — сайт не поднимется, пока не зададите.

## Профиль этой установки

**Сеть.** Машина стоит за роутером в локальной сети `192.168.88.0/24`. Белый адрес
`87.251.104.230` висит на роутере, сайт публикуется пробросом портов 80 и 443 на
`192.168.88.81`. Раньше на этом же железе работал прод под Windows, поэтому
DNS-запись и проброс уже существуют — но проверить их нужно: проброс мог указывать
на другой внутренний адрес.

**Доступ.** Вход по паролю, ключи не используются — сознательное решение. SSH
проброшен наружу на нестандартный внешний порт: администрирование нужно и вне
заведения. Это самое уязвимое место конструкции, поэтому вокруг него собрано три
слоя защиты — фильтр брутфорса на роутере, экспоненциально растущие баны fail2ban
и предельно узкий sshd (`AllowUsers`, две попытки, 20 секунд на ввод).

**Деплой.** Self-hosted runner устанавливает исходящее соединение с GitHub сам,
поэтому **пробрасывать SSH ради деплоя не нужно** — и не надо.

---

## Ключи создавать не нужно — нигде

Ни на ноутбуке, ни на сервере. Ни `ssh-keygen`, ни `ssh-copy-id`, ни
`authorized_keys` в этой инструкции не встречаются. Везде, где нужен доступ, вы
вводите **пароль пользователя `deploy`**, который сами зададите на шаге 4.

Единственный «ключ» в документе — `privkey.pem` на шаге 14, но это половина
TLS-сертификата для nginx, к SSH отношения не имеющая.

Автодеплою SSH тоже не нужен: runner на шаге 19 сам соединяется с GitHub изнутри
и работает с файлами локально.

## Три места, где вы работаете

У каждого блока команд в комментарии подписано, где его выполнять. Мест всего три:

- **Консоль сервера** — монитор и клавиатура, подключённые к самой машине. Нужны
  на шагах 1–5, пока не заработает удалённый доступ, и как аварийный вход, если
  запретесь снаружи.
- **SSH-сессия** — то же самое, но по сети с ноутбука, после шага 5. Пометка
  `root` означает, что перед командой нужен `sudo` (или заранее `sudo -i`);
  пометка `deploy` — что команду выполняют от обычного пользователя, без sudo.
- **Ноутбук** — ваша рабочая машина с клоном репозитория. Оттуда идут первая
  заливка кода и копирование сертификатов.

---

# Часть I · Железо и система

## 1. BIOS и поведение при пропадании питания

Единственный пункт, который нельзя починить удалённо. Физическая машина в
заведении переживает скачки напряжения и отключения — после них она должна
подниматься сама.

| Параметр BIOS/UEFI | Значение | Зачем |
|---|---|---|
| Restore on AC Power Loss<br>(After Power Failure / State After G3) | **Power On** | Свет дали — сервер включился сам. Дефолт обычно `Last State` или `Power Off` |
| Halt On / Wait for F1 on error | **No Errors** | Не вставать на паузу из-за отсутствующей клавиатуры |
| Secure Boot | Off | Избавляет от возни с подписью модулей ядра |
| Wake on LAN | Enabled | Резервный способ включить машину из сети |
| Fan profile | Не Silent | 24/7 в помещении с кальянами — пыль и температура |

Если сервер собран на ноутбуке или мини-ПК с крышкой — запрещаем засыпание:

```bash
# на сервере, под root
sed -i 's/^#\?HandleLidSwitch=.*/HandleLidSwitch=ignore/' /etc/systemd/logind.conf
sed -i 's/^#\?HandleLidSwitchExternalPower=.*/HandleLidSwitchExternalPower=ignore/' /etc/systemd/logind.conf
systemctl restart systemd-logind

systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target
```


> ✅ **Проверка.** `systemctl status sleep.target` → `masked`. Настройку BIOS проверяют только физически: при работающей машине выдерните кабель питания, через минуту верните — она должна включиться сама, без нажатия кнопки.

## 2. Сеть: статический адрес и проброс портов

Три вещи должны сойтись: сервер держит постоянный адрес в локальной сети, роутер не
отдаёт этот адрес никому другому, и порты 80/443 с белого адреса ведут именно на него.

### Статический адрес сервера

Сейчас адрес получен по DHCP — в выводе `ip route` это видно по пометке `proto dhcp`.

```yaml
# /etc/netplan/00-installer-config.yaml
network:
  version: 2
  ethernets:
    enp5s0:
      dhcp4: no
      addresses: [192.168.88.81/24]
      routes:
        - to: default
          via: 192.168.88.1
      nameservers:
        addresses: [192.168.88.1, 1.1.1.1]
```

Первым DNS стоит роутер — он резолвит имена внутри локальной сети; `1.1.1.1`
подстрахует, если роутер перезагрузится.

```bash
# на сервере, под root
chmod 600 /etc/netplan/00-installer-config.yaml
netplan get      # итог после слияния всех файлов в /etc/netplan/
netplan try      # откатится через 120 с, если связь пропала
netplan apply
```

### Резервирование адреса на роутере

> 🔴 **Адрес взят из DHCP-пула.** `192.168.88.81` сейчас выдан роутером, то есть
> входит в его пул (у MikroTik по умолчанию `192.168.88.10–192.168.88.254`). Если
> просто прописать его статикой, роутер рано или поздно выдаст тот же адрес ноутбуку
> или телефону — в сети окажутся два устройства с одним IP, и сайт начнёт отваливаться
> без внятной причины.
>
> Закройте это одним из двух способов: зарезервируйте адрес за MAC сервера (MikroTik:
> *IP → DHCP Server → Leases*, найти аренду, *Make Static*) либо возьмите адрес вне
> пула, например `192.168.88.5` — тогда поменяйте его и в netplan, и в пробросе.

```bash
ip link show enp5s0 | awk '/ether/ {print $2}'   # MAC для резервирования
```

### Проброс портов

На роутере должны быть два правила destination NAT: внешние 80 и 443 →
`192.168.88.81`. Они уже существуют со времён прода на Windows, но могли указывать
на прежний внутренний адрес той машины.

```routeros
# терминал MikroTik — посмотреть, что настроено сейчас
/ip firewall nat print where action=dst-nat

# если адрес назначения устарел — поправить (N — номер правила из print)
/ip firewall nat set N to-addresses=192.168.88.81

# если правил нет вовсе — создать
/ip firewall nat add chain=dstnat protocol=tcp dst-port=80 \
    action=dst-nat to-addresses=192.168.88.81 to-ports=80 comment="mirai http"
/ip firewall nat add chain=dstnat protocol=tcp dst-port=443 \
    action=dst-nat to-addresses=192.168.88.81 to-ports=443 comment="mirai https"
```

### Проброс SSH

Администрирование нужно и вне заведения, поэтому SSH тоже пробрасываем — но **не**
на внешний 22 или 2222. Возьмите произвольный высокий порт: боты сканируют интернет
по стандартным номерам, и один этот шаг убирает почти весь поток попыток.

```routeros
# внешний 49222 → внутренний 2222 на сервере
/ip firewall nat add chain=dstnat protocol=tcp dst-port=49222 \
    action=dst-nat to-addresses=192.168.88.81 to-ports=2222 comment="mirai ssh"

# старый проброс 22, если остался от прежней конфигурации — удалить
/ip firewall nat print where dst-port=22
```

**Фильтр брутфорса на роутере.** Первый рубеж: адрес, открывший больше четырёх
соединений подряд, улетает в чёрный список на десять дней и до сервера уже не
доходит. Правила — в цепочке `forward`, потому что трафик идёт через роутер
на сервер, а не самому роутеру.

```routeros
/ip firewall filter
add chain=forward protocol=tcp dst-address=192.168.88.81 dst-port=2222 \
    src-address-list=ssh_blacklist action=drop comment="ssh: drop blacklisted"
add chain=forward protocol=tcp dst-address=192.168.88.81 dst-port=2222 \
    connection-state=new src-address-list=ssh_stage3 action=add-src-to-address-list \
    address-list=ssh_blacklist address-list-timeout=10d comment="ssh: stage3 -> blacklist"
add chain=forward protocol=tcp dst-address=192.168.88.81 dst-port=2222 \
    connection-state=new src-address-list=ssh_stage2 action=add-src-to-address-list \
    address-list=ssh_stage3 address-list-timeout=1m
add chain=forward protocol=tcp dst-address=192.168.88.81 dst-port=2222 \
    connection-state=new src-address-list=ssh_stage1 action=add-src-to-address-list \
    address-list=ssh_stage2 address-list-timeout=1m
add chain=forward protocol=tcp dst-address=192.168.88.81 dst-port=2222 \
    connection-state=new action=add-src-to-address-list \
    address-list=ssh_stage1 address-list-timeout=1m
```

Порядок правил важен: `drop` должен стоять первым. Себя из списка вынимают так:
`/ip firewall address-list remove [find list=ssh_blacklist address=ВАШ_IP]`.

> ✅ **Проверка.** `ip -br a` показывает `192.168.88.81/24` на `enp5s0`,
> `ip route show default` — шлюз `192.168.88.1` уже **без** пометки `proto dhcp`.
> `ping -c2 1.1.1.1` и `ping -c2 ya.ru` проходят. `curl -s ifconfig.me` отвечает
> `87.251.104.230` — сервер выходит в интернет через нужный белый адрес.


## 3. Обновление системы

```bash
# на сервере, под root
apt update && apt upgrade -y
apt install -y openssh-server curl git rsync ca-certificates ufw fail2ban \
               smartmontools lm-sensors htop unattended-upgrades

timedatectl set-timezone Asia/Sakhalin
hostnamectl set-hostname mirai-host

dpkg-reconfigure --priority=low unattended-upgrades
```

Автообновления на машине без ключей особенно важны: большинство реальных взломов
идёт не через подбор пароля, а через непропатченный сервис.


> ✅ **Проверка.** `apt list --upgradable` ничего не выводит, `timedatectl` показывает нужный пояс.

## 4. Пользователь deploy

Пароль здесь — единственный фактор аутентификации, поэтому он должен быть длинным:
не «сложным» из спецсимволов, а именно длинным.

```bash
# на сервере, под root
adduser deploy             # спросит пароль — задайте длинный
usermod -aG sudo deploy

# сгенерировать стойкий пароль:
tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 24; echo
```

> **Длина важнее набора символов.** 24 случайных буквенно-цифровых символа — около
> 143 бит энтропии, перебор нереален. А `Mirai2026!` словарные атаки берут за
> минуты, сколько бы восклицательных знаков туда ни добавить. Храните пароль
> в менеджере.

Группу `docker` добавим на шаге 10 — она эквивалентна root, поэтому появляется
вместе с самим Docker.


> ✅ **Проверка.** `id deploy` содержит группу `sudo`. Наберите `su - deploy` и введите пароль — должны попасть в его shell.

## 5. SSH по паролю

Оставляем парольную аутентификацию, но убираем всё, что можно убрать без неё.

### Сначала — убедиться, что SSH-сервер вообще установлен

Ubuntu Desktop не ставит его по умолчанию. Признак отсутствия: в `/etc/ssh/` есть
`ssh_config` и `ssh_config.d/`, но нет `sshd_config` — это конфиги **клиента**,
а не демона.

```bash
ls /etc/ssh/
systemctl status ssh --no-pager

# если sshd_config нет — сервер не установлен:
sudo apt install -y openssh-server
sudo systemctl enable --now ssh
```

После установки появятся `/etc/ssh/sshd_config` и каталог `/etc/ssh/sshd_config.d/`.
Проверьте, что основной конфиг действительно подключает этот каталог:

```bash
grep -n '^Include' /etc/ssh/sshd_config
```

> ⚠️ **Если строки `Include` нет**, файлы из `sshd_config.d/` не читаются, и создавать
> `99-mirai.conf` бессмысленно — правки не применятся. Тогда впишите директивы ниже
> прямо в конец `/etc/ssh/sshd_config`, предварительно закомментировав там же старые
> значения тех же параметров: в основном файле выигрывает **первое** вхождение,
> поэтому дубль выше по файлу перебьёт вашу строку.

### Конфигурация демона

```bash
# /etc/ssh/sshd_config.d/99-mirai.conf
Port 2222
PermitRootLogin no
PasswordAuthentication yes
PubkeyAuthentication yes
AllowUsers deploy

# порт открыт в интернет: минимум попыток и времени на них
MaxAuthTries 2
LoginGraceTime 20
MaxStartups 3:50:10

PermitEmptyPasswords no
X11Forwarding no
AllowAgentForwarding no
AllowTcpForwarding no
ClientAliveInterval 300
ClientAliveCountMax 2
```

```bash
# host-ключи должны существовать — ими сервер представляется клиенту.
# Их отсутствие даёт ошибку "no hostkeys available -- exiting".
ls -l /etc/ssh/ssh_host_*
ssh-keygen -A     # создаст недостающие; существующие не тронет

# проверку конфига запускать ТОЛЬКО от root, иначе не прочитает ключи
sudo sshd -t && sudo systemctl restart ssh

# провайдерский cloud-init не перебивает наши настройки?
grep -rE 'PasswordAuthentication|PermitRootLogin|^Port' /etc/ssh/sshd_config /etc/ssh/sshd_config.d/

# итоговые значения после слияния всех файлов
sudo sshd -T | grep -E '^(port|permitrootlogin|passwordauthentication|allowusers|maxauthtries)'
```

> **Host-ключи — это не отказ от парольного входа.** Пара `ssh_host_*` в `/etc/ssh/`
> принадлежит самому серверу и нужна всегда: ею он доказывает клиенту, что это
> действительно он, а не подставная машина. Ваш вход остаётся парольным. Ошибка
> `no hostkeys available -- exiting` означает либо что ключей нет (лечится
> `ssh-keygen -A`), либо что `sshd -t` запущен без `sudo` и не смог их прочитать.

> 🔴 **Порядок файлов в `sshd_config.d` важен.** Ubuntu читает `*.conf` по алфавиту,
> и **первое** вхождение параметра выигрывает. Провайдерский `50-cloud-init.conf`
> идёт раньше нашего `99-mirai.conf`, поэтому его директивы победят. Проверьте
> вывод `grep` и удалите конфликтующие строки, иначе root-логин и порт 22 останутся
> включёнными вопреки конфигу.

Проверяем новый порт из отдельного окна, не закрывая текущую сессию:

```bash
ssh -p 2222 deploy@192.168.88.81
```

Чтобы не набирать порт каждый раз:

```ssh-config
# ~/.ssh/config на ноутбуке
Host mirai
    HostName 192.168.88.81
    User deploy
    Port 2222
```

Дальше достаточно `ssh mirai` и `rsync … mirai:/путь`.


> ✅ **Проверка.** Из нового окна на ноутбуке `ssh -p 2222 deploy@192.168.88.81` спрашивает пароль и пускает. `ssh -p 22 deploy@…` отвечает `Connection refused`. `ssh -p 2222 root@…` не пускает даже с верным паролем.
>
> *Только после того, как новый порт заработал, закрывайте старую сессию. Если что-то пошло не так — машина рядом, чините с консоли.*

## 6. fail2ban

При парольном входе это не гигиена, а основная линия обороны.

```ini
# /etc/fail2ban/jail.local
[DEFAULT]
backend  = systemd
bantime  = 1h
findtime = 10m
maxretry = 3
ignoreip = 127.0.0.1/8 192.168.88.0/24

# порт смотрит в интернет: каждый повторный бан вдвое длиннее предыдущего
bantime.increment = true
bantime.factor    = 2
bantime.maxtime   = 30d

[sshd]
enabled = true
port    = 2222
maxretry = 3

# кто попадался несколько раз — улетает на неделю
[recidive]
enabled  = true
bantime  = 1w
findtime = 1d
maxretry = 3
```

```bash
systemctl enable --now fail2ban
fail2ban-client status sshd      # через сутки здесь будут десятки банов
```

> ⚠️ **`backend = systemd` обязателен на 24.04.** Логи SSH уходят в journald, а
> `/var/log/auth.log` может не заполняться. С дефолтным `backend = auto` fail2ban
> молча не увидит ни одной неудачной попытки: сервис работает, счётчик пустой,
> банов нет. Проверьте, что `Total failed` растёт.

Себя из бана вынимают так — пригодится, когда промахнётесь паролем три раза:

```bash
# с консоли машины
fail2ban-client set sshd unbanip 203.0.113.7
```


> ✅ **Проверка.** `fail2ban-client status sshd` отвечает без ошибок. Через несколько часов в интернете `Total failed` вырастет до десятков — это боты. Если через сутки там ноль, значит fail2ban не читает логи: проверьте `backend = systemd`.

## 7. Файрвол

Снаружи до сервера доходят три порта: 80, 443 и SSH через проброс с внешнего 49222.
Для локальной сети SSH открыт без ограничений, для интернета — с лимитом частоты
подключений.

```bash
# на сервере, под root
ufw default deny incoming
ufw default allow outgoing

# SSH: из локальной сети без ограничений
ufw allow from 192.168.88.0/24 to any port 2222 proto tcp comment 'SSH: LAN'

# SSH снаружи — с лимитом: 6 попыток соединения за 30 с и адрес придерживается
ufw limit 2222/tcp comment 'SSH: internet'

# сайт — отовсюду, сюда приходит трафик с проброса
ufw allow 80/tcp  comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'

ufw --force enable
ufw status verbose
```

> ⚠️ **Три рубежа вокруг парольного SSH.** Порт открыт в интернет, поэтому защита
> выстроена слоями: **роутер** отбрасывает адрес после четырёх соединений подряд
> и держит его в чёрном списке десять дней; **ufw** ограничивает частоту;
> **fail2ban** банит после двух неудачных паролей, удваивая срок при каждом рецидиве
> вплоть до месяца. Плюс нестандартный внешний порт, мимо которого проходит основная
> масса сканеров. Что остаётся вашей ответственностью — длина пароля: всё
> перечисленное замедляет перебор, но не спасает от угаданного `Mirai2026!`.

> ⚠️ **Docker обходит ufw.** Опубликованный порт контейнера пишется прямо
> в `iptables`. Нас это не подводит: Postgres проброшен как `127.0.0.1:5432` и не
> виден даже из локальной сети. Правило на будущее: любой новый порт публикуйте
> с явным `127.0.0.1:`.

> ✅ **Проверка.** `ufw status verbose` показывает `Default: deny (incoming)`
> и четыре правила. С ноутбука в локальной сети работает
> `ssh -p 2222 deploy@192.168.88.81`, с мобильного интернета —
> `ssh -p 49222 deploy@87.251.104.230`. Стандартные порты закрыты:
> `nc -zv 87.251.104.230 22` и `nc -zv 87.251.104.230 2222` не отвечают.


## 8. Диски, SMART, ИБП

На VPS о железе думает провайдер. Здесь диск умрёт у вас, и предупреждение должно
прийти заранее.

```bash
lsblk -o NAME,SIZE,MODEL,MOUNTPOINT
smartctl -a /dev/sda | grep -E 'Model|Health|Reallocated|Wear|Power_On'

# регулярные самотесты и письма при деградации
sed -i 's|^DEVICESCAN.*|DEVICESCAN -a -o on -S on -s (S/../.././02\|L/../../6/03) -m root|' \
    /etc/smartd.conf
systemctl enable --now smartd
```

```bash
sensors-detect --auto
sensors      # в кальянной пыль забивает радиаторы за месяцы, а не годы
```

ИБП с USB-управлением — `nut` корректно погасит систему до того, как сядет батарея.
Внезапное отключение питания у Postgres может стоить последних транзакций.

```bash
apt install -y nut
lsusb              # ИБП виден?
nut-scanner -U     # сгенерирует секцию для ups.conf
```

> **Диск под БД.** Postgres пишет том в `/var/www/mirailounge/data/pg`. Если в
> машине есть SSD и HDD — база должна лежать на SSD. Проверить:
> `df -h /var/www/mirailounge/data` и сверить устройство с `lsblk`.


> ✅ **Проверка.** `smartctl -H /dev/sda` → `PASSED`. `sensors` показывает температуры, а не пустоту. `systemctl is-enabled smartd` → `enabled`.

## 9. Swap (если RAM ≤ 4 ГБ)

```bash
# СНАЧАЛА проверить, что уже есть — установщик Ubuntu обычно создаёт swapfile сам
swapon --show
free -h
ls -lh /swapfile 2>/dev/null
```

> ⚠️ **Если swap уже активен**, перезаписать его нельзя: `fallocate` ответит
> `Text file busy`, а `mkswap` откажется трогать занятое устройство. Размера в 2 ГБ
> и больше обычно достаточно — тогда шаг просто пропускается. Увеличить существующий
> можно только через отключение:
>
> ```bash
> sudo swapoff /swapfile
> sudo rm -f /swapfile
> ```

Каждая команда ниже — от root, поэтому либо `sudo` перед каждой, либо один раз
`sudo -i`. Права `600` обязательны: ядро откажется подключать файл, доступный
на чтение всем.

```bash
# на сервере, под root
fallocate -l 4G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile

# запись в fstab — только если её там ещё нет, иначе задвоится
grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab

echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf
sysctl -w vm.swappiness=10
```

> **Если `fallocate` ругается на файловую систему.** На btrfs и некоторых
> конфигурациях он не годится — файл получится с «дырами», и `swapon` его отвергнет.
> Тогда создавайте универсальным способом:
> `dd if=/dev/zero of=/swapfile bs=1M count=4096 status=progress`.


> ✅ **Проверка.** `swapon --show` выводит строку с нужным размером, `free -h`
> показывает ненулевой Swap. После перезагрузки он должен подняться сам — это
> и проверяет запись в `/etc/fstab`.

## 10. Docker Engine

Нужен именно Docker Engine из репозитория docker.com, а **не** пакет `docker.io`
из Ubuntu и не snap-версия: требуется плагин compose v2 (синтаксис `docker compose`,
без дефиса) и поддержка `env_file` с `required: false` — она используется в обоих
compose-файлах проекта.

### Способ 1 — официальный установщик

Скрипт от Docker сам добавит ключ, репозиторий и поставит все пакеты. Надёжнее
ручной последовательности: там легко споткнуться на правах при записи в системные
каталоги.

```bash
# убрать возможные остатки старых установок
sudo apt remove -y docker docker-engine docker.io containerd runc 2>/dev/null
sudo snap remove docker 2>/dev/null

curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
sudo sh /tmp/get-docker.sh
```

### Способ 2 — вручную

> 🔴 **Только из root-сессии.** Команды ниже пишут в системные каталоги через `>`.
> Перенаправление выполняет ваш shell, а не `sudo`, поэтому построчный
> `sudo команда` здесь не сработает — будет «отказано в доступе». Войдите в root
> один раз: `sudo -i`.

```bash
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  > /etc/apt/sources.list.d/docker.list

apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

### После установки — обоими способами

```bash
sudo usermod -aG docker deploy
sudo systemctl enable --now docker

# лимиты журналов, иначе логи контейнеров со временем съедят диск
sudo tee /etc/docker/daemon.json > /dev/null <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" }
}
JSON
sudo systemctl restart docker
```

`sudo tee` вместо `>` — тот же случай: перенаправление под sudo не сработает,
а `tee` запускается с правами root и пишет сам.

**Перелогиньтесь**, чтобы применилось членство в группе `docker`: выйдите из SSH
и зайдите заново. Без этого `docker` будет требовать sudo.

> ✅ **Проверка.** `docker compose version` → `v2.x`. Под пользователем `deploy`,
> **без sudo**: `docker run --rm hello-world` печатает приветствие. Ответ
> `permission denied while trying to connect to the Docker daemon socket` означает,
> что вы не перелогинились. `docker-compose: command not found` с дефисом — не
> ошибка: в v2 команда пишется через пробел.


---

# Часть II · Проект

> **Маршрут первого деплоя — по шагам, строго по порядку:**
> 1. **11** — создать `/var/www/mirailounge` (владелец `deploy`)
> 2. **13** — положить `.env` (с `APP_ENV=prod` и `MIRAI_TABLE_SIGNING_KEY`) — rsync его НЕ несёт
> 3. **14** — разложить TLS-серты в `docker/ssl/mirailounge.ru/` — rsync их НЕ несёт
> 4. **12** — залить код (первый rsync с ноутбука, с `--include` для png-ассетов)
> 5. **15** — поднять платформенный Postgres (`docker/platform`) — до сайта, создаёт сеть
> 6. **16** — собрать и поднять стек сайта (nginx+php)
> 7. **17** — миграции → `import-json`/`import-recommender`/`import-vitrina` → `create-admin`
> 8. **18** — дымовой тест (главная, `/vitrina`, `/admin/`)
> 9. **19–20** — self-hosted runner + workflow: дальше деплой идёт сам на push в `main`
>
> Шаги 2–3 (`.env` и серты) — до первого rsync: они в исключениях, поэтому приезжают
> только руками, а без них стек не поднимется.

## 11. Каталог проекта

```bash
# на сервере, под root
mkdir -p /var/www/mirailounge
chown -R deploy:deploy /var/www/mirailounge
```

> 🔴 **База данных живёт внутри каталога деплоя.** Том Postgres примонтирован как
> `../../data/pg`, физически это `/var/www/mirailounge/data/pg`. При этом выкладка
> идёт `rsync --delete`. От удаления боевой БД вас отделяет одна строка `/data/`
> в `scripts/deploy-excludes.txt`. Никогда не запускайте rsync с `--delete` без
> `--exclude-from=scripts/deploy-excludes.txt`, и сначала с `--dry-run`.


> ✅ **Проверка.** `ls -ld /var/www/mirailounge` показывает владельца `deploy deploy`.

## 12. Первая заливка кода

На сервере не нужны ни Node, ни Composer: `vendor/` ставится внутри образа PHP при
сборке, а бандлы Vite собирает CI и присылает артефактом. Первую выкладку делаем
с ноутбука — rsync спросит пароль, это разовая операция.

```bash
# с ноутбука, из корня репозитория
cd frontend && npm ci && npm run build && cd ..

rsync -az \
  --include='/public/assets/booking/***' \
  --include='/public/assets/img/vitrina/***' \
  --exclude-from=scripts/deploy-excludes.txt \
  ./ mirai:/var/www/mirailounge/
```

Без `--delete`: каталог пуст, удалять нечего, а риск опечатки максимален.

> ⚠️ **`*.png` в исключениях режет и ассеты.** `scripts/deploy-excludes.txt` исключает
> `*.png` целиком, поэтому картинки карты зала (`public/assets/booking/`, в т.ч.
> `view-a/b.png`, `zal.png`) и витрины (`public/assets/img/vitrina/*.png`) возвращаются
> в выкладку явными `--include` **до** `--exclude-from`. Порядок важен: include выигрывает
> только стоя раньше exclude. Добавляете новый каталог с png-ассетами — добавьте и include
> сюда, и в workflow (шаг 20).

Второй путь, доступный именно на bare-metal — склонировать репозиторий прямо на
машине. Для приватного репозитория понадобится personal access token:

```bash
# на сервере, под deploy
git clone https://github.com/<владелец>/<репозиторий>.git /tmp/mirai
rsync -a --exclude='.git' /tmp/mirai/ /var/www/mirailounge/
rm -rf /tmp/mirai
```


> ✅ **Проверка.** На сервере: `ls /var/www/mirailounge` показывает `composer.json`, `docker/`, `public/`, `src/`. Каталога `vendor/` быть не должно — он живёт внутри образа.

## 13. Файл .env

Лежит только на сервере, в git не попадает, rsync его не трогает (`/.env`
в исключениях). Подхватывается и сервисом `php`, и контейнером Postgres.

```bash
# на сервере, под deploy
cd /var/www/mirailounge
cp .env.example .env
openssl rand -hex 32      # значение для MIRAI_TABLE_SIGNING_KEY
openssl rand -base64 24   # пароль Postgres
nano .env
chmod 600 .env
```

```ini
APP_ENV=prod

POSTGRES_DB=mirailounge
POSTGRES_USER=mirailounge
POSTGRES_PASSWORD=<длинный случайный пароль>
POSTGRES_HOST=postgres
POSTGRES_PORT=5432

MIRAI_TABLE_SIGNING_KEY=<openssl rand -hex 32>

MIRAI_TG_BOT_TOKEN=<новый токен от BotFather>
MIRAI_TG_CHAT_ID=<chat_id уведомлений>
MIRAI_TG_HTTP_PROXY=
MIRAI_TG_API_BASE=

MIRAI_VAPID_PUBLIC=
MIRAI_VAPID_PRIVATE=
MIRAI_VAPID_SUBJECT=mailto:admin@mirailounge.ru
```

> 🔴 **`APP_ENV=prod` меняет поведение.** Ошибки не отдаются в HTTP-ответ, а пустой
> `MIRAI_TABLE_SIGNING_KEY` становится фатальным — `Config::tableSession()` бросает
> исключение. Видите 500 на всех страницах — первым делом проверьте ключ.

> ⚠️ **Пароль Postgres задаётся один раз.** `POSTGRES_PASSWORD` применяется только
> при *инициализации* пустого тома `data/pg`. Поменяете позже — контейнер продолжит
> жить со старым, а приложение получит отказ аутентификации. Смена потом — только
> через `ALTER ROLE` в psql.

Ключи Web Push генерируются после подъёма стека (шаг 16):
`docker compose exec php php scripts/generate-vapid.php`. Менять существующие
нельзя без потери всех подписок.


> ✅ **Проверка.** `ls -l .env` → права `-rw-------`. `grep -c '^[A-Z]' .env` → не меньше 10. Ни одна строка не должна остаться со значением в угловых скобках: `grep '<' .env` — вывод пустой.

## 14. TLS-сертификаты

Let's Encrypt не используется — сертификаты покупные (Reg.ru). nginx ждёт их по
путям из `docker/nginx/conf.d/00-ssl-mirailounge.conf`:

```
docker/ssl/mirailounge.ru/
├── fullchain.pem   # certificate.crt + certificate_ca.crt, именно в таком порядке
└── privkey.pem     # certificate.key
```

```bash
# с ноутбука
scp certificate.crt certificate_ca.crt certificate.key \
    mirai:/var/www/mirailounge/docker/ssl/
```

```bash
# на сервере, под deploy
cd /var/www/mirailounge/docker/ssl
chmod +x setup-certs.sh && ./setup-certs.sh
chmod 600 mirailounge.ru/privkey.pem certificate.key

# ключ соответствует сертификату? хеши должны совпасть
openssl x509 -noout -modulus -in mirailounge.ru/fullchain.pem | openssl md5
openssl rsa  -noout -modulus -in mirailounge.ru/privkey.pem   | openssl md5
```

✅ `/docker/ssl/` в исключениях rsync, подкаталоги домена — в `.gitignore`.
Разложенные сертификаты переживают любую выкатку.


> ✅ **Проверка.** Две команды `openssl … | openssl md5` вывели **одинаковые** хеши. `ls docker/ssl/mirailounge.ru/` показывает ровно два файла.
>
> *Разные хеши означают, что ключ не от этого сертификата — nginx не запустится. Возьмите правильную пару у поставщика.*

## 15. Платформа: общий Postgres

База вынесена в отдельный стек `docker/platform/` — один инстанс на все сайты хоста
(mirailounge, miraileague, шкл65). Редеплой любого сайта не трогает БД. Стек
создаёт внешнюю сеть `mirai-platform`, поэтому поднимать его нужно **до** сайта.

```bash
# на сервере, под deploy
cd /var/www/mirailounge
docker compose -f docker/platform/docker-compose.yml up -d
docker compose -f docker/platform/docker-compose.yml ps    # healthy?
```

Каждому следующему сайту — своя база и роль:

```bash
docker exec -it mirai-postgres psql -U mirailounge -d postgres \
  -c "CREATE ROLE miraileague LOGIN PASSWORD '***';"
docker exec -it mirai-postgres psql -U mirailounge -d postgres \
  -c "CREATE DATABASE miraileague OWNER miraileague;"
```


> ✅ **Проверка.** `docker compose -f docker/platform/docker-compose.yml ps` показывает `mirai-postgres` в состоянии `healthy` (не просто `running`). Проверка соединения: `docker exec mirai-postgres psql -U mirailounge -c 'select 1'`.

## 16. Стек сайта

```bash
# на сервере, под deploy
cd /var/www/mirailounge/docker
docker compose build php
docker compose up -d
docker compose ps
```

> 🔴 **`docker-compose.override.yml` не должен попасть на прод.** Compose
> подхватывает его автоматически, а он переводит стек в dev-режим: HTTP без SSL,
> dev-образ PHP и `vendor/` с хоста (на сервере его нет — контейнер упадёт). Файл
> в исключениях rsync, но если копировали архивом или клонировали git — удалите
> вручную.

Проверка: `ls docker/docker-compose.override.yml docker/nginx/dev 2>/dev/null` —
вывод должен быть пустым.


> ✅ **Проверка.** `docker compose ps` показывает `mirai-nginx` и `mirai-php` как `Up`. `curl -I http://127.0.0.1/` отвечает 200 или 301, но не `502` и не `Connection refused`.

## 17. Миграции, админ, импорт данных

`phinx.php` берёт креды из того же `Config`, что и приложение, — миграции и рантайм
гарантированно смотрят в одну базу.

```bash
cd /var/www/mirailounge/docker
docker compose exec -T php php vendor/bin/phinx migrate -c phinx.php
docker compose exec -T php php vendor/bin/phinx status  -c phinx.php
```

> 🔴 **Данные прежнего прода — с этой же машины.** До переустановки здесь работал
> прод на Windows с Docker, и боевые меню, галерея, VIP и заказы лежали в его каталоге.
> Если Ubuntu ставили на тот же раздел с форматированием — данные потеряны. Если
> система встала на другой диск или раздел уцелел — найдите их **до** импорта:
>
> ```bash
> lsblk -f                        # какие разделы есть и чем заняты
> sudo mkdir -p /mnt/old && sudo mount /dev/sdb2 /mnt/old
> sudo find /mnt/old -name 'menu.json' -o -name 'orders.json' 2>/dev/null
> ```
>
> Нашли — скопируйте `data/*.json` и каталоги `uploads/` в `/var/www/mirailounge/`,
> и только потом запускайте импорт. Не нашли — меню заполняется заново через админку.

### Порядок первого наполнения базы (строго сверху вниз)

На свежем сервере Postgres пуст. Автодеплой применяет **только миграции** (схему);
данные и справочники заливаются разово, вручную, в этом порядке:

```bash
cd /var/www/mirailounge/docker

# 1) Схема (идемпотентно; тот же шаг делает и автодеплой)
docker compose exec -T php php vendor/bin/phinx migrate -c phinx.php
docker compose exec -T php php vendor/bin/phinx status  -c phinx.php   # все up, ни одной down

# 2) Меню/галерея/VIP/… из старых JSON — ТОЛЬКО если положили data/*.json (см. выше).
#    Запускать один раз: повтор затрёт правки из новой админки старым снимком.
docker compose exec -T php php bin/import-json

# 3) Граф рекомендаций (гастропары). Идемпотентно, можно повторять.
docker compose exec -T php php bin/import-recommender

# 4) Витрина кальянов: создаёт недостающие кальяны-товары + чаши/напитки/геометрию.
#    Идемпотентно (кальяны — ON CONFLICT DO NOTHING, справочники перезаливаются).
docker compose exec -T php php bin/import-vitrina

# 5) Первый администратор (роль owner). Пароль — длинный.
docker compose exec -T php php bin/create-admin isinuytin '<сильный пароль>' owner
```

| Утилита | Что делает | Когда |
|---|---|---|
| `bin/migrate` | Обёртка над Phinx (`migrate`/`status`/`rollback`/`create`) | каждый деплой (авто) |
| `bin/import-json` | Перенос `data/*.json` → Postgres | **один раз** (иначе затрёт правки) |
| `bin/import-recommender` | Граф гастропар (`resources/menu-association-model.json`) | по необходимости |
| `bin/import-vitrina` | Витрина кальянов: кальяны-товары + чаши/напитки/геометрия | по необходимости |
| `bin/create-admin` | Создать/сбросить админа (owner/admin/manager/staff) | по необходимости |

> ⚠️ **Витрина без `bin/import-vitrina` будет пустой.** Экран `/vitrina` берёт кальяны/
> чаши/напитки из таблиц `hookah_showcase`/`hookah_bowls`/`hookah_drinks` — их заполняет
> только этот импорт. Он же заводит в меню 5 кальянов (Tropic Rave, Electric Matcha,
> Mirai, Дуо, Трио); «Кальян Классика» уже есть — не дублируется.


> ✅ **Проверка.** `phinx status` — все миграции `up`. Таблицы:
> `docker exec mirai-postgres psql -U mirailounge -d mirailounge -c '\dt'` — среди них
> `hookah_showcase`, `hookah_bowls`, `hookah_drinks`, `bookings`, `hall_zones`.
> `curl -s http://127.0.0.1/api/hookah-showcase | head -c 60` содержит `"ok":true`.

## 18. DNS и дымовой тест

A-записи `mirailounge.ru` и `www.mirailounge.ru` → `87.251.104.230`, белый адрес
роутера. Со времён прода на Windows они уже такие — сверьте: `dig +short mirailounge.ru`.

```bash
# с сервера — минуя роутер, проверяем сам стек
curl -I --resolve mirailounge.ru:443:127.0.0.1 https://mirailounge.ru/

# снаружи — проверяем весь путь целиком, вместе с пробросом
curl -I --resolve mirailounge.ru:443:87.251.104.230 https://mirailounge.ru/
```

Дальше руками по живому сайту:

- `/` — главная грузится, меню открывается, бандлы из `/dist/` подхватились
- меню → **«Кальян»** открывает `/vitrina` — карусель кальянов, свайп чаш меняет
  цену; кнопка «Заказать» по QR-столу кладёт кальян+чашу в заказ
- `/booking/map` — 3D-карта зала (изометрия + столы), клик по столу → бронь
- `/admin/` — вход под созданной учёткой; плитки **Витрина**, **Брони**, **Меню**
- `/admin/vitrina` — правка кальянов/чаш/напитков; `/admin/booking/hall-editor` — карта зала
- заказ от имени гостя по QR-ссылке стола → уведомление в Telegram (когда настроен)
- `/orders/` — панель зала видит заявку

> **Hairpin NAT.** Из локальной сети запрос к `87.251.104.230` уходит на роутер
> и должен вернуться внутрь на сервер — это поддерживается не всеми роутерами и не
> всегда включено. Если изнутри сайт не открывается, а снаружи открывается —
> с сервером всё в порядке, дело в роутере, и чинить это нужно только ради удобства
> сотрудников. Честная проверка одна: откройте сайт с телефона по мобильному
> интернету, отключив Wi-Fi.


> ✅ **Проверка.** `curl -I https://mirailounge.ru/` с мобильного интернета отвечает 200, замок в браузере без предупреждений. Вход в `/admin/` работает.

---

# Часть III · Автодеплой

## 19. Self-hosted runner

Runner живёт на самой машине и сам забирает задания с GitHub исходящим HTTPS.
Входящий SSH для деплоя не нужен — это снимает главный риск парольной схемы.

> 🔴 **Репозиторий обязан быть приватным.** На публичном репозитории self-hosted
> runner — дыра на выполнение произвольного кода: любой присылает pull request,
> и его workflow исполняется на вашем сервере с правами `deploy`, то есть
> с доступом к `.env`, сертификатам и Docker.

Токен регистрации: **Settings → Actions → Runners → New self-hosted runner**.
Одноразовый, живёт около часа.

```bash
# на сервере, под deploy (не root!)
mkdir -p ~/actions-runner && cd ~/actions-runner

# последняя версия runner'а — узнаём у GitHub, не хардкодим
VER=$(curl -s https://api.github.com/repos/actions/runner/releases/latest \
      | grep '"tag_name"' | sed -E 's/.*"v([^"]+)".*/\1/')
echo "версия: $VER"

curl -sLO "https://github.com/actions/runner/releases/download/v${VER}/actions-runner-linux-x64-${VER}.tar.gz"
tar xzf "actions-runner-linux-x64-${VER}.tar.gz"
sudo ./bin/installdependencies.sh
```

```bash
./config.sh \
  --url https://github.com/<владелец>/<репозиторий> \
  --token <токен из интерфейса GitHub> \
  --name mirai-host \
  --labels self-hosted,linux,x64,mirai-prod \
  --work _work \
  --unattended
```

Ставим сервисом, чтобы runner поднимался вместе с машиной — важно на железе,
которое перезагружается после отключений света:

```bash
sudo ./svc.sh install deploy
sudo ./svc.sh start
sudo ./svc.sh status
```

В **Settings → Actions → Runners** появится `mirai-host` со статусом *Idle*.
Логи выполнения — `~/actions-runner/_diag/`.

> ⚠️ **Runner работает под `deploy`** — состоит в группе `docker` и владеет
> каталогом проекта. Любой workflow в репозитории может прочитать `.env` и приватный
> ключ сертификата. Ограничьте круг людей с правом push, а на
> **Settings → Actions → General** запретите запуск workflow из форков.

> **Sudo без пароля не потребуется.** Пайплайн ниже написан так, чтобы обходиться
> без `sudo`: rsync идёт в каталог, которым `deploy` владеет, Docker доступен через
> группу. Не добавляйте `NOPASSWD` в sudoers ради CI — это отдаст root любому, кто
> сможет запушить в репозиторий.


> ✅ **Проверка.** В GitHub на странице **Settings → Actions → Runners** строка `mirai-host` горит зелёным со статусом `Idle`. На сервере: `sudo ~/actions-runner/svc.sh status` → `active (running)`.

## 20. Новый workflow

Текущий `.github/workflows/deploy.yml` заточен под SSH-ключ и rsync по сети.
С self-hosted runner проще: тесты гоняются на стороне GitHub, бандлы передаются
артефактом, выкладку делает сам сервер локальным rsync. Секреты `DEPLOY_*`
и `DEPLOY_SSH_KEY` больше не нужны — удалите их из репозитория.

```yaml
name: CI/CD

on:
  push:
    branches: [main]
  pull_request:
  workflow_dispatch:

concurrency:
  group: deploy-production
  cancel-in-progress: true

jobs:
  ci:
    runs-on: ubuntu-latest
    env:
      APP_ENV: dev
      MIRAI_TABLE_SIGNING_KEY: ci-signing-key-0123456789abcdef
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo, pdo_pgsql, fileinfo, zip
          coverage: none

      - run: composer install --no-interaction --no-progress --prefer-dist
      - run: php vendor/bin/phpunit --testsuite unit
      - run: php vendor/bin/phpstan analyse src --level=5 --no-progress

      - uses: actions/setup-node@v4
        with:
          node-version: '22'
      - run: cd frontend && npm ci && npm run build

      # собранные бандлы передаём на сервер артефактом,
      # чтобы на железе не держать Node
      - uses: actions/upload-artifact@v4
        with:
          name: dist
          path: public/dist/
          retention-days: 3

  deploy:
    needs: ci
    if: github.ref == 'refs/heads/main'
    runs-on: [self-hosted, mirai-prod]
    env:
      DEPLOY_PATH: /var/www/mirailounge
    steps:
      - uses: actions/checkout@v4

      - uses: actions/download-artifact@v4
        with:
          name: dist
          path: public/dist/

      # локальная выкладка: без сети, без SSH, без паролей
      - name: Выкладка в DEPLOY_PATH
        run: |
          rsync -a --delete \
            --include='/public/assets/booking/***' \
            --include='/public/assets/img/vitrina/***' \
            --exclude-from=scripts/deploy-excludes.txt \
            ./ "$DEPLOY_PATH/"

      - name: Платформа, стек, миграции
        run: |
          cd "$DEPLOY_PATH/docker"
          docker compose -f platform/docker-compose.yml up -d
          docker compose build php
          docker compose up -d
          docker compose exec -T php php vendor/bin/phinx migrate -c phinx.php

      - name: Проверка живости
        run: |
          sleep 5
          curl -fsS -o /dev/null -w '%{http_code}\n' \
            --resolve mirailounge.ru:443:127.0.0.1 https://mirailounge.ru/
```

**Что изменилось против прежнего файла.** Убраны шаг `SSH key`, `ssh-keyscan`
и rsync по сети — вместе с ними исчезла необходимость держать приватный ключ
в чужом облаке. Бандлы Vite едут артефактом, поэтому Node на сервере не нужен.
Добавлена проверка живости: если после выкатки сайт не отвечает, job краснеет,
а не завершается молча.

Ручная выкладка, когда GitHub недоступен:

```bash
cd /var/www/mirailounge/docker
docker compose build php && docker compose up -d
docker compose exec -T php php vendor/bin/phinx migrate -c phinx.php
```


> ✅ **Проверка.** Сделайте пустой коммит и запушьте в `main`: `git commit --allow-empty -m 'проверка деплоя' && git push`. Во вкладке Actions оба job зелёные, а шаг «Проверка живости» вывел `200`.

---

# Часть IV · Эксплуатация

## 21. Бэкапы

На bare-metal есть роскошь, недоступная на VPS — второй физический диск.

```bash
# /usr/local/bin/mirai-backup.sh
#!/usr/bin/env bash
set -e
DST=/mnt/backup/mirai       # отдельный диск, не системный!
DATE=$(date +%F)
mkdir -p "$DST"

docker exec mirai-postgres pg_dumpall -U mirailounge | gzip > "$DST/pg-$DATE.sql.gz"

tar czf "$DST/files-$DATE.tar.gz" -C /var/www/mirailounge \
  .env docker/ssl \
  public/assets/img/menu/uploads \
  public/assets/img/gallery/uploads \
  public/assets/img/vip/partner_uploads \
  public/assets/img/hall/uploads \
  public/assets/img/vitrina/uploads \
  public/assets/img/interior 2>/dev/null || true

find "$DST" -name '*.gz' -mtime +14 -delete
```

```bash
chmod +x /usr/local/bin/mirai-backup.sh
echo '15 4 * * * root /usr/local/bin/mirai-backup.sh' > /etc/cron.d/mirai-backup
```

> 🔴 **Копия в том же корпусе — не бэкап.** Пожар, залив, кража и скачок напряжения
> уничтожают оба диска разом. Для физической машины в заведении это не абстракция.
> Настройте выгрузку `/mnt/backup/mirai` за пределы здания — `rclone` в облако или
> `rsync` на домашнюю машину. И раз в квартал проверяйте восстановление.


> ✅ **Проверка.** Запустите скрипт руками: `sudo /usr/local/bin/mirai-backup.sh`. В `/mnt/backup/mirai/` появились два файла, и `gunzip -t` на дампе проходит без ошибок.

## 22. Диагностика

| Симптом | Где искать |
|---|---|
| 502 на всех страницах | Контейнер php не поднялся: `docker compose logs php --tail=100`. Частая причина — пустой `MIRAI_TABLE_SIGNING_KEY` при `APP_ENV=prod` |
| `could not translate host name "postgres"` | Платформа не поднята или нет сети: `docker network ls \| grep platform` |
| `password authentication failed` | `POSTGRES_PASSWORD` изменили после инициализации тома. Чинится через `ALTER ROLE` |
| Страницы без стилей | Нет `public/dist/` — артефакт не доехал. Проверьте шаг `download-artifact` в логе job |
| Ошибка SSL / нет HTTPS | Нет `docker/ssl/mirailounge.ru/{fullchain,privkey}.pem`. `docker compose exec nginx nginx -t` |
| Не приходит Telegram | `docker compose logs php \| grep -i telegram`. Бот в чате? При превращении группы в супергруппу id меняется на `-100…` |
| Job висит в очереди | Runner offline: `sudo ~/actions-runner/svc.sh status`, логи в `~/actions-runner/_diag/` |
| Не пускает по SSH | Вероятно, поймали свой же fail2ban. С консоли: `fail2ban-client set sshd unbanip <ваш IP>` |
| Сайт пропал после отключения света | BIOS-параметр Restore on AC Power Loss (шаг 1) и `systemctl is-enabled docker` |

### Повседневные команды

```bash
cd /var/www/mirailounge/docker
docker compose ps
docker compose logs -f php
docker compose logs -f nginx
docker compose restart php nginx
docker compose exec php sh
docker exec -it mirai-postgres psql -U mirailounge -d mirailounge
docker system df                 # сколько занято
docker system prune -f           # подчистить мусор сборок
```

### Здоровье железа

```bash
uptime; free -h; df -h
sensors                          # температуры
sudo smartctl -H /dev/sda        # здоровье диска
sudo fail2ban-client status sshd # сколько ботов забанено
journalctl -p err -b --no-pager | tail -30
```

---

## Контрольный список

- [ ] BIOS: включение после пропадания питания
- [ ] Сон и реакция на крышку отключены
- [ ] Статический адрес `192.168.88.81` в netplan
- [ ] Адрес зарезервирован на роутере или вне DHCP-пула
- [ ] Проброс 80/443 ведёт на `192.168.88.81`
- [ ] SSH проброшен на нестандартный внешний порт (49222)
- [ ] Фильтр брутфорса на роутере активен
- [ ] Система обновлена, часовой пояс задан
- [ ] Пользователь `deploy` с длинным паролем
- [ ] SSH: порт 2222, root запрещён, `AllowUsers deploy`
- [ ] fail2ban с растущими банами (`bantime.increment`)
- [ ] Конфликты с `50-cloud-init.conf` устранены
- [ ] fail2ban с `backend = systemd`, счётчик растёт
- [ ] `ufw` активен: 80, 443, 2222 с лимитом частоты
- [ ] SMART-мониторинг включён, диск здоров
- [ ] Docker Engine + compose v2, лимиты логов
- [ ] `/var/www/mirailounge` принадлежит `deploy`
- [ ] `.env` заполнен, `chmod 600`, `APP_ENV=prod`
- [ ] `MIRAI_TABLE_SIGNING_KEY` — новый случайный
- [ ] Telegram-бот перевыпущен, старый токен отозван
- [ ] Сертификаты перевыпущены и разложены
- [ ] Платформа Postgres поднята и healthy
- [ ] Стек сайта собран и запущен
- [ ] Миграции применены, `phinx status` чистый
- [ ] Админ создан, вход в `/admin/` работает
- [ ] Данные со старого прода найдены (или признаны утраченными)
- [ ] `import-json` (если были старые JSON), `import-recommender`, `import-vitrina` выполнены
- [ ] `/vitrina` показывает кальяны/чаши, `/booking/map` — карту зала
- [ ] DNS ведёт на `87.251.104.230`, HTTPS открывается с мобильного интернета
- [ ] Репозиторий приватный
- [ ] Runner зарегистрирован, статус Idle, автозапуск
- [ ] Секреты `DEPLOY_*` удалены из репозитория
- [ ] Бэкап на отдельный диск и за пределы здания
- [ ] `docker-compose.override.yml` на сервере отсутствует
