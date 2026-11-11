#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export APP_ENV=test
bash scripts/php.sh vendor/bin/phpunit "$@"
