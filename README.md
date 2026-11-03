# Jokoa by JuegAlmi — Backend (Reto 2)

API REST en Symfony 7.4 bajo `/ws`, con MariaDB 11.4, JWT y HTTPS. La consumen la app Android nativa, la app Ionic (APK) y la web React.

## Qué hace la tienda

Venta y alquiler de videojuegos y venta de móviles, ordenadores, tablets y gaming, en tiendas de Bizkaia.

- **Catálogo:** videojuegos, móviles y artículos (portátiles, sobremesa, PC gaming, tablets, consolas y periféricos), con ficha técnica, buscador, orden y filtro de stock.
- **Compra:** garantía ampliada por unidad, elección de tienda de recogida y factura en PDF de cada compra. Alquiler de videojuegos por días.
- **Cuenta:** registro con verificación por correo, perfil, favoritos y comparador de 2 o 3 productos.
- **Soporte:** tickets de soporte y de reparación (con presupuesto que el cliente acepta o rechaza) y chat entre cliente y administrador con contador de mensajes sin leer.
- **Asesor de compra** con IA (`POST /ws/asistente`).
- **Administración (web):** resumen, usuarios, tiendas, fotos, altas y bajas de videojuegos, móviles y artículos, y tickets.
- **Modo oscuro** en web (sigue al sistema, con botón en el menú), Android e Ionic.

Rutas nuevas desde la primera entrega: `/articulos`, `/favoritos`, `/compras/{id}/factura`, `compra.tienda_id` en `/compras/add`, `/tickets/{id}/mensajes`, `/tickets/no-leidos`, `/tickets/presupuesto/{id}`, `/verificar` y `/asistente`. Todas están en la colección de Postman.

En producción: `https://jokoa.duckdns.org/ws` (AWS Academy, Ubuntu 26.04).

## Arquitectura (AWS)

```
Internet ──► [jokoa-bastion · IP pública · nginx + HTTPS] ──► [jokoa-symfony · 10.0.2.10 · privada]  /ws y /
                         └── SSH de administración (solo desde la IP del administrador) y NAT de la subred privada
```

- **Bastión:** única máquina con IP pública. Termina el HTTPS (Let's Encrypt), hace de proxy inverso, de salto SSH y de NAT para que las privadas lleguen a Internet sin IP pública.
- **Symfony (privada):** API y web Angular compilada; nginx (solo HTTP, red privada), PHP-FPM, MariaDB y Adminer (solo en 127.0.0.1) y copias de seguridad.
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
| `postman/` | Colección de Postman (69 peticiones, 12 carpetas) y comprobador |
| `docs/` | Diagrama E-R (`er.py` genera `modelo-er.html`; el PNG se saca con Chrome) y créditos de imágenes |
| `bitacora/` | Registro de todos los pasos del montaje (`web/log.jsonl`) |
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

Añade a `~/.ssh/config` los alias `jokoa-bastion` (IP pública del bastión) y `jokoa-symfony` (10.0.2.10), este con `ProxyJump jokoa-bastion`, `User ubuntu` y `IdentityFile ~/.ssh/vockey.pem`.

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

**Correo de verificación (opcional):** `ssh -t jokoa-symfony "~/juegalmi/db/configurar-correo.sh"` pide servidor SMTP (Gmail con contraseña de aplicación de 16 letras, Brevo u otro), manda un correo de prueba y, si llega, activa `VERIFICAR_EMAIL=1`. La clave queda en `api/.env.prod.local` (permisos 600, fuera de Git). Sin esto, las cuentas nuevas entran sin verificar.

**Asesor con IA (opcional):** `db/configurar-asistente.sh` guarda su clave en el mismo fichero.

## Copias de seguridad

`db/copia.sh` se lanza cada hora por cron en `jokoa-symfony` (rotación de 7 días). `db/restaurar.sh <copia>` restaura. `db/descargar-copias.ps1` baja las copias al PC (copia fuera de AWS).

## Cuentas de demostración

**En producción** solo existen tres cuentas: `ekaitz@jokoa.com` (administrador), `almi@gmail.com` y `sergio@gmail.com` (clientes, con una compra y un alquiler cada uno). Las contraseñas no se guardan en el repositorio; cámbialas con `ssh -t jokoa-symfony "~/juegalmi/db/cambiar-admin.sh [correo]"`. Los datos de ejemplo de `AppFixtures` (`admin@juegalmi.com`, `ane@juegalmi.com`, `jon@juegalmi.com`, contraseña `demo1234`) solo se cargan en un entorno nuevo. La colección de Postman usa las cuentas de producción: `JOKOA_PASSWORD=... JOKOA_ADMIN_PASSWORD=... python postman/probar.py` (o las variables `password` y `password_admin` en Postman). Cada ejecución de Postman registra un cliente nuevo (`cliente<número>@juegalmi.com`): desactívalos con `UPDATE usuario SET activo=0 WHERE email LIKE 'cliente%@juegalmi.com'`.

## Seguridad

- `api/.env` (en Git) no lleva secretos: la frase del JWT, el correo y la clave del asesor viven en `api/.env.local` / `api/.env.prod.local` de cada máquina.
- `db/rotar-jwt.sh` genera una frase y un par de claves JWT nuevos (cierra todas las sesiones). `db/cambiar-admin.sh` cambia una contraseña sin mostrarla.
- Las cuentas desactivadas (`usuario.activo = 0`) no pueden iniciar sesión.

## Probar la API

Importa `postman/JuegAlmi-API.postman_collection.json` y lanza el Runner, o ejecuta `python postman/probar.py`. Resultado esperado: 69/69. Las pruebas crean y borran datos de ejemplo (compras, tickets, artículos) en la base a la que apuntan; las compras de prueba de Ane quedan, así que conviene ejecutarla sobre datos de demostración.

## Cuidado con el crédito

Las tres máquinas gastan unos 1 $/día encendidas. Al terminar: **End Lab**. Para borrar todo: `terraform destroy`.
