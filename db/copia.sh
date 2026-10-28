#!/usr/bin/env bash
# Copia de seguridad de la base de datos juegalmi en ~/juegalmi/copias (comprimida, con fecha y hora).
# Borra las copias de más de 7 días. Uso: db/copia.sh (lo lanza cron cada hora)
set -euo pipefail
set -o noclobber   # nunca sobrescribir una copia que ya existe
cd "$(dirname "$0")"
DESTINO=../copias
mkdir -p "$DESTINO"
umask 077   # las copias llevan datos personales y hashes de contraseñas: solo las lee reto
# Con milisegundos: dos copias en el mismo segundo no pueden tener el mismo nombre
FICHERO="$DESTINO/juegalmi-$(date +%Y%m%d-%H%M%S-%3N).sql.gz"

# --single-transaction: copia coherente sin bloquear las tablas mientras la API sigue funcionando
sudo docker compose exec -T mysql sh -c 'mariadb-dump --single-transaction --triggers -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' | gzip > "$FICHERO"

# Una copia que no se puede leer no sirve: se comprueba que el gzip está bien y que el volcado terminó
gzip -t "$FICHERO"
zcat "$FICHERO" | tail -1 | grep -q 'Dump completed' || { echo "ERROR: volcado incompleto: $FICHERO"; exit 1; }

find "$DESTINO" -name 'juegalmi-*.sql.gz' -mtime +7 -delete
echo "$(date '+%F %T') copia correcta: $FICHERO ($(du -h "$FICHERO" | cut -f1))"
