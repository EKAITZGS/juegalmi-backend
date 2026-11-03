#!/usr/bin/env bash
# Cambia la contraseña de una cuenta de la tienda (por defecto, el administrador) sin que se vea ni quede en el historial.
# Uso desde el PC:  ssh -t jokoa-symfony "~/juegalmi/db/cambiar-admin.sh [correo]"
set -euo pipefail
cd "$(dirname "$0")/.."
CORREO=${1:-admin@juegalmi.com}
case "$CORREO" in *[\'\"\\\ ]*) echo "Correo no válido."; exit 1;; esac

read -rsp "Nueva contraseña para $CORREO (mínimo 12 caracteres): " NUEVA; echo
read -rsp "Repítela: " REPITE; echo
[ "$NUEVA" = "$REPITE" ] || { echo "No coinciden."; exit 1; }
[ ${#NUEVA} -ge 12 ] || { echo "Muy corta: usa 12 o más caracteres."; exit 1; }

# La clave viaja por variable de entorno (no por argumentos, que se ven con ps)
HASH=$(sudo docker compose exec -T -e NUEVA="$NUEVA" php php -r 'echo password_hash(getenv("NUEVA"), PASSWORD_BCRYPT);' </dev/null)
unset NUEVA REPITE
sudo docker compose exec -T php php bin/console dbal:run-sql "UPDATE usuario SET password='$HASH' WHERE email='$CORREO'" </dev/null
echo "Hecho. Prueba a entrar con la clave nueva (las sesiones abiertas siguen valiendo hasta que caduquen)."
