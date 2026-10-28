#!/usr/bin/env bash
# En el bastión: instala certbot y deja nginx como proxy en HTTP (fase 1). Uso: bastion/preparar.sh
set -euo pipefail
cd "$(dirname "$0")"
sudo DEBIAN_FRONTEND=noninteractive apt-get -y -q install certbot >/dev/null
sudo mkdir -p /var/www/certbot
sudo install -m 644 nginx-fase1.conf /etc/nginx/sites-available/jokoa
sudo ln -sf /etc/nginx/sites-available/jokoa /etc/nginx/sites-enabled/jokoa
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
echo "nginx del bastión en fase 1 (HTTP)"
