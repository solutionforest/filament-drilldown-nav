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

# Server binaries are cached outside the repo so a CI runner downloads each one once. Downloads avoid
# the unauthenticated GitHub API (rate-limited per IP): RoadRunner's installer uses $GITHUB_TOKEN when
# set, and FrankenPHP is fetched from its public release URL.
CACHE="${OCTANE_BIN_CACHE:-$HOME/.cache/octane-smoke}"
mkdir -p "$CACHE"

case "$SERVER" in
  roadrunner)
    if [ ! -x ./rr ]; then
      if [ -x "$CACHE/rr" ]; then cp "$CACHE/rr" ./rr
      else php artisan octane:install --server=roadrunner --no-interaction >/dev/null 2>&1 && cp ./rr "$CACHE/rr"; fi
    fi
    [ -x ./rr ] || { echo "Could not obtain the RoadRunner binary"; exit 4; } ;;
  frankenphp)
    if [ ! -x ./frankenphp ]; then
      ARCH="$(uname -m)"; case "$ARCH" in arm64) ARCH=aarch64 ;; esac   # x86_64 | aarch64
      BIN="$CACHE/frankenphp-$ARCH"                                      # cached per architecture
      if [ ! -x "$BIN" ]; then
        curl -fsSL -o "$BIN" "https://github.com/php/frankenphp/releases/latest/download/frankenphp-linux-$ARCH" && chmod +x "$BIN"
      fi
      [ -x "$BIN" ] && cp "$BIN" ./frankenphp
    fi
    [ -x ./frankenphp ] || { echo "Could not obtain the FrankenPHP binary"; exit 4; } ;;
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
