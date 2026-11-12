# Jokoa — Referencia de la API REST

Base: `https://jokoa.duckdns.org/ws` · JSON · autenticación `Authorization: Bearer <JWT>` (válido 8 h).
Roles: **público**, **usuario** (autenticado) y **admin** (`ROLE_ADMIN`).

Respuestas de escritura: `{"resultado":"ok","id":N}`. Errores: `{"resultado":"error","mensaje":"…"}` (400 datos, 403 permisos, 404 no existe, 409 conflicto).

Una colección de Postman con 69 peticiones está en `postman/JuegAlmi-API.postman_collection.json`.

## Autenticación y cuenta

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| POST | `/login` | público | Cuerpo `{email, password}` → `{token}`. Las cuentas con `activo=0` no entran |
| POST | `/registro` | público | Alta de cliente (`email, password, nombre, apellidos, telefono?`). Limitado a 8/hora. Con `VERIFICAR_EMAIL=1` manda correo de verificación |
| POST | `/verificar` | público | Confirma el correo con el token recibido |
| POST | `/verificar/reenviar` | público | Reenvía el correo (10/hora) |
| GET | `/perfil` | usuario | Datos del usuario del token |
| PUT | `/perfil/update` | usuario | Edita nombre, apellidos, teléfono y contraseña |

## Catálogo

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| GET | `/plataformas` | usuario | Plataformas de videojuegos |
| GET | `/departamentos` | usuario | Departamentos de soporte |
| GET | `/tiendas` | usuario | Tiendas (dirección, teléfono, horario, latitud/longitud) |
| GET | `/videojuegos`, `/videojuegos/{id}` | usuario | Listado y ficha |
| POST | `/videojuegos/add` | admin | Alta |
| PUT | `/videojuegos/update/{id}` | admin | Edición |
| DELETE | `/videojuegos/delete/{id}` | admin | Baja |
| GET | `/moviles`, `/moviles/{id}` | usuario | Listado y ficha (incluye planes de garantía) |
| POST / PUT / DELETE | `/moviles/add`, `/moviles/update/{id}`, `/moviles/delete/{id}` | admin | Alta, edición, baja |
| GET | `/articulos`, `/articulos/{id}` | usuario | Ordenadores, tablets, consolas y periféricos; admite filtro por categoría (query) |
| POST / PUT / DELETE | `/articulos/add`, `/articulos/update/{id}`, `/articulos/delete/{id}` | admin | Alta, edición, baja |

Categorías de artículo: `portatil, sobremesa, pc_gaming, tablet, consola, mando, auriculares, teclado, raton, monitor, silla`. Admiten garantía ampliada los móviles y las categorías definidas en `Articulo::CON_GARANTIA` (tablets y ordenadores).

## Compras

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| GET | `/compras` | usuario / admin | Propias (admin: todas) |
| POST | `/compras/add` | usuario | Cuerpo abajo |
| GET | `/compras/{id}/factura` | propietario / admin | Factura en PDF |

```json
{
  "tienda_id": 1,
  "lineas": [
    { "producto_id": 12, "cantidad": 1, "garantia_meses": 24 }
  ]
}
```

- `tienda_id` y `garantia_meses` (`12` o `24`) son opcionales; el precio de la garantía lo calcula el servidor (8 % / 14 %).
- Descuenta stock; guarda el precio del momento. Errores: 400 sin líneas / tienda inexistente / garantía no disponible; 404 producto; 409 sin stock.

## Alquileres

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| GET | `/alquileres` | usuario / admin | Propios (admin: todos) |
| POST | `/alquileres/add` | usuario | `{videojuego_id, dias}`: resta 1 copia y fija inicio hoy y fin `hoy + dias` |
| PUT | `/alquileres/devolver/{id}` | propietario / admin | Repone stock y marca la devolución (409 si ya devuelto) |

## Tickets de soporte y reparación

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| GET | `/tickets` | usuario / admin | Propios (admin: todos) |
| POST | `/tickets/add` | usuario | `{departamento_id, asunto, descripcion, dispositivo, tipo}` con `tipo` = `soporte` \| `reparacion` |
| PUT | `/tickets/presupuesto/{id}` | propietario | `{aceptado: true\|false}`: acepta (→ `en_reparacion`) o rechaza (→ `cerrado`) un ticket `presupuestado` |
| PUT | `/tickets/update/{id}` | admin | Cambia `estado` (válido según el tipo) y `presupuesto` |
| DELETE | `/tickets/delete/{id}` | admin | Elimina |
| GET | `/tickets/no-leidos` | usuario | Mensajes sin leer del chat |
| GET | `/tickets/{id}/mensajes` | propietario / admin | Mensajes del ticket (marca como leídos) |
| POST | `/tickets/{id}/mensajes` | propietario / admin | `{texto}` |

## Favoritos

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| GET | `/favoritos` | usuario | Ids de producto favoritos |
| PUT | `/favoritos/{id}` | usuario | Añade el producto `id` |
| DELETE | `/favoritos/{id}` | usuario | Lo quita |

## Asesor de compra (IA)

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| POST | `/asistente` | usuario | Historial de turnos `{role, content}` (el último, del cliente) → `{respuesta, productos[]}`. Las tarjetas de producto las construye el servidor con ids reales del catálogo. Limitado por tasa; inactivo si no hay `ASISTENTE_API_KEY` |

## Administración (`/admin`, solo `ROLE_ADMIN`)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/admin/resumen` | Métricas del panel |
| GET | `/admin/usuarios` | Listado de usuarios |
| POST | `/admin/usuarios/add` | Alta de usuario |
| PUT | `/admin/usuarios/update/{id}` | Rol, contraseña, activar/desactivar |
| POST | `/admin/imagenes` | Subida de fotos de producto (máx. 3 MB por petición, servidas en `/uploads`) |
| POST | `/admin/tiendas/add` | Alta de tienda |
| PUT | `/admin/tiendas/update/{id}` | Edición de tienda |
| DELETE | `/admin/tiendas/delete/{id}` | Baja de tienda |

> Los cuerpos exactos de cada petición están en la colección de Postman y en el código (`api/src/Controller/`). Esta tabla se ha elaborado leyendo los controladores; conviene revisar los campos de los cuerpos que no aparecen detallados antes de usarla como contrato.
