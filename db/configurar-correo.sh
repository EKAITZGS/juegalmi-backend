#!/usr/bin/env bash
# Guarda la cuenta de Gmail con la que Jokoa envía los correos de verificación, manda un correo de prueba
# y, si llega bien, activa la verificación. La contraseña se escribe sin que se vea y queda en
# api/.env.prod.local (permisos 600, fuera de Git).
# Uso desde el PC:  ssh -t jokoa-symfony "~/juegalmi/db/configurar-correo.sh"
set -euo pipefail
cd "$(dirname "$0")/.."
ARCHIVO=api/.env.prod.local

read -rp "Correo de Gmail que enviará los mensajes: " CORREO
read -rsp "Contraseña de aplicación de Google (16 letras, no se ve al escribir): " CLAVE; echo
CLAVE=${CLAVE// /}   # Google la muestra con espacios; sin ellos es la misma
{ [ -n "$CORREO" ] && [ -n "$CLAVE" ]; } || { echo "Faltan datos."; exit 1; }
case "$CORREO$CLAVE" in *[\'\"\\\ ]*) echo "Sin comillas, barras ni espacios."; exit 1;; esac
USUARIO=$(python3 -c 'import sys, urllib.parse; print(urllib.parse.quote(sys.argv[1], safe=""))' "$CORREO")
unset CLAVE_VISIBLE 2>/dev/null || true

escribir() {  # $1 = 0/1: verificación desactivada/activada
  umask 077
  cat > "$ARCHIVO" <<ENV
# Correo para la verificación de cuentas. NO subir a Git.
MAILER_DSN="smtp://${USUARIO}:${CLAVE}@smtp.gmail.com:587"
MAIL_REMITENTE=$CORREO
VERIFICAR_EMAIL=$1
APP_URL=https://jokoa.duckdns.org
ENV
  sudo docker compose exec -T php php bin/console cache:clear >/dev/null </dev/null
}

echo "== Enviando un correo de prueba a $CORREO"
escribir 0
if ! sudo docker compose exec -T php php bin/console app:correo-prueba "$CORREO" </dev/null; then
  rm -f "$ARCHIVO"
  sudo docker compose exec -T php php bin/console cache:clear >/dev/null </dev/null
  echo "No se pudo enviar. Revisa que el correo y la contraseña de aplicación sean correctos."
  exit 1
fi
echo "== Mira la bandeja de $CORREO (y el spam): debe haber llegado el mensaje de prueba."
read -rp "¿Ha llegado? Escribe 's' para activar la verificación: " OK
if [ "$OK" = "s" ]; then
  escribir 1
  echo "Verificación por correo ACTIVADA."
else
  echo "No se activa. La cuenta queda guardada; vuelve a ejecutar el script cuando llegue el correo."
fi
