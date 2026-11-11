#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
container="${PHP_CONTAINER:-docker-environment-frankenphp-1}"
if [[ "${PHP_EXECUTION:-docker}" == local ]] || ! command -v docker >/dev/null || ! docker inspect "$container" >/dev/null 2>&1; then
  exec php "$@"
fi
exec docker exec -e APP_ENV="${APP_ENV:-dev}" -e XDEBUG_MODE=off -u "$(id -u):$(id -g)" -w "${PHP_PROJECT_DIR:-/srv/sites/$(basename "$PWD")}" "$container" php "$@"
