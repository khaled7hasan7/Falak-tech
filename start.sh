#!/usr/bin/env bash
# Falak Console — vendor side. Bound to 127.0.0.1 on purpose: this process
# holds the key that signs every customer licence.

set -eu

cd "$(dirname "$0")"

PORT="${PORT:-8787}"
PHP="$(command -v php || echo /c/php/php.exe)"

[ -x "$PHP" ] || { echo "PHP not found. Install it, or set PHP= in this script." >&2; exit 1; }

echo
echo "  لوحة فلك  >  http://127.0.0.1:$PORT"
echo "  Ctrl+C للإيقاف."
echo

exec "$PHP" -S "127.0.0.1:$PORT" -t public public/router.php
