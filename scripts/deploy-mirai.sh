#!/usr/bin/env bash
set -euo pipefail

# Server-side deploy helper.
#
# Assumes:
# - Repo clone: /home/deploy/repos/mirai_lounge
# - Webroot:    /home/deploy/mirai_lounge
# - This script is invoked on the server (as root or via sudo)

REPO="${REPO:-/home/deploy/repos/mirai_lounge}"
DST="${DST:-/home/deploy/mirai_lounge}"

sudo -u deploy -H git -C "$REPO" pull --ff-only

rsync -a --delete \
  --exclude ".git/" \
  --exclude ".DS_Store" \
  --exclude "data/menu.json" \
  --exclude "public/assets/img/menu/uploads/" \
  "$REPO/" "$DST/"

sudo chgrp -R www-data "$DST/data" "$DST/public/assets/img/menu/uploads" || true
sudo chmod -R 2775 "$DST/data" "$DST/public/assets/img/menu/uploads" || true

echo "OK"

