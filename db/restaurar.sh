#!/usr/bin/env bash
# Restaura una copia hecha con db/copia.sh. SUSTITUYE los datos actuales (antes guarda una copia de cómo estaban).
# Uso: db/restaurar.sh ~/juegalmi/copias/juegalmi-AAAAMMDD-HHMMSS-mmm.sql.gz
set -euo pipefail
FICHERO=$(realpath "${1:?Indica la copia a restaurar. Las disponibles: ls -t ~/juegalmi/copias}")
cd "$(dirname "$0")"
gzip -t "$FICHERO"
echo "Se va a SUSTITUIR el contenido de la base de datos por: $FICHERO"
read -r -p "Escribe SI para continuar: " respuesta
[ "$respuesta" = "SI" ] || { echo "Cancelado: no se ha tocado nada."; exit 1; }
# Se lee la copia ANTES de hacer la copia de seguridad previa: así nada (ni la rotación de 7 días) puede tocarla
umask 077
TEMPORAL=$(mktemp)
trap 'rm -f "$TEMPORAL"' EXIT
zcat "$FICHERO" > "$TEMPORAL"
echo "Antes, copia del estado actual por si acaso:"
./copia.sh
sudo docker compose exec -T mysql sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < "$TEMPORAL"
echo "Restaurada: $FICHERO"
