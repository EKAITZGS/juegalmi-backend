# Jokoa by JuegAlmi — Backend (Reto 2)

API REST en Symfony 7.4 bajo `/ws`, con MariaDB 11.4, JWT y HTTPS. La consumen la app Android nativa y la app Ionic/Angular.

En producción: `https://jokoa.duckdns.org/ws` (AWS Academy, Ubuntu 26.04).

## Arquitectura (AWS)

```
Internet ──► [jokoa-bastion · IP pública · nginx + HTTPS] ──► [jokoa-symfony · 10.0.2.10 · privada]  /ws
                         │                                └──► [jokoa-ionic   · 10.0.2.11 · privada]  /
                         └── SSH de administración (solo desde la IP del administrador) y NAT de la subred privada
```

- **Bastión:** única máquina con IP pública. Termina el HTTPS (Let's Encrypt), hace de proxy inverso, de salto SSH y de NAT para que las privadas lleguen a Internet sin IP pública.
- **Symfony (privada):** nginx (solo HTTP, red privada), PHP-FPM, MariaDB y Adminer (solo en 127.0.0.1) y copias de seguridad.
- **Ionic (privada):** servirá la web de Ionic compilada.
- Grupos de seguridad: las privadas solo aceptan SSH y HTTP desde el bastión.
- **Sin IP elástica** (consume crédito): la IP del bastión cambia al reiniciar el lab y DuckDNS la actualiza solo cada 5 minutos.

## Estructura

| Carpeta | Contenido |
|---|---|
| `terraform/` | VPC, subredes, grupos de seguridad y las tres máquinas |
| `bastion/` | nginx del bastión (proxy HTTP y HTTPS) y scripts de certificado |
| `api/` | Symfony: entidades, migraciones, fixtures y `WsController` |
| `db/` | MariaDB + Adminer, scripts de contraseñas, copias y restauración |
| `docker/` | Dockerfile de PHP-FPM, nginx de la máquina privada |
| `duckdns/` | Actualización automática de la IP del dominio |
| `postman/` | Colección de Postman (39 peticiones) y comprobador |
| `docker-compose.yml` | PHP-FPM + nginx de la máquina privada |

## Secretos (nunca en Git)

`db/.env`, `api/.env.local`, `api/config/jwt/*.pem`, `~/.duckdns-token`, `~/.aws/credentials` y `terraform/*.tfstate` se crean en cada máquina o PC y están en `.gitignore`. Las credenciales del Learner Lab caducan al apagarlo.

## Despliegue desde cero

**1. Infraestructura** (en el PC, con las credenciales del lab en `~/.aws/credentials`):

```bash
cd terraform
terraform init
terraform apply -var "mi_ip=$(curl -s https://checkip.amazonaws.com)"
```

Añade a `~/.ssh/config` los alias `jokoa-bastion` (IP pública del bastión), `jokoa-symfony` (10.0.2.10) y `jokoa-ionic` (10.0.2.11), estos con `ProxyJump jokoa-bastion`, `User ubuntu` y `IdentityFile ~/.ssh/vockey.pem`.

**2. Symfony** (`ssh jokoa-symfony`; Docker se instala con el repositorio oficial como en clase):

```bash
# copia el proyecto a ~/juegalmi (sin secretos) y, dentro:
cd ~/juegalmi/db && umask 077
printf "MARIADB_ROOT_PASSWORD=%s\nMARIADB_DATABASE=juegalmi\nMARIADB_USER=juegalmi\nMARIADB_PASSWORD=%s\n" "$(openssl rand -hex 16)" "$(openssl rand -hex 16)" > .env
sudo docker compose up -d
ssh -t jokoa-symfony "~/juegalmi/db/cambiar-claves.sh"      # tus contraseñas reales (desde el PC)
cd ~/juegalmi
sudo docker compose up -d --build
sudo docker compose exec php composer install
sudo docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists && chmod 600 api/config/jwt/private.pem
sudo docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
sudo docker compose exec -e APP_ENV=dev php php bin/console doctrine:fixtures:load --append --no-interaction
```

**3. Bastión** (`ssh jokoa-bastion`, con `bastion/` y `duckdns/` copiados a `~/juegalmi`):

```bash
~/juegalmi/duckdns/instalar.sh      # pide el token de DuckDNS y programa la actualización de la IP
~/juegalmi/bastion/preparar.sh      # certbot y nginx en HTTP
~/juegalmi/bastion/activar-https.sh # certificado de Let's Encrypt (renovación automática) y HTTPS
```

Con `APP_ENV=prod`, cualquier cambio de código requiere `php bin/console cache:clear`.

## Copias de seguridad

`db/copia.sh` se lanza cada hora por cron en `jokoa-symfony` (rotación de 7 días). `db/restaurar.sh <copia>` restaura. `db/descargar-copias.ps1` baja las copias al PC (copia fuera de AWS).

## Cuentas de demostración

`admin@juegalmi.com`, `ane@juegalmi.com` y `jon@juegalmi.com`, todas con la contraseña `demo1234` (solo datos de ejemplo).

## Probar la API

Importa `postman/JuegAlmi-API.postman_collection.json` y lanza el Runner, o ejecuta `python postman/probar.py`. Resultado esperado: 39/39.

## Cuidado con el crédito

Las tres máquinas gastan unos 1 $/día encendidas. Al terminar: **End Lab**. Para borrar todo: `terraform destroy`.
