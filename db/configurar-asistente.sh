#!/usr/bin/env bash
# Configura la IA del asesor de compra: proveedor, modelo y clave. La clave se escribe sin que se vea y queda en
# api/.env.prod.local (permisos 600, fuera de Git). Cambia solo las líneas ASISTENTE_*: no toca la configuración de correo.
# Uso desde el PC:  ssh -t jokoa-symfony "~/juegalmi/db/configurar-asistente.sh"
set -euo pipefail
cd "$(dirname "$0")/.."
ARCHIVO=api/.env.prod.local

echo "Proveedores (formato compatible con OpenAI): 1) Groq gratis  2) DeepSeek de pago  3) Otro"
read -rp "Elige [1]: " OPCION
case "${OPCION:-1}" in
  1) URL=https://api.groq.com/openai/v1; MODELO=openai/gpt-oss-120b;;
  2) URL=https://api.deepseek.com; MODELO=deepseek-chat;;
  *) read -rp "URL base (termina antes de /chat/completions): " URL; read -rp "Modelo: " MODELO;;
esac
read -rsp "Clave de API (no se ve al escribir): " CLAVE; echo
{ [ -n "$CLAVE" ] && [ -n "$URL" ] && [ -n "$MODELO" ]; } || { echo "Faltan datos."; exit 1; }
case "$CLAVE$URL$MODELO" in *' '*|*\'*|*\"*|*\\*) echo "Sin comillas, barras invertidas ni espacios."; exit 1;; esac

umask 077
touch "$ARCHIVO"
grep -vE '^(ASISTENTE_|ANTHROPIC_)' "$ARCHIVO" > "$ARCHIVO.tmp" || true
printf 'ASISTENTE_API_KEY=%s\nASISTENTE_URL=%s\nASISTENTE_MODELO=%s\n' "$CLAVE" "$URL" "$MODELO" >> "$ARCHIVO.tmp"
mv "$ARCHIVO.tmp" "$ARCHIVO"
chmod 600 "$ARCHIVO"
sudo docker compose exec -T php php bin/console cache:clear >/dev/null </dev/null
echo "Guardado ($MODELO). El asesor ya puede responder."
