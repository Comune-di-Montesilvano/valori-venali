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
  --security-opt no-new-privileges:true --cap-drop ALL \
  --cap-add CHOWN --cap-add DAC_OVERRIDE --cap-add FOWNER --cap-add SETGID --cap-add SETUID \
  --read-only --tmpfs /run/mysqld --tmpfs /tmp \
  --health-cmd "healthcheck.sh --connect --innodb_initialized" \
  --health-interval 5s --health-retries 30 \
  "$DB_IMAGE" >/dev/null

echo "Attendo MariaDB ($DB_IMAGE)..."
for _ in $(seq 1 60); do
  [ "$(docker inspect -f '{{.State.Health.Status}}' smoke-db)" = healthy ] && break
  sleep 2
done
[ "$(docker inspect -f '{{.State.Health.Status}}' smoke-db)" = healthy ] || { docker logs smoke-db; exit 1; }

# Porta e utente dall'immagine (fino a v2.4.x root, poi www-data; porta 80).
# Le immagini non-root girano con le stesse restrizioni del compose di produzione.
APP_PORT_IN="$(docker inspect -f '{{range $p, $_ := .Config.ExposedPorts}}{{$p}} {{end}}' "$IMAGE" | grep -oE '[0-9]+' | sort -n | tail -1)"
APP_USER="$(docker inspect -f '{{.Config.User}}' "$IMAGE")"
HARDEN=()
if [ -n "$APP_USER" ]; then
  HARDEN=(--security-opt no-new-privileges:true --cap-drop ALL --read-only
    --sysctl net.ipv4.ip_unprivileged_port_start=0
    --tmpfs /tmp --tmpfs /var/run/apache2 --tmpfs /var/lock/apache2
    --mount type=volume,dst=/var/www/html/uploads)
fi
echo "App: utente '${APP_USER:-root}', porta $APP_PORT_IN"

docker run -d --name smoke-app --network "$NET" -p "${PORT}:${APP_PORT_IN}" \
  "${HARDEN[@]}" \
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

# Il 200 non basta: con display_errors un Fatal (es. tabella mancante) esce nella
# pagina con 200 e non nei log. Le pagine PHP devono essere pulite e il form di
# login deve avere il token CSRF (sessione scrivibile + DB raggiungibile).
for path in /index.php /admin/login.php; do
  body="$(curl -s "$BASE$path")"
  if grep -qE '(Fatal|Parse) error|Warning</b>|Errore di connessione al database' <<<"$body"; then
    echo "FAIL errore PHP/DB nella pagina $path"; fail=1
  fi
done
if grep -q 'name="csrf_token" value="[0-9a-f]\{64\}"' <<<"$(curl -s "$BASE/admin/login.php")"; then
  echo "OK   token CSRF nel form di login"
else
  echo "FAIL token CSRF assente nel form di login"; fail=1
fi
# Asset self-hosted: solo se la sorgente li prevede (i tag vecchi usano il CDN)
if [ -f package.json ]; then
  check /assets/bootstrap-italia/css/bootstrap-italia.min.css
  check /assets/bootstrap-italia/js/bootstrap-italia.bundle.min.js
  # Il nome dei file font cambia tra versioni: lo ricavo da loadFonts() nel bundle.
  # sed -n 1p e non head -1: head chiude la pipe, grep prende SIGPIPE e con
  # pipefail lo script esce (141) a seconda dei tempi.
  FONT="$(curl -s "$BASE/assets/bootstrap-italia/js/bootstrap-italia.bundle.min.js" \
    | grep -o 'Titillium_Web/[A-Za-z0-9_.-]*\.woff2' | sed -n 1p)"
  if [ -n "$FONT" ]; then check "/assets/bootstrap-italia/fonts/$FONT"; else echo "FAIL font non trovato nel bundle"; fail=1; fi
fi

if [ -n "$APP_USER" ]; then
  # Filesystem in sola lettura: il codice non è scrivibile, uploads/ sì
  if docker exec smoke-app sh -c 'touch /var/www/html/index.php' 2>/dev/null; then
    echo "FAIL codice scrivibile dal processo web"; fail=1
  else
    echo "OK   codice in sola lettura"
  fi
  # Un .php in uploads/ non viene mai eseguito né servito
  docker exec smoke-app sh -c 'echo "<?php echo \"ESEGUITO\";" > /var/www/html/uploads/smoke.php'
  code="$(curl -s -o smoke-upload.out -w '%{http_code}' "$BASE/uploads/smoke.php")"
  if [ "$code" = 403 ] && ! grep -q ESEGUITO smoke-upload.out; then
    echo "OK   $code /uploads/smoke.php (PHP bloccato)"
  else
    echo "FAIL $code /uploads/smoke.php (PHP eseguibile in uploads)"; fail=1
  fi
  rm -f smoke-upload.out
fi

if docker logs smoke-app 2>&1 | grep -E "PHP (Fatal|Parse|Warning|Deprecated)"; then
  echo "FAIL errori PHP nei log"; fail=1
fi

exit "$fail"
