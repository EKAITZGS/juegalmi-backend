#!/usr/bin/env bash
# Guarda el token de DuckDNS (entrada oculta), comprueba que funciona y programa la actualización automática.
# Uso: ssh -t ... "~/juegalmi/duckdns/instalar.sh"
set -euo pipefail
cd "$(dirname "$0")"
read -rsp "Token de DuckDNS (no se ve al escribir): " T; echo
[ -n "$T" ] || { echo "Token vacío."; exit 1; }
umask 077
printf '%s\n' "$T" > "$HOME/.duckdns-token"
unset T
if ! ./actualizar.sh; then
  rm -f "$HOME/.duckdns-token"
  echo "DuckDNS no aceptó el token o el subdominio. Compruébalo en duckdns.org (el subdominio debe ser 'jokoa') y repite."
  exit 1
fi
( crontab -l 2>/dev/null | grep -v duckdns/actualizar.sh || true
  echo "@reboot sleep 20 && $PWD/actualizar.sh >> $PWD/duckdns.log 2>&1"
  echo "*/5 * * * * $PWD/actualizar.sh >> $PWD/duckdns.log 2>&1" ) | crontab -
echo "Programado: al arrancar y cada 5 minutos."
crontab -l | grep duckdns
