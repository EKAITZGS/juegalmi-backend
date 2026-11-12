# Jokoa — Documentación del proyecto (Reto 2)

Jokoa es una tienda de **videojuegos (venta y alquiler)** y de **móviles, ordenadores, tablets y material gaming**, con tiendas físicas en Bizkaia. El proyecto se compone de **un backend y tres clientes** que comparten la misma API y la misma base de datos.

| Repositorio | Qué es | Tecnología |
|---|---|---|
| [`juegalmi-backend`](https://github.com/EKAITZGS/juegalmi-backend) | API REST, base de datos, infraestructura AWS | Symfony 7.4, PHP-FPM, MariaDB 11.4, JWT, Docker, Terraform, nginx |
| [`jokoa-web`](https://github.com/EKAITZGS/jokoa-web) | Tienda web + panel de administración | React 19, TypeScript, Vite, React Router 7, Leaflet |
| [`jokoa-android`](https://github.com/EKAITZGS/jokoa-android) | App Android nativa | Java, Volley, Glide, osmdroid, Material |
| [`jokoa-ionic`](https://github.com/EKAITZGS/jokoa-ionic) | App híbrida (APK) | Ionic 9, Angular 22, Capacitor 8, Leaflet |

Producción: `https://jokoa.duckdns.org` (web en `/`, API en `/ws`). El detalle de endpoints está en [`API.md`](API.md).

---

## 1. Visión general

```
 Android (Java)   Ionic/Capacitor (APK)   Web React (tienda + /admin)
        \                  |                    /
         └────────── HTTPS · JSON · Bearer JWT ─┘
                           │
        Internet ─► [jokoa-bastion · IP pública · nginx + Let's Encrypt]
                           │  proxy inverso + salto SSH + NAT
                           ▼
              [jokoa-symfony · 10.0.2.10 · subred privada]
               nginx :80 ─► /ws → PHP-FPM (Symfony)
                          ─► /img, /uploads → fotos
                          ─► /  → web React compilada
               MariaDB 11.4 + Adminer (solo 127.0.0.1)
```

- Los tres clientes usan **la misma API `/ws`**, el mismo diseño y la misma paleta.
- La web se sirve desde el mismo dominio que la API (no hay CORS en producción); el APK Ionic y la app Android usan URL absoluta.
- Autenticación **JWT** (8 h de validez). Al caducar, todos los clientes vuelven al login.

### Funcionalidades

- **Catálogo:** videojuegos, móviles y artículos (portátiles, sobremesa, PC gaming, tablets, consolas, mandos, auriculares, teclados, ratones, monitores, sillas), con ficha técnica, buscador, orden y filtro de stock.
- **Compra:** garantía ampliada por unidad (móviles, tablets y ordenadores: +12 meses 8 %, +24 meses 14 % del precio, calculado siempre por el servidor), tienda de recogida y **factura en PDF**.
- **Alquiler** de videojuegos por días, con devolución (repone stock).
- **Cuenta:** registro con verificación por correo, perfil, favoritos y comparador de 2–3 productos.
- **Soporte:** tickets de soporte y de reparación (el cliente acepta o rechaza el presupuesto) y chat cliente–administrador con contador de no leídos.
- **Asesor de compra con IA** (`POST /ws/asistente`): consulta el catálogo real con una herramienta y devuelve tarjetas de producto.
- **Administración (solo web):** resumen, usuarios, tiendas, fotos, altas/bajas de productos y tickets.
- **Modo oscuro** en web, Android e Ionic.

> Nota: el README de Android/Ionic cita una "web Angular"; la web real es **React** (`jokoa-web`).

---

## 2. Backend — `juegalmi-backend`

### 2.1 Estructura

| Carpeta | Contenido |
|---|---|
| `api/` | Proyecto Symfony: `src/Controller`, `Entity`, `Repository`, `Service`, `Command`, `DataFixtures`, `Migrations`, `Security` |
| `terraform/` | VPC, subredes pública/privada, grupos de seguridad y las máquinas (bastión `t3.micro`, Symfony `t3.small`) |
| `bastion/` | nginx del bastión (HTTP y HTTPS) y scripts de certificado (`preparar.sh`, `activar-https.sh`) |
| `docker/` | Dockerfile/`php.ini` de PHP-FPM y `nginx/default.conf` de la máquina privada |
| `db/` | MariaDB + Adminer (`docker-compose.yml`) y scripts operativos (ver 2.6) |
| `duckdns/` | Actualización automática de la IP del dominio (cada 5 min) |
| `postman/` | Colección (69 peticiones, 12 carpetas), `probar.py` (ejecutor) y `generar.py` |
| `docs/` | Diagrama E-R (`er.py`, `er_chen.py`, `.drawio`, PNG) y créditos de imágenes |
| `docker-compose.yml` | PHP-FPM + nginx de la máquina privada (usa la red externa `db_default`) |

### 2.2 Controladores y servicios

| Archivo | Responsabilidad |
|---|---|
| `Controller/WsController.php` | Registro, perfil, catálogos, compras, alquileres, tickets, favoritos, asistente |
| `Controller/AdminController.php` | `/ws/admin/*`: resumen, usuarios, subida de imágenes, tiendas |
| `Controller/TicketChatController.php` | Mensajes de ticket y contador de no leídos |
| `Controller/VerificacionController.php` | Verificación de correo y reenvío |
| `Service/Garantias.php` | Planes de garantía (12 m = 8 %, 24 m = 14 %) |
| `Service/Factura.php` | Generación de la factura PDF |
| `Service/VerificacionCorreo.php` | Token y envío del correo de verificación |
| `Service/Asistente.php` | Asesor IA: API tipo *chat completions* con herramienta `buscar_productos` (máx. 5 vueltas) |
| `Security/UserChecker.php` | Bloquea el login de cuentas con `activo = 0` |
| `EventListener/UbicacionListener.php` | Registra ubicación/acción del usuario |
| `Command/*` | Carga de catálogo ampliado y para el asistente, imágenes del manual, tarjetas, correo de prueba |

### 2.3 Seguridad

- Firewall `login` (`/ws/login`, `json_login` con `email` y `password`) → devuelve el JWT. Firewall `api` (`/ws/*`) stateless con JWT.
- Públicas: `/ws/login`, `/ws/registro`, `/ws/verificar`, `/ws/verificar/reenviar`. Todo lo demás exige autenticación.
- Roles: `ROLE_USER` y `ROLE_ADMIN`. Las operaciones de alta/edición/baja de catálogo, tickets y todo `/ws/admin` requieren admin.
- El comprador/propietario se toma **siempre del token**, nunca del JSON; el precio y la garantía los fija el servidor.
- *Rate limiting*: 8 registros/hora y 10 reenvíos de verificación/hora por conexión; el asistente también tiene limitador.
- `api/.env` (en Git) no lleva secretos. Claves reales en `api/.env.local` / `api/.env.prod.local`, `api/config/jwt/*.pem`, `db/.env`, `~/.duckdns-token`, `*.tfstate` (todo en `.gitignore`).
- `db/rotar-jwt.sh` regenera frase y claves JWT (cierra todas las sesiones).

### 2.4 Variables de entorno

| Variable | Uso |
|---|---|
| `DATABASE_URL` | Conexión a MariaDB (la de `.env` es un ejemplo; se define por máquina) |
| `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE` | Claves JWT (frase solo en `.env.local`) |
| `CORS_ALLOW_ORIGIN` | Orígenes permitidos (por defecto solo localhost) |
| `MAILER_DSN`, `MAIL_REMITENTE`, `APP_URL` | Envío de correo y enlaces de verificación |
| `VERIFICAR_EMAIL` | `1` exige verificar el correo; `0` (por defecto) crea cuentas ya verificadas |
| `ASISTENTE_API_KEY`, `ASISTENTE_URL`, `ASISTENTE_MODELO` | Asesor IA (por defecto Groq, `openai/gpt-oss-120b`); sin clave el asistente queda inactivo |

### 2.5 Modelo de datos

```
Usuario ─┬─< Compra ─< LineaCompra >─ Producto ─┬─ Videojuego >─ Plataforma
         ├─< Alquiler >─ Videojuego             ├─ Movil
         ├─< Ticket >─ Departamento             ├─ Articulo
         │     └─< MensajeTicket                └─< Imagen
         ├─< LecturaTicket (último mensaje leído)
         ├─< Favorito >─ Producto
         └─< Ubicacion
Compra >─ Tienda (recogida)
```

- **Producto** es la base común (nombre, descripción, precio, stock, fecha de alta, `caracteristicas` JSON, imágenes). **Videojuego**, **Movil** y **Articulo** son extensiones 1-1.
- **Videojuego:** plataforma, género, PEGI, precio de alquiler/día. **Movil:** marca, almacenamiento, RAM. **Articulo:** categoría, marca, `montaje` (los PC Jokoa se montan en tienda).
- **LineaCompra** guarda el precio unitario del momento y la garantía (meses y precio).
- **Ticket:** `tipo` (`soporte` / `reparacion`), `estado` según el tipo, `presupuesto` y `presupuestoAceptado`. Flujo de reparación: `presupuestado` → el cliente acepta (`en_reparacion`) o rechaza (`cerrado`); `listo`/`en_reparacion` solo con presupuesto aceptado.
- **Usuario:** `activo`, `verificado`, token y caducidad de verificación.
- Esquema gestionado con **Doctrine Migrations** (`api/src/Migrations`, desde `Version20261027…`). Diagrama E-R en `docs/modelo-er.png` y `docs/er-chen.png`.

### 2.6 Scripts operativos (`db/`)

| Script | Función |
|---|---|
| `cambiar-claves.sh` | Cambia las contraseñas de MariaDB por las reales |
| `cambiar-admin.sh [correo]` | Cambia una contraseña de usuario sin mostrarla |
| `configurar-correo.sh` | Configura SMTP, manda prueba y activa `VERIFICAR_EMAIL=1` |
| `configurar-asistente.sh` | Guarda la clave del asesor IA |
| `copia.sh` / `restaurar.sh <copia>` | Backup horario por cron (rotación 7 días) y restauración |
| `descargar-copias.ps1` | Baja las copias al PC (copia fuera de AWS) |
| `rotar-jwt.sh` | Rota claves JWT |
| `reiniciar-demo.sh`, `env-symfony.sh` | Datos de demo y variables de la máquina Symfony |

### 2.7 Despliegue desde cero

Resumen (pasos completos y comandos en el `README.md` del backend):

1. **Infraestructura** (`terraform/`): `terraform init && terraform apply -var "mi_ip=$(curl -s https://checkip.amazonaws.com)"`. Añadir alias SSH `jokoa-bastion` y `jokoa-symfony` (`ProxyJump jokoa-bastion`).
2. **Symfony** (en `jokoa-symfony`): levantar `db/` (MariaDB), `docker compose up -d --build`, `composer install`, generar claves JWT, `doctrine:migrations:migrate` y, en entorno nuevo, `doctrine:fixtures:load --append`.
3. **Bastión**: `duckdns/instalar.sh`, `bastion/preparar.sh`, `bastion/activar-https.sh` (Let's Encrypt con renovación automática).
4. **Web**: desde `jokoa-web`, `./desplegar.sh` compila y publica en `~/juegalmi/web`.

Con `APP_ENV=prod`, cada cambio de código requiere `php bin/console cache:clear`.

Puntos a tener en cuenta:
- **Sin IP elástica** (ahorra crédito): la IP del bastión cambia al reiniciar el lab; DuckDNS la actualiza cada 5 min.
- Las credenciales del AWS Learner Lab caducan al apagarlo.
- Las tres máquinas cuestan ≈ 1 $/día: **End Lab** al terminar; `terraform destroy` para borrar todo.

### 2.8 Pruebas de la API

`python postman/probar.py` (o el Runner de Postman con `postman/JuegAlmi-API.postman_collection.json`). Resultado esperado **69/69**. Variables: `JOKOA_PASSWORD`, `JOKOA_ADMIN_PASSWORD` (o `password` / `password_admin` en Postman). Cada ejecución crea y borra datos de prueba y registra un cliente `cliente<n>@juegalmi.com` (desactivar con `UPDATE usuario SET activo=0 WHERE email LIKE 'cliente%@juegalmi.com'`). Conviene ejecutarlo sobre datos de demostración.

### 2.9 Cuentas

- **Producción:** `ekaitz@jokoa.com` (admin), `almi@gmail.com`, `sergio@gmail.com` (clientes). Contraseñas fuera del repositorio.
- **Fixtures (entorno nuevo):** `admin@juegalmi.com`, `ane@juegalmi.com`, `jon@juegalmi.com`, contraseña `demo1234`.

---

## 3. Web — `jokoa-web`

Tienda y panel de administración en una sola SPA.

**Stack:** React 19, TypeScript 7, Vite 8, React Router 7, Leaflet, fuente Roboto.

### Estructura (`src/`)

| Ruta | Contenido |
|---|---|
| `main.tsx`, `App.tsx` | Arranque y enrutado (`SoloConSesion`, `SoloAdmin`, página 404) |
| `api.ts` | Cliente HTTP `/ws`: token en `localStorage` (`jokoa_token`), comprobación de caducidad, `ApiError`, callback al caducar |
| `auth.tsx`, `cart.tsx`, `favoritos.tsx`, `comparar.tsx`, `notificaciones.tsx`, `toast.tsx`, `tema.ts` | Contextos/estado: sesión, carrito, favoritos, comparador, avisos, tema claro/oscuro |
| `pages/` | Inicio, Catalogo (juegos/móviles), Articulos (ordenadores/tablets/gaming), Buscar, Ficha, Carrito, Pedidos, Perfil, Tiendas, Soporte, Favoritos, Comparar, Login, Registro, Verificar |
| `admin/` | `AdminLayout`, Resumen, Productos, Pedidos, Tickets, Tiendas, Usuarios |
| `components/` | Layout, ProductCard, Modal, ChatTicket, AsistenteChat, BotonFavorito, BotonComparar, etc. |
| `styles/` | `tokens.css` (diseño), `base`, `tienda`, `admin`, `animaciones` |

### Rutas

Públicas: `/`, `/login`, `/registro`, `/verificar`. Con sesión: `/juegos`, `/moviles`, `/ordenadores`, `/tablets`, `/gaming`, `/buscar`, `/favoritos`, `/comparar`, `/producto/:tipo/:id`, `/carrito`, `/pedidos`, `/perfil`, `/tiendas`, `/soporte`. Solo administrador: `/admin/*`.

### Desarrollo y publicación

```bash
npm install
npm run dev      # Vite en 0.0.0.0; proxy de /ws, /img y /uploads hacia https://jokoa.duckdns.org
npm run build    # tsc --noEmit + vite build → dist/
./desplegar.sh   # (en la máquina Symfony) compila y copia dist/ a ~/juegalmi/web con rsync
```

`desplegar.sh` asume el repo en `~/juegalmi-web` y Node en `~/herramientas/node/bin`. nginx sirve `/assets/` con caché de 30 días inmutable y el resto con *fallback* a `index.html`.

---

## 4. App Android — `jokoa-android`

App nativa en **Java** (`com.jokoa.app`), minSdk 24, targetSdk/compileSdk 35, Java 11, versión 1.0.

**Dependencias:** AppCompat, Material 1.12, Volley 1.2.1 (red), Glide 4.16 (imágenes), RecyclerView, ViewPager2, osmdroid 6.1.20 (mapa OSM), Play Services Maps.

### Estructura (`app/src/main/java/com/jokoa/app/`)

| Paquete | Contenido |
|---|---|
| raíz | Activities: `SplashActivity`, `LoginActivity`, `RegistroActivity`, `MainActivity` (navegación inferior), `DetalleActivity`, `SoporteActivity`, `ComparadorActivity` |
| `fragmentos/` | Inicio, Productos, Galeria, Fotos, Asesor (chat IA), Mapa, Tiendas, Pedidos, Perfil, `FotoDialogFrag` |
| `adaptadores/` | Producto, Fila, Asesor, Foto, GaleriaPager |
| `datos/` | Modelos y estado local: `Producto`, `Favoritos`, `Comparar`, `AsesorModelo`, `FotoAlmi`, `Turno` |
| `red/` | `Api` (cola Volley única, `BASE = https://jokoa.duckdns.org/ws`, cabecera `Authorization: Bearer`) y `Sesion` (token en SharedPreferences) |
| `InterfacesDam/` | `IControlFotos` |

Si la API responde 401, `Api` limpia la sesión y vuelve a `LoginActivity`. Permisos: `INTERNET` y `ACCESS_NETWORK_STATE`. Tema claro/oscuro con `values-night`.

### Compilar

Requisitos: Android Studio, JDK 17. Crear `local.properties` con `sdk.dir=…` (no se sube a Git).

```bash
./gradlew assembleDebug     # APK en app/build/outputs/apk/debug/
```

Para apuntar a otro servidor, cambiar `Api.BASE`.

---

## 5. App Ionic — `jokoa-ionic`

**Stack:** Ionic 9, Angular 22 (NgModule), Capacitor 8 (`appId com.jokoa.app.ionic`, `webDir www`), Leaflet, RxJS, Vitest. Plugins Capacitor: app, haptics, keyboard, status-bar.

### Estructura (`src/app/`)

| Carpeta | Contenido |
|---|---|
| `core/` | `api.service` (HTTP), `sesion.service`, `auth.guard`, `auth.interceptor` (añade el Bearer y gestiona 401), `aviso.service`, `favoritos.service`, `comparar.service`, `asesor.service`, `modelos.ts` |
| `pages/` | splash, login, registro, tabs (tienda, asesor, tiendas, pedidos, perfil), detalle/`:tipo/:id`, soporte |
| `theme/` | `variables.scss` (tokens de diseño) y `jokoa.scss` (componentes) |
| `environments/` | `environment.ts` (dev, `/ws`), `environment.prod.ts` (web, `/ws`), `environment.app.ts` (APK, URL absoluta) |

Rutas: `splash` → `login`/`registro` → `tabs/{tienda,asesor,tiendas,pedidos,perfil}` (con `authGuard`), `detalle/:tipo/:id`, `soporte`.

`CapacitorHttp` está activado: las peticiones salen por el sistema y no por el WebView, así se evitan problemas de CORS. `proxy.conf.js` redirige `/ws` a la API en desarrollo, con caché DNS de 5 min por un router doméstico lento.

### Ejecutar y generar el APK

```bash
npm install
npx ng serve                          # http://localhost:4200
npx ng build --configuration app      # build para el APK (apiUrl absoluta)
npx cap add android && npx cap sync android
# después, abrir android/ en Android Studio y generar el APK
```

Alcance actual respecto a Android nativo: la app Ionic cubre Juegos y Móviles; las secciones de Ordenadores/Tablets/Gaming las tiene la web y la app nativa.

---

## 6. Convenciones de la API (resumen)

- Base: `https://jokoa.duckdns.org/ws`; JSON; `Authorization: Bearer <token>`.
- Errores: `{ "resultado": "error", "mensaje": "…" }` con 400 (datos), 403 (permisos/ajeno), 404 (no existe), 409 (conflicto: stock, ya devuelto, estado).
- Éxito en escritura: `{ "resultado": "ok", "id": … }`.
- Convención de rutas: `GET /x`, `GET /x/{id}`, `POST /x/add`, `PUT /x/update/{id}`, `DELETE /x/delete/{id}`.

Todos los endpoints, con método, permisos y cuerpo, en [`API.md`](API.md).
