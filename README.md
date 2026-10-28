# Jokoa by JuegAlmi — Backend (Reto 2)

API REST en Symfony 7.4 bajo `/ws`, con MariaDB 11.4, JWT y HTTPS. La consumen la app Android nativa y la app Ionic/Angular.

En producción: `https://jokoa.duckdns.org/ws` (AWS Academy EC2, Ubuntu 26.04).

## Estructura

| Carpeta | Contenido |
|---|---|
| `api/` | Symfony: entidades, migraciones, fixtures y `WsController` |
| `db/` | MariaDB + Adminer (`docker-compose.yml`) y scripts de contraseñas y copias |
| `docker/` | Dockerfile de PHP-FPM, configuración de nginx y renovación del certificado |
| `duckdns/` | Actualización automática de la IP en DuckDNS |
| `postman/` | Colección de Postman (39 peticiones) y comprobador |
| `docker-compose.yml` | PHP-FPM + nginx (HTTP 80 y HTTPS 443) |

## Secretos (nunca en Git)

`db/.env`, `api/.env.local`, `api/config/jwt/*.pem`, `~/.duckdns-token`, `docker/certbot/conf/` y `copias/` se crean en cada servidor y están en `.gitignore`.

## Despliegue en un servidor nuevo (Ubuntu con Docker)

```bash
# 1. Código
git clone <repositorio> ~/juegalmi && cd ~/juegalmi

# 2. Base de datos: arranca con una contraseña temporal y después pones las tuyas
cd db
umask 077
printf "MARIADB_ROOT_PASSWORD=%s\nMARIADB_DATABASE=juegalmi\nMARIADB_USER=juegalmi\nMARIADB_PASSWORD=%s\n" "$(openssl rand -hex 16)" "$(openssl rand -hex 16)" > .env
sudo docker compose up -d
./cambiar-claves.sh          # pide las contraseñas reales (entrada oculta) y genera api/.env.local
cd ..

# 3. PHP, dependencias, claves JWT, tablas y datos de ejemplo
sudo docker compose up -d --build php
sudo docker compose exec php composer install
sudo docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
chmod 600 api/config/jwt/private.pem
sudo docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
sudo docker compose exec -e APP_ENV=dev php php bin/console doctrine:fixtures:load --append --no-interaction

# 4. Certificado (el dominio ya debe apuntar al servidor; ver duckdns/)
mkdir -p docker/certbot/conf docker/certbot/www
B=https://raw.githubusercontent.com/certbot/certbot/main/certbot/src/certbot
curl -fsSL $B/_internal/plugins/nginx/tls_configs/options-ssl-nginx.conf -o docker/certbot/conf/options-ssl-nginx.conf
curl -fsSL $B/ssl-dhparams.pem -o docker/certbot/conf/ssl-dhparams.pem
sudo docker run --rm -p 80:80 -v "$PWD/docker/certbot/conf:/etc/letsencrypt" certbot/certbot certonly --standalone \
  --agree-tos --register-unsafely-without-email --cert-name juegalmi -d TU_DOMINIO
sudo docker compose up -d nginx
```

Con `APP_ENV=prod`, cualquier cambio de código requiere `php bin/console cache:clear`.

## Copias de seguridad y renovación (cron)

```
0 * * * *   ~/juegalmi/db/copia.sh                 # copia horaria en copias/, rotación de 7 días
30 3 * * *  ~/juegalmi/docker/renovar-cert.sh      # renueva el certificado si quedan < 30 días
```

Restaurar: `db/restaurar.sh copias/juegalmi-AAAAMMDD-HHMMSS-mmm.sql.gz`

## Cuentas de demostración

`admin@juegalmi.com`, `ane@juegalmi.com` y `jon@juegalmi.com`, todas con la contraseña `demo1234` (solo datos de ejemplo).

## Probar la API

Importa `postman/JuegAlmi-API.postman_collection.json` y lanza el Runner, o ejecuta `python postman/probar.py`. Resultado esperado: 39/39.
