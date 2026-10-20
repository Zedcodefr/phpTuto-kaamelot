#!/usr/bin/env bash
# Dans le devcontainer, « localhost » est le conteneur lui-même, pas votre machine.
# On relaie donc les ports publiés par la stack (compose.yaml) pour que les commandes
# des cours (curl http://localhost:8081/..., psql -p 5433...) fonctionnent telles quelles.
set -euo pipefail

PORTS=(5173 5175 8081 8082 8090 5433)

for port in "${PORTS[@]}"; do
  pkill -f "socat TCP-LISTEN:${port}," 2>/dev/null || true
  setsid nohup socat "TCP-LISTEN:${port},fork,reuseaddr,bind=127.0.0.1" "TCP:host.docker.internal:${port}" \
    >/dev/null 2>&1 < /dev/null &
done
