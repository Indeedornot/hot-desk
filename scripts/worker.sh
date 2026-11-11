#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
bash scripts/php.sh bin/console messenger:consume async --memory-limit=128M "$@"
