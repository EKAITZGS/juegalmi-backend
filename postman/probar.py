# Ejecuta la colección de Postman como el Runner: cada petición en orden y comprueba su código HTTP.
# Uso: python postman/probar.py [base_url]   (por defecto, la de la colección)
import json
import pathlib
import re
import ssl
import sys
import time
import urllib.error
import urllib.request

c = json.loads(pathlib.Path(__file__).with_name("JuegAlmi-API.postman_collection.json").read_text(encoding="utf-8"))
# Confía en el certificado autofirmado de la VM (como hacer en Postman Settings → Certificates → CA certificates)
cert = pathlib.Path(__file__).with_name("juegalmi-vm.crt")
var = {v["key"]: v["value"] for v in c["variable"]}
if len(sys.argv) > 1:
    var["base_url"] = sys.argv[1]
# El certificado autofirmado solo se usa con la VM; en AWS el de Let's Encrypt ya lo reconoce el sistema
contexto = ssl.create_default_context(cafile=str(cert) if cert.exists() and "192.168.56" in var["base_url"] else None)


def sub(s):
    return re.sub(r"\{\{(\$?\w+)\}\}", lambda m: str(int(time.time())) if m.group(1) == "$timestamp" else str(var[m.group(1)]), s)


total = fallos = 0
for carpeta in c["item"]:
    for it in carpeta["item"]:
        r = it["request"]
        script = " ".join(it["event"][0]["script"]["exec"])
        esperado = int(re.search(r"have\.status\((\d+)\)", script).group(1))
        cabeceras = {x["key"]: sub(x["value"]) for x in r["header"]}
        if r["auth"]["type"] == "bearer":
            cabeceras["Authorization"] = "Bearer " + sub(r["auth"]["bearer"][0]["value"])
        cuerpo = sub(r["body"]["raw"]).encode() if "body" in r else None
        peticion = urllib.request.Request(sub(r["url"]), data=cuerpo, method=r["method"], headers=cabeceras)
        try:
            respuesta = urllib.request.urlopen(peticion, context=contexto)
            codigo, texto = respuesta.status, respuesta.read()
        except urllib.error.HTTPError as e:
            codigo, texto = e.code, e.read()
        guardar = re.search(r"set\('(\w+)', pm\.response\.json\(\)\.(\w+)\)", script)
        if guardar and codigo == 200:
            var[guardar.group(1)] = json.loads(texto)[guardar.group(2)]
        ok = codigo == esperado
        total += 1
        fallos += not ok
        print(f"{'OK ' if ok else 'MAL'} {codigo} {carpeta['name']} › {it['name']}")

print(f"\n{total - fallos}/{total} peticiones con el código esperado")
sys.exit(1 if fallos else 0)
