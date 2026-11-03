#!/usr/bin/env bash
# Rota las claves del JWT: genera una frase de paso nueva (solo en api/.env.prod.local, permisos 600, fuera de Git)
# y un par de claves nuevo. Todas las sesiones abiertas caducan: hay que volver a entrar.
# Uso desde el PC:  ssh -t jokoa-symfony "~/juegalmi/db/rotar-jwt.sh"
set -euo pipefail
cd "$(dirname "$0")/.."
ARCHIVO=api/.env.prod.local
umask 077
touch "$ARCHIVO"
cp "$ARCHIVO" "$ARCHIVO.bak"
cp -a api/config/jwt "$ARCHIVO.jwt.bak"
FRASE=$(openssl rand -hex 24)
sed -i '/^JWT_PASSPHRASE=/d' "$ARCHIVO"
echo "JWT_PASSPHRASE=$FRASE" >> "$ARCHIVO"
unset FRASE
sudo docker compose exec -T php php bin/console cache:clear >/dev/null </dev/null
if sudo docker compose exec -T php php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction </dev/null; then
  chmod 600 api/config/jwt/private.pem
  sudo docker compose exec -T php php bin/console cache:clear >/dev/null </dev/null
  rm -rf "$ARCHIVO.bak" "$ARCHIVO.jwt.bak"
  echo "Claves rotadas. Entra otra vez en la web y en las apps."
else
  mv "$ARCHIVO.bak" "$ARCHIVO"   # si falla se deja como estaba
  rm -rf api/config/jwt && mv "$ARCHIVO.jwt.bak" api/config/jwt
  sudo docker compose exec -T php php bin/console cache:clear >/dev/null </dev/null
  echo "No se pudo generar el par de claves: no se ha cambiado nada."
  exit 1
fi
