#!/usr/bin/env bash
# En el bastión: pide el certificado de Let's Encrypt (reto por el puerto 80, sin correo) y activa HTTPS (fase 2).
# El dominio ya debe apuntar a la IP del bastión (duckdns/instalar.sh). Uso: bastion/activar-https.sh
set -euo pipefail
cd "$(dirname "$0")"
sudo certbot certonly --webroot -w /var/www/certbot -d jokoa.duckdns.org \
  --non-interactive --agree-tos --register-unsafely-without-email
# certbot renueva solo (certbot.timer); tras cada renovación nginx recarga el certificado nuevo
sudo install -m 755 /dev/stdin /etc/letsencrypt/renewal-hooks/deploy/nginx-reload.sh <<'HOOK'
#!/bin/sh
systemctl reload nginx
HOOK
sudo install -m 644 nginx-https.conf /etc/nginx/sites-available/jokoa
sudo nginx -t
sudo systemctl reload nginx
systemctl is-active certbot.timer
echo "HTTPS activo en jokoa.duckdns.org"
