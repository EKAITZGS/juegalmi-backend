#!/usr/bin/env bash
# Renueva el certificado de Let's Encrypt si le quedan menos de 30 días y recarga nginx sin cortar el servicio.
# Lo lanza cron cada día. Uso: docker/renovar-cert.sh
set -euo pipefail
cd "$(dirname "$0")/.."
sudo docker run --rm -v "$PWD/docker/certbot/conf:/etc/letsencrypt" -v "$PWD/docker/certbot/www:/var/www/certbot" \
  certbot/certbot renew --quiet </dev/null
sudo docker compose exec -T nginx nginx -s reload </dev/null
echo "$(date '+%F %T') renovación comprobada"
