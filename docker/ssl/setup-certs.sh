#!/bin/bash
# Положите в папку docker/ssl/ файлы от Reg.ru, затем запустите: ./setup-certs.sh

cd "$(dirname "$0")"
mkdir -p mirailounge.ru

CERT="certificate.crt"
CA="certificate_ca.crt"
KEY="certificate.key"

[ ! -f "$CERT" ] && echo "Не найден: $CERT" && exit 1

cat "$CERT" "$CA" > mirailounge.ru/fullchain.pem
cp "$KEY" mirailounge.ru/privkey.pem

echo "Готово. Файлы в docker/ssl/mirailounge.ru/"
