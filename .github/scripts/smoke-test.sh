#!/usr/bin/env bash
# Smoke test dell'immagine app: avvia MariaDB (schema da initdb/) + app,
# verifica che home, admin login e asset self-hosted rispondano 200 e che
# nei log non ci siano errori PHP. Usato da test.yml e publish-latest.yml:
# è il gate che rende sicuro l'auto-merge Dependabot.
#
# Uso: .github/scripts/smoke-test.sh <immagine-app>
set -euo pipefail

IMAGE="${1:?uso: smoke-test.sh <immagine-app>}"
DB_IMAGE="$(sed -n 's/^\s*image:\s*\(mariadb:.*\)$/\1/p' docker-compose.yml | head -1)"
NET="smoke-$$"
PORT=18080
BASE="http://localhost:${PORT}"

cleanup() {
  docker logs smoke-app > smoke-app.log 2>&1 || true
  docker rm -f smoke-app smoke-db >/dev/null 2>&1 || true
  docker network rm "$NET" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create "$NET" >/dev/null

docker run -d --name smoke-db --network "$NET" --network-alias db \
  -e MARIADB_ROOT_PASSWORD=smoke-root \
  -e MARIADB_DATABASE=valori_venali \
  -e MARIADB_USER=vvenali \
  -e MARIADB_PASSWORD=smoke-pass \
  -v "$PWD/initdb:/docker-entrypoint-initdb.d:ro" \
  --health-cmd "healthcheck.sh --connect --innodb_initialized" \
  --health-interval 5s --health-retries 30 \
  "$DB_IMAGE" >/dev/null

echo "Attendo MariaDB ($DB_IMAGE)..."
for _ in $(seq 1 60); do
  [ "$(docker inspect -f '{{.State.Health.Status}}' smoke-db)" = healthy ] && break
  sleep 2
done
[ "$(docker inspect -f '{{.State.Health.Status}}' smoke-db)" = healthy ] || { docker logs smoke-db; exit 1; }

docker run -d --name smoke-app --network "$NET" -p "${PORT}:80" \
  -e DB_HOST=db -e DB_PORT=3306 \
  -e DB_NAME=valori_venali -e DB_USER=vvenali -e DB_PASS=smoke-pass \
  -e APP_URL="$BASE" \
  -e APP_SECRET=smoke-test-secret-0123456789abcdef0123 \
  -e APP_ADMIN_USER=admin -e APP_ADMIN_PASS=smoke-admin-pass \
  -e COMUNE_NOME="Comune di Test" -e COMUNE_PROVINCIA=XX \
  "$IMAGE" >/dev/null

for _ in $(seq 1 30); do
  curl -fs -o /dev/null "$BASE/index.php" && break
  sleep 1
done

fail=0
check() {
  local path="$1" code
  code="$(curl -s -o /dev/null -w '%{http_code}' "$BASE$path")"
  if [ "$code" = 200 ]; then echo "OK   $code $path"; else echo "FAIL $code $path"; fail=1; fi
}

check /index.php
check /admin/login.php
# Asset self-hosted: solo se la sorgente li prevede (i tag vecchi usano il CDN)
if [ -f package.json ]; then
  check /assets/bootstrap-italia/css/bootstrap-italia.min.css
  check /assets/bootstrap-italia/js/bootstrap-italia.bundle.min.js
  # Il nome dei file font cambia tra versioni: lo ricavo da loadFonts() nel bundle
  FONT="$(curl -s "$BASE/assets/bootstrap-italia/js/bootstrap-italia.bundle.min.js" \
    | grep -o 'Titillium_Web/[A-Za-z0-9_.-]*\.woff2' | head -1)"
  if [ -n "$FONT" ]; then check "/assets/bootstrap-italia/fonts/$FONT"; else echo "FAIL font non trovato nel bundle"; fail=1; fi
fi

if docker logs smoke-app 2>&1 | grep -E "PHP (Fatal|Parse|Warning|Deprecated)"; then
  echo "FAIL errori PHP nei log"; fail=1
fi

exit "$fail"
