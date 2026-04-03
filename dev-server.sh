#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if ! command -v php >/dev/null 2>&1; then
  echo "php not found in PATH" >&2
  exit 1
fi
echo "Open http://127.0.0.1:8080 (document root: public/)"
exec php -S 127.0.0.1:8080 -t public router.php
