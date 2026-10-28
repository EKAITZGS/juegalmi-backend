# Genera JuegAlmi-API.postman_collection.json (colección de Postman de la API /ws con JWT)
import json
import pathlib


def req(nombre, metodo, ruta, cuerpo=None, codigo=200, guardar=None, token="token", cabeceras=()):
    test = [f"pm.test('Responde {codigo}', () => pm.response.to.have.status({codigo}));"]
    if guardar:
        var, campo = guardar
        test.append(f"pm.collectionVariables.set('{var}', pm.response.json().{campo});")
    r = {"method": metodo, "url": "{{base_url}}" + ruta, "header": [{"key": k, "value": v} for k, v in cabeceras]}
    if token is None:
        r["auth"] = {"type": "noauth"}
    else:
        r["auth"] = {"type": "bearer", "bearer": [{"key": "token", "value": "{{" + token + "}}", "type": "string"}]}
    if cuerpo is not None:
        r["header"].insert(0, {"key": "Content-Type", "value": "application/json"})
        r["body"] = {"mode": "raw", "raw": json.dumps(cuerpo, ensure_ascii=False, indent=2), "options": {"raw": {"language": "json"}}}
    return {"name": nombre, "request": r, "event": [{"listen": "test", "script": {"type": "text/javascript", "exec": test}}]}


ADM = "token_admin"
juego = {"nombre": "Tetris Effect", "descripcion": "Puzles con música.", "precio": 24.99, "stock": 3, "plataforma_id": 1,
         "genero": "Puzle", "pegi": 3, "precio_alquiler_dia": 1.5, "imagen": "https://placehold.co/600x400/png?text=Tetris+Effect"}
movil = {"nombre": "Moto G85", "descripcion": "Gama media con pantalla curva.", "precio": 299, "stock": 6, "marca": "Motorola",
         "almacenamiento_gb": 256, "ram_gb": 8, "imagen": "https://placehold.co/600x400/png?text=Moto+G85"}
ticket = {"departamento_id": 1, "asunto": "No carga la batería", "descripcion": "Se queda en el 1 %.", "dispositivo": "iPhone 15"}
ubicacion = (("X-Latitud", "43.270900"), ("X-Longitud", "-2.946800"))

coleccion = {
    "info": {
        "name": "JuegAlmi API (/ws) con JWT",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        "description": "API REST del Reto 2 JuegAlmi. Ejecuta primero la carpeta '0. Autenticación': los logins guardan el token de Ane "
                       "(cliente) y el del admin en variables, y el resto de peticiones los usan como Bearer. Cambia base_url para la VM (https://192.168.56.10/ws) "
                       "o para AWS (por defecto, jokoa.duckdns.org con certificado de Let's Encrypt). Cada petición comprueba su código HTTP: se pueden lanzar todas en orden con el Runner. "
                       "Contraseña de las cuentas de demo: demo1234. En la VM el certificado es autofirmado: añade "
                       "juegalmi-vm.crt en Postman → Settings → Certificates → CA certificates.",
    },
    "variable": [{"key": k, "value": v} for k, v in [("base_url", "https://jokoa.duckdns.org/ws"), ("token", ""), ("token_admin", ""),
                                                      ("videojuego_id", ""), ("movil_id", ""), ("alquiler_id", ""), ("ticket_id", "")]],
    "item": [
        {"name": "0. Autenticación", "item": [
            req("Sin token (401)", "GET", "/videojuegos", codigo=401, token=None),
            req("Login con contraseña mala (401)", "POST", "/login", {"email": "ane@juegalmi.com", "password": "mala"}, codigo=401, token=None),
            req("Login de Ane (cliente)", "POST", "/login", {"email": "ane@juegalmi.com", "password": "demo1234"}, guardar=("token", "token"), token=None),
            req("Login del admin", "POST", "/login", {"email": "admin@juegalmi.com", "password": "demo1234"}, guardar=("token_admin", "token"), token=None),
            req("Registro de un cliente nuevo", "POST", "/registro", {"email": "cliente{{$timestamp}}@juegalmi.com", "password": "clave1234",
                                                                     "nombre": "Cliente", "apellidos": "Nuevo", "telefono": "600999888"}, token=None),
        ]},
        {"name": "1. Catálogo", "item": [
            req("Plataformas", "GET", "/plataformas"),
            req("Departamentos", "GET", "/departamentos"),
            req("Videojuegos (con ubicación)", "GET", "/videojuegos", cabeceras=ubicacion),
            req("Un videojuego (Gran Turismo 7)", "GET", "/videojuegos/4"),
            req("Videojuego que no existe (404)", "GET", "/videojuegos/999", codigo=404),
            req("Móviles", "GET", "/moviles"),
            req("Un móvil (Galaxy S25)", "GET", "/moviles/9"),
        ]},
        {"name": "2. Videojuegos (CRUD, admin)", "item": [
            req("Un cliente intenta dar de alta (403)", "POST", "/videojuegos/add", juego, codigo=403),
            req("Alta", "POST", "/videojuegos/add", juego, guardar=("videojuego_id", "id"), token=ADM),
            req("Modificar (todos los campos)", "PUT", "/videojuegos/update/{{videojuego_id}}", {**juego, "precio": 19.99, "stock": 5}, token=ADM),
            req("Consultar el nuevo", "GET", "/videojuegos/{{videojuego_id}}"),
            req("Baja", "DELETE", "/videojuegos/delete/{{videojuego_id}}", token=ADM),
            req("Consultar tras borrar (404)", "GET", "/videojuegos/{{videojuego_id}}", codigo=404),
        ]},
        {"name": "3. Móviles (CRUD, admin)", "item": [
            req("Alta", "POST", "/moviles/add", movil, guardar=("movil_id", "id"), token=ADM),
            req("Modificar (todos los campos)", "PUT", "/moviles/update/{{movil_id}}", {**movil, "precio": 279, "ram_gb": 12}, token=ADM),
            req("Baja", "DELETE", "/moviles/delete/{{movil_id}}", token=ADM),
            req("Borrar un móvil ya vendido (409)", "DELETE", "/moviles/delete/11", codigo=409, token=ADM),
        ]},
        {"name": "4. Compras", "item": [
            req("Comprar (Ane, con ubicación)", "POST", "/compras/add",
                {"lineas": [{"producto_id": 12, "cantidad": 1}, {"producto_id": 7, "cantidad": 1}]}, cabeceras=ubicacion),
            req("Comprar sin stock suficiente (409)", "POST", "/compras/add", {"lineas": [{"producto_id": 9, "cantidad": 99}]}, codigo=409),
            req("Comprar sin productos (400)", "POST", "/compras/add", {"lineas": []}, codigo=400),
            req("Mis compras (Ane: solo las suyas)", "GET", "/compras"),
            req("Todas las compras (admin)", "GET", "/compras", token=ADM),
        ]},
        {"name": "5. Alquileres", "item": [
            req("Alquilar (Ane, con ubicación)", "POST", "/alquileres/add", {"videojuego_id": 4, "dias": 3},
                guardar=("alquiler_id", "id"), cabeceras=ubicacion),
            req("Mis alquileres", "GET", "/alquileres"),
            req("Devolver", "PUT", "/alquileres/devolver/{{alquiler_id}}"),
            req("Devolver otra vez (409)", "PUT", "/alquileres/devolver/{{alquiler_id}}", codigo=409),
            req("Devolver un alquiler de Jon (403)", "PUT", "/alquileres/devolver/2", codigo=403),
            req("Alquilar un móvil (404)", "POST", "/alquileres/add", {"videojuego_id": 9, "dias": 3}, codigo=404),
        ]},
        {"name": "6. Tickets de soporte", "item": [
            req("Abrir ticket (Ane)", "POST", "/tickets/add", ticket, guardar=("ticket_id", "id")),
            req("Mis tickets", "GET", "/tickets"),
            req("Un cliente intenta cerrarlo (403)", "PUT", "/tickets/update/{{ticket_id}}", {**ticket, "estado": "cerrado"}, codigo=403),
            req("Cerrar ticket (admin, todos los campos)", "PUT", "/tickets/update/{{ticket_id}}", {**ticket, "estado": "cerrado"}, token=ADM),
            req("Todos los tickets (admin)", "GET", "/tickets", token=ADM),
            req("Borrar ticket (admin)", "DELETE", "/tickets/delete/{{ticket_id}}", token=ADM),
        ]},
    ],
}

destino = pathlib.Path(__file__).with_name("JuegAlmi-API.postman_collection.json")
destino.write_text(json.dumps(coleccion, ensure_ascii=False, indent=2), encoding="utf-8")
print(f"Colección escrita: {sum(len(c['item']) for c in coleccion['item'])} peticiones en {len(coleccion['item'])} carpetas")
