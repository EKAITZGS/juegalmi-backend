#!/usr/bin/env bash
# Vacía todas las tablas (ids desde 1) y vuelve a cargar los datos de ejemplo. BORRA todo lo que haya: solo para la demo.
set -euo pipefail
cd "$(dirname "$0")"
sql() { sudo docker compose exec -T mysql sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" '"$*"; }
TABLAS=$(sql -N -e "'SHOW TABLES'" | grep -v '^doctrine_migration_versions$')
# Las claves externas se desactivan solo en esta sesión y solo mientras se vacía
{ echo 'SET FOREIGN_KEY_CHECKS = 0;'; for t in $TABLAS; do echo "TRUNCATE TABLE \`$t\`;"; done; echo 'SET FOREIGN_KEY_CHECKS = 1;'; } | sql
cd .. && sudo docker compose exec -T -e APP_ENV=dev php php bin/console doctrine:fixtures:load --append --no-interaction
echo "Datos de la demo reiniciados."
