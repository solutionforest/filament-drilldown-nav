#!/usr/bin/env bash
# Starts a real Octane worker (roadrunner | swoole | frankenphp) with ONE worker, runs the smoke test
# against it, and always stops the server. Usage: bin/octane-smoke.sh roadrunner
set -uo pipefail

SERVER="${1:-roadrunner}"
PORT="${OCTANE_PORT:-8766}"
cd "$(dirname "$0")/.."

export APP_ENV=local APP_DEBUG=true DEMO_AUTOLOGIN=true OCTANE_SERVER="$SERVER" SESSION_DRIVER=file CACHE_STORE=file

if [ "$SERVER" = "swoole" ] && ! php -m | grep -qi '^swoole$'; then
  echo "SKIP: the swoole PHP extension is not installed"; exit 0
fi

[ -f .env ] || cp .env.example .env
php artisan key:generate --force >/dev/null
touch database/database.sqlite
php artisan migrate:fresh --seed --force >/dev/null

case "$SERVER" in
  roadrunner) [ -x ./rr ] || php artisan octane:install --server=roadrunner --no-interaction >/dev/null 2>&1 ;;
  frankenphp) [ -x ./frankenphp ] || php artisan octane:install --server=frankenphp --no-interaction >/dev/null 2>&1 ;;
esac

LOG="$(mktemp)"
setsid php artisan octane:start --server="$SERVER" --host=127.0.0.1 --port="$PORT" --workers=1 --max-requests=100000 >"$LOG" 2>&1 &
PID=$!
stop() { kill -- -"$PID" 2>/dev/null || kill "$PID" 2>/dev/null; sleep 1; }
trap stop EXIT

for i in $(seq 1 60); do
  curl -s -o /dev/null "http://127.0.0.1:$PORT/admin/login" && break
  sleep 1
  if ! kill -0 "$PID" 2>/dev/null; then echo "Octane ($SERVER) exited early:"; tail -20 "$LOG"; exit 3; fi
done

php bin/octane-smoke.php "http://127.0.0.1:$PORT"
STATUS=$?
if [ "$STATUS" -ne 0 ]; then echo "--- server log ---"; tail -20 "$LOG"; fi
exit "$STATUS"
