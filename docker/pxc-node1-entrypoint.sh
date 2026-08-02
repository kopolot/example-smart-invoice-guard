#!/bin/bash
set -euo pipefail

# Local/dev only: after an unclean full-cluster stop every node has
# safe_to_bootstrap: 0, so Galera refuses to form a new Primary Component.
# pxc-node1 is the dedicated bootstrap node (empty CLUSTER_JOIN / gcomm://).
GRASTATE=/var/lib/mysql/grastate.dat
if [[ -f "$GRASTATE" ]]; then
    sed -i 's/^safe_to_bootstrap:[[:space:]]*0$/safe_to_bootstrap: 1/' "$GRASTATE"
fi

exec /entrypoint.sh "$@"
