#!/bin/bash
# user_data del bastión: lo ejecuta cloud-init una vez, al crear la máquina.
# Convierte el bastión en NAT para la subred privada e instala nginx (proxy inverso hacia las privadas).
set -eux

echo 'net.ipv4.ip_forward=1' > /etc/sysctl.d/99-nat.conf
sysctl --system

DEBIAN_FRONTEND=noninteractive apt-get update -y
DEBIAN_FRONTEND=noninteractive apt-get install -y nftables nginx

IFACE=$(ip -o -4 route show to default | awk '{print $5}' | head -1)
cat > /etc/nftables.conf <<EOF
#!/usr/sbin/nft -f
flush ruleset
table ip nat {
  chain postrouting {
    type nat hook postrouting priority 100; policy accept;
    ip saddr 10.0.2.0/24 oifname "$IFACE" masquerade
  }
}
EOF
systemctl enable --now nftables

timedatectl set-timezone Europe/Madrid
