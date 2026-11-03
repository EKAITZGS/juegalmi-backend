#!/usr/bin/env bash
# Guarda la cuenta de correo con la que Jokoa envía los mensajes de verificación (Gmail, Brevo u otro servidor SMTP),
# manda un correo de prueba y, si llega bien, activa la verificación. La clave se escribe sin que se vea y queda en
# api/.env.prod.local (permisos 600, fuera de Git).
# Uso desde el PC:  ssh -t jokoa-symfony "~/juegalmi/db/configurar-correo.sh"
set -euo pipefail
cd "$(dirname "$0")/.."
ARCHIVO=api/.env.prod.local

echo "Para Gmail: servidor smtp.gmail.com y contraseña de aplicación de 16 letras."
echo "Para Brevo: servidor smtp-relay.brevo.com, usuario SMTP y clave SMTP que da Brevo."
read -rp "Servidor SMTP [smtp.gmail.com]: " SERVIDOR
SERVIDOR=${SERVIDOR:-smtp.gmail.com}
read -rp "Correo remitente (el que verán los usuarios en 'De'): " CORREO
read -rp "Usuario SMTP [el mismo correo]: " USER_SMTP
USER_SMTP=${USER_SMTP:-$CORREO}
read -rsp "Clave SMTP o contraseña de aplicación (no se ve al escribir): " CLAVE; echo
CLAVE=${CLAVE// /}   # Google la muestra con espacios; sin ellos es la misma
{ [ -n "$CORREO" ] && [ -n "$CLAVE" ] && [ -n "$USER_SMTP" ]; } || { echo "Faltan datos."; exit 1; }
case "$CORREO$USER_SMTP$CLAVE$SERVIDOR" in *[\'\"\\\ ]*) echo "Sin comillas, barras ni espacios."; exit 1;; esac
USUARIO=$(python3 -c 'import sys, urllib.parse; print(urllib.parse.quote(sys.argv[1], safe=""))' "$USER_SMTP")

escribir() {  # $1 = 0/1: verificación desactivada/activada
  umask 077
  local IA; IA=$(grep '^ASISTENTE_' "$ARCHIVO" 2>/dev/null || true)   # la configuración del asesor de IA se conserva
  cat > "$ARCHIVO" <<ENV
# Correo para la verificación de cuentas. NO subir a Git.
MAILER_DSN="smtp://${USUARIO}:${CLAVE}@${SERVIDOR}:587"
MAIL_REMITENTE=$CORREO
VERIFICAR_EMAIL=$1
APP_URL=https://jokoa.duckdns.org
ENV
  [ -z "$IA" ] || echo "$IA" >> "$ARCHIVO"
  sudo docker compose exec -T php php bin/console cache:clear >/dev/null </dev/null
}

echo "== Enviando un correo de prueba a $CORREO"
escribir 0
if ! sudo docker compose exec -T php php bin/console app:correo-prueba "$CORREO" </dev/null; then
  sed -i '/^ASISTENTE_/!d' "$ARCHIVO"; [ -s "$ARCHIVO" ] || rm -f "$ARCHIVO"
  sudo docker compose exec -T php php bin/console cache:clear >/dev/null </dev/null
  echo "No se pudo enviar. Revisa el servidor, el usuario y la clave."
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
