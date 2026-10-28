#!/usr/bin/env bash
# Genera api/.env.local (DATABASE_URL, APP_SECRET y JWT_PASSPHRASE) a partir de db/.env. No se sube a Git.
set -euo pipefail
cd "$(dirname "$0")"
set -a; . ./.env; set +a
ENC=$(python3 -c 'import os, urllib.parse; print(urllib.parse.quote(os.environ["MARIADB_PASSWORD"], safe=""))')
ENC=${ENC//%/%%}  # doctrine.yaml usa %env(resolve:DATABASE_URL)%: un % literal debe ir doble
VER=$(sudo docker compose exec -T mysql sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" -N -e "SELECT VERSION()"')
VER="${VER%%-MariaDB*}-MariaDB"
conservar() { grep -s "^$1=" ../api/.env.local | cut -d= -f2- || true; }
SECRET=$(conservar APP_SECRET); [ -n "$SECRET" ] || SECRET=$(openssl rand -hex 16)
FRASE=$(conservar JWT_PASSPHRASE); [ -n "$FRASE" ] || FRASE=$(openssl rand -hex 32)
umask 077
cat > ../api/.env.local <<ENV
# Generado por db/env-symfony.sh a partir de db/.env. NO subir a Git.
APP_ENV=prod
APP_SECRET=$SECRET
DATABASE_URL="mysql://${MARIADB_USER}:${ENC}@mysql:3306/${MARIADB_DATABASE}?serverVersion=${VER}&charset=utf8mb4"
JWT_PASSPHRASE=$FRASE
ENV
echo "api/.env.local generado (MariaDB $VER)"
