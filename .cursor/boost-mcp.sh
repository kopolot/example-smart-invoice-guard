#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

if ! docker compose ps --status running --services 2>/dev/null | grep -qx php; then
    echo "Laravel Boost MCP requires the Docker php service. Start it with: docker compose up -d" >&2
    exit 1
fi

exec docker compose exec -T php php artisan boost:mcp
