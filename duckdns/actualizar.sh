#!/usr/bin/env bash
# Pone en DuckDNS la IP pública actual de esta máquina (la IP cambia cada vez que se arranca el lab).
# El token se lee de ~/.duckdns-token (permisos 600, fuera de Git) y no aparece en la lista de procesos.
set -euo pipefail
DOMINIO=jokoa
TOKEN=$(cat "$HOME/.duckdns-token")
RESP=$(printf 'url = "https://www.duckdns.org/update?domains=%s&token=%s&ip="\n' "$DOMINIO" "$TOKEN" | curl -s --max-time 20 -K -)
echo "$(date '+%F %T') $DOMINIO.duckdns.org -> $RESP"
[ "$RESP" = "OK" ]
