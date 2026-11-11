#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
bash scripts/php.sh vendor/bin/php-cs-fixer fix
npm --prefix frontend run format
frontend/node_modules/.bin/prettier --write ".vscode/*.json" "config/**/*.yaml" docs/openapi.yaml compose.dev.yaml
