#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
container="${PHP_CONTAINER:-docker-environment-frankenphp-1}"
docker inspect "$container" >/dev/null 2>&1 || { echo "Start the shared PHP environment first (see README.md)." >&2; exit 1; }
docker network inspect docker-environment_default >/dev/null
if ! docker exec "$container" php -r 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);'; then
  echo "The shared PHP runtime needs pdo_pgsql. See README.md before continuing." >&2
  exit 1
fi
python3 - <<'PY'
from pathlib import Path
import secrets
Path('.local').mkdir(mode=0o700, exist_ok=True)
p = Path('.local/database.env')
if not p.exists():
    p.write_text(f'POSTGRES_DB=hotdesk\nPOSTGRES_USER=hotdesk\nPOSTGRES_PASSWORD={secrets.token_hex(24)}\n')
    p.chmod(0o600)
values = dict(line.split('=', 1) for line in p.read_text().splitlines() if '=' in line)
password = values['POSTGRES_PASSWORD']
for name, extra in [('.env.local', 'MAILER_DSN=smtp://mailer:1025\nMESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0\n'), ('.env.test.local', '')]:
    target = Path(name)
    if not target.exists():
        target.write_text(f'APP_SECRET={secrets.token_hex(32)}\nDATABASE_URL="postgresql://hotdesk:{password}@hot-desk-postgres:5432/hotdesk?serverVersion=18&charset=utf8"\n' + extra)
        target.chmod(0o600)
PY
docker compose -p hot-desk -f compose.dev.yaml up -d --wait
docker exec -e XDEBUG_MODE=off -e COMPOSER_CACHE_DIR=/tmp/hot-desk-composer-cache -u "$(id -u):$(id -g)" -w "${PHP_PROJECT_DIR:-/srv/sites/$(basename "$PWD")}" "$container" composer install --no-interaction
bash scripts/php.sh bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=test bash scripts/php.sh bin/console doctrine:database:create --if-not-exists
APP_ENV=test bash scripts/php.sh bin/console doctrine:migrations:migrate --no-interaction
bash scripts/php.sh bin/console app:setup
npm --prefix frontend ci
npm --prefix frontend run build
echo 'Open http://hot-desk.localhost. Local account credentials: .local/accounts.json.'
