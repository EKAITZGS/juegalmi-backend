#!/usr/bin/env bash
# Cambia las contraseñas de MariaDB (root y juegalmi) sin que se vean en pantalla ni queden en el historial.
# Uso: ssh -t reto juegalmi/db/cambiar-claves.sh
set -euo pipefail
cd "$(dirname "$0")"

pedir() {
  local a b
  while :; do
    read -rsp "Nueva contraseña para $1: " a; echo
    read -rsp "Repítela: " b; echo
    if [ -z "$a" ]; then echo "No puede estar vacía."; continue; fi
    if [ "$a" != "$b" ]; then echo "No coinciden, otra vez."; continue; fi
    case "$a" in *\'*|*\*) echo "Sin comillas simples (') ni barras invertidas (\)."; continue;; esac
    clave=$a; return
  done
}

pedir "root de MariaDB";                    ROOT=$clave
pedir "juegalmi (el usuario de Symfony)";   APP=$clave

echo "== Cambiando contraseñas dentro de MariaDB"
sudo docker compose exec -T mysql sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD"' <<SQL
ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '$ROOT';
ALTER USER IF EXISTS 'root'@'%' IDENTIFIED BY '$ROOT';
ALTER USER IF EXISTS 'juegalmi'@'%' IDENTIFIED BY '$APP';
FLUSH PRIVILEGES;
SQL

echo "== Guardando en .env"
umask 077
cat > .env <<ENV
# Contraseñas de MariaDB. NO subir a Git.
MARIADB_ROOT_PASSWORD='$ROOT'
MARIADB_DATABASE=juegalmi
MARIADB_USER=juegalmi
MARIADB_PASSWORD='$APP'
ENV

echo "== Recreando el contenedor con el .env nuevo"
sudo docker compose up -d --quiet-pull 2>&1 | grep -E 'mysql|Recreat|Start' || true
for i in $(seq 1 30); do sudo docker compose exec -T mysql healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 && break; sleep 2; done

echo "== Comprobando que entran con las contraseñas nuevas"
sudo docker compose exec -T mysql sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -N -e "SELECT CONCAT(\"root OK: \", CURRENT_USER())" && mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -N -e "SELECT CONCAT(\"juegalmi OK: \", CURRENT_USER(), \" en \", DATABASE())"'
[ ! -d ../api ] || ./env-symfony.sh  # mantiene api/.env.local al día
echo "Hecho."
