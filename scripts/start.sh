#!/usr/bin/env bash
set -euo pipefail

PORT=${PORT:-8080}
echo "Starting PHP dev server on http://localhost:$PORT"
php -S 0.0.0.0:$PORT

