"""Modelo E-R de Jokoa en notación de Chen: salida .drawio (editable) y .svg (para el PNG)."""
import math
import pathlib
import sys
from xml.sax.saxutils import escape

SALIDA = pathlib.Path(sys.argv[1])  # carpeta de salida

# (nombre, centro, atributos); el primero con '*' es la clave primaria
E = {
    'Usuario': ((2000, 1500), ['*id', 'email', 'password', 'nombre', 'apellidos', 'telefono', 'roles', 'activo', 'verificado',
                               'token_verificacion', 'verificacion_caduca', 'verificacion_enviada', 'fecha_alta']),
    'Producto': ((2900, 650), ['*id', 'nombre', 'descripcion', 'precio', 'stock', 'caracteristicas', 'fecha_alta']),
    'Movil': ((2300, -150), ['*producto_id', 'marca', 'almacenamiento_gb', 'ram_gb']),
    'Articulo': ((3500, -150), ['*producto_id', 'categoria', 'marca', 'montaje']),
    'Videojuego': ((4100, 900), ['*producto_id', 'genero', 'pegi', 'precio_alquiler_dia']),
    'Plataforma': ((5100, 900), ['*id', 'nombre']),
    'Imagen': ((4000, 150), ['*id', 'url', 'orden']),
    'Compra': ((1100, 650), ['*id', 'fecha']),
    'LineaCompra': ((2000, 250), ['*id', 'cantidad', 'precio_unitario', 'garantia_meses', 'precio_garantia']),
    'Tienda': ((100, 650), ['*id', 'nombre', 'direccion', 'telefono', 'horario', 'latitud', 'longitud']),
    'Alquiler': ((3900, 1750), ['*id', 'fecha_inicio', 'fecha_fin', 'fecha_devolucion', 'precio_dia']),
    'Ticket': ((800, 2250), ['*id', 'asunto', 'descripcion', 'dispositivo', 'tipo', 'estado', 'presupuesto',
                             'presupuesto_aceptado', 'fecha_apertura', 'fecha_cierre']),
    'Departamento': ((-500, 2250), ['*id', 'nombre']),
    'MensajeTicket': ((1300, 3100), ['*id', 'texto', 'fecha']),
    'LecturaTicket': ((2400, 3100), ['*id', 'ultimo_leido']),
    'Ubicacion': ((3200, 2500), ['*id', 'latitud', 'longitud', 'fecha', 'accion']),
}

S = 0.72
for _n in E:
    E[_n] = ((E[_n][0][0] * S, E[_n][0][1] * S), E[_n][1])

# (etiqueta, entidad A, cardinalidad A, entidad B, cardinalidad B)
R = [
    ('realiza', 'Usuario', '1', 'Compra', 'N'),
    ('contiene', 'Compra', '1', 'LineaCompra', 'N'),
    ('se vende en', 'Producto', '1', 'LineaCompra', 'N'),
    ('se recoge en', 'Compra', 'N', 'Tienda', '0..1'),
    ('alquila', 'Usuario', '1', 'Alquiler', 'N'),
    ('es alquilado', 'Videojuego', '1', 'Alquiler', 'N'),
    ('es de', 'Videojuego', 'N', 'Plataforma', '1'),
    ('es un', 'Producto', '1', 'Movil', '0..1'),
    ('es un', 'Producto', '1', 'Articulo', '0..1'),
    ('es un', 'Producto', '1', 'Videojuego', '0..1'),
    ('tiene', 'Producto', '1', 'Imagen', 'N'),
    ('marca favorito', 'Usuario', 'N', 'Producto', 'M'),
    ('abre', 'Usuario', '1', 'Ticket', 'N'),
    ('pertenece a', 'Ticket', 'N', 'Departamento', '1'),
    ('tiene', 'Ticket', '1', 'MensajeTicket', 'N'),
    ('escribe', 'Usuario', '1', 'MensajeTicket', 'N'),
    ('registra lectura', 'Ticket', '1', 'LecturaTicket', 'N'),
    ('lee', 'Usuario', '1', 'LecturaTicket', 'N'),
    ('registra', 'Usuario', '1', 'Ubicacion', 'N'),
]

AW, AH = None, 34
def anch(t): return max(84, int(7.4 * len(t) + 26))
def ew(n): return max(130, int(10 * len(n) + 34))
EH = 52
DW, DH = 130, 70

pos = {n: v[0] for n, v in E.items()}
diam = []  # (etiqueta, centro, a, ca, b, cb)
for et, a, ca, b, cb in R:
    (ax, ay), (bx, by) = pos[a], pos[b]
    diam.append([et, ((ax + bx) / 2, (ay + by) / 2), a, ca, b, cb])
# ajustes de la posición de algunos rombos para que no se pisen
OVER = {('es un', 'Movil'): (2600, 250), ('es un', 'Articulo'): (3200, 250), ('es un', 'Videojuego'): (3500, 800),
        ('marca favorito', 'Producto'): (2500, 1100), ('realiza', 'Compra'): (1550, 1000),
        ('es alquilado', 'Alquiler'): (4000, 1300), ('alquila', 'Alquiler'): (2950, 1650),
        ('tiene', 'Imagen'): (3450, 400)}
OVER = {k: (v[0] * S, v[1] * S) for k, v in OVER.items()}
for d in diam:
    k = (d[0], d[4]) if (d[0], d[4]) in OVER else (d[0], d[2]) if (d[0], d[2]) in OVER else None
    if k: d[1] = OVER[k]

# conexiones de cada entidad (para dejar libres esos ángulos al colocar atributos)
conex = {n: [] for n in E}
for et, c, a, ca, b, cb in diam:
    conex[a].append(c); conex[b].append(c)

cajas = []  # rectángulos ocupados (x0,y0,x1,y1)
def choca(r, lista):
    return any(not (r[2] < o[0] or r[0] > o[2] or r[3] < o[1] or r[1] > o[3]) for o in lista)

for n, ((x, y), _) in E.items():
    cajas.append((x - ew(n) / 2 - 6, y - EH / 2 - 6, x + ew(n) / 2 + 6, y + EH / 2 + 6))
for et, c, a, ca, b, cb in diam:
    cajas.append((c[0] - DW / 2, c[1] - DH / 2, c[0] + DW / 2, c[1] + DH / 2))

attrs = []  # (entidad, texto, pk, centro)
for n, ((x, y), lst) in sorted(E.items(), key=lambda kv: -len(kv[1][1])):
    bloqueados = [math.degrees(math.atan2(c[1] - y, c[0] - x)) % 360 for c in conex[n]]
    libres = [t for t in range(0, 360, 4) if all(min(abs(t - b) % 360, 360 - abs(t - b) % 360) > 15 for b in bloqueados)]
    k = len(lst)
    objetivos = [libres[int((i + .5) * len(libres) / k)] for i in range(k)]
    for i, t in enumerate(lst):
        pk = t.startswith('*')
        t = t.lstrip('*')
        w = anch(t)
        puesto = None
        for radio in range(150 if k < 8 else 210, 900, 36):
            for delta in range(0, 180, 4):
                for s in (1, -1):
                    ang = objetivos[i] + s * delta
                    if ang % 360 not in [l % 360 for l in libres] and delta > 0 and False:
                        continue
                    if min((abs(ang % 360 - b) % 360 for b in bloqueados), default=999) <= 18 and min(360 - abs(ang % 360 - b) % 360 for b in bloqueados) <= 18:
                        continue
                    cx = x + radio * math.cos(math.radians(ang)) * 1.25
                    cy = y + radio * math.sin(math.radians(ang))
                    caja = (cx - w / 2 - 4, cy - AH / 2 - 4, cx + w / 2 + 4, cy + AH / 2 + 4)
                    if not choca(caja, cajas):
                        puesto = (cx, cy, caja)
                        break
                if puesto: break
            if puesto: break
        cx, cy, caja = puesto
        cajas.append(caja)
        attrs.append((n, t, pk, (cx, cy), w))

# ---- límites del lienzo
xs = [c[0] for c in cajas] + [c[2] for c in cajas]
ys = [c[1] for c in cajas] + [c[3] for c in cajas]
X0, Y0 = min(xs) - 60, min(ys) - 90
X1, Y1 = max(xs) + 60, max(ys) + 60
W, H = int(X1 - X0), int(Y1 - Y0)
def P(p): return (p[0] - X0, p[1] - Y0)

def salida_rect(centro, w, h, hacia):
    dx, dy = hacia[0] - centro[0], hacia[1] - centro[1]
    L = math.hypot(dx, dy) or 1
    s = min((w / 2) / abs(dx) if dx else 1e9, (h / 2) / abs(dy) if dy else 1e9)
    t = s * L + 22
    return (centro[0] + dx / L * t - dy / L * 14, centro[1] + dy / L * t + dx / L * 14)

# ---------- SVG
svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" font-family="Arial,Helvetica,sans-serif">',
       f'<rect width="{W}" height="{H}" fill="#fff"/>',
       f'<text x="40" y="52" font-size="34" font-weight="700" fill="#185fa5">Jokoa · Modelo Entidad-Relación (notación de Chen)</text>',
       f'<text x="40" y="80" font-size="15" fill="#5b6b82">Rectángulo = entidad · elipse = atributo (subrayado, clave primaria) · rombo = relación · 1 / N / M / 0..1 = cardinalidad</text>']
for n, t, pk, c, w in attrs:
    a, b = P(E[n][0]), P(c)
    svg.append(f'<line x1="{a[0]:.0f}" y1="{a[1]:.0f}" x2="{b[0]:.0f}" y2="{b[1]:.0f}" stroke="#222" stroke-width="1.3"/>')
for et, c, a, ca, b, cb in diam:
    for e in (a, b):
        p, q = P(E[e][0]), P(c)
        svg.append(f'<line x1="{p[0]:.0f}" y1="{p[1]:.0f}" x2="{q[0]:.0f}" y2="{q[1]:.0f}" stroke="#222" stroke-width="1.8"/>')
for n, (c, _) in E.items():
    x, y = P(c)
    w = ew(n)
    svg.append(f'<rect x="{x - w / 2:.0f}" y="{y - EH / 2:.0f}" width="{w}" height="{EH}" fill="#fff" stroke="#222" stroke-width="2.4"/>')
    svg.append(f'<text x="{x:.0f}" y="{y + 6:.0f}" text-anchor="middle" font-size="18" font-weight="700">{escape(n)}</text>')
for n, t, pk, c, w in attrs:
    x, y = P(c)
    svg.append(f'<ellipse cx="{x:.0f}" cy="{y:.0f}" rx="{w / 2:.0f}" ry="{AH / 2:.0f}" fill="#fff" stroke="#222" stroke-width="1.4"/>')
    dec = ' text-decoration="underline"' if pk else ''
    svg.append(f'<text x="{x:.0f}" y="{y + 4:.0f}" text-anchor="middle" font-size="13"{dec}>{escape(t)}</text>')
for et, c, a, ca, b, cb in diam:
    x, y = P(c)
    svg.append(f'<polygon points="{x:.0f},{y - DH / 2:.0f} {x + DW / 2:.0f},{y:.0f} {x:.0f},{y + DH / 2:.0f} {x - DW / 2:.0f},{y:.0f}" fill="#fff" stroke="#222" stroke-width="2"/>')
    svg.append(f'<text x="{x:.0f}" y="{y + 4:.0f}" text-anchor="middle" font-size="13">{escape(et)}</text>')
    for e, card in ((a, ca), (b, cb)):
        lx, ly = salida_rect(E[e][0], ew(e), EH, c)
        lx, ly = P((lx, ly))
        svg.append(f'<text x="{lx:.0f}" y="{ly + 6:.0f}" text-anchor="middle" font-size="18" font-weight="700" fill="#b8461f">{card}</text>')
svg.append('</svg>')
(SALIDA / 'er-chen.svg').write_text('\n'.join(svg), encoding='utf-8')

# ---------- draw.io
cel = ['<mxCell id="0"/>', '<mxCell id="1" parent="0"/>']
ids = {}
def nid(): nid.k += 1; return f'c{nid.k}'
nid.k = 1
def vert(valor, estilo, cx, cy, w, h):
    i = nid()
    cel.append(f'<mxCell id="{i}" value="{escape(valor)}" style="{estilo}" vertex="1" parent="1"><mxGeometry x="{cx - w / 2:.0f}" y="{cy - h / 2:.0f}" width="{w}" height="{h}" as="geometry"/></mxCell>')
    return i
def arista(a, b, grosor=1):
    cel.append(f'<mxCell id="{nid()}" style="endArrow=none;html=1;rounded=0;strokeWidth={grosor};" edge="1" parent="1" source="{a}" target="{b}"><mxGeometry relative="1" as="geometry"/></mxCell>')
for n, (c, _) in E.items():
    x, y = P(c)
    ids[n] = vert(n, 'rounded=0;whiteSpace=wrap;html=1;fontStyle=1;fontSize=14;strokeWidth=2;', x, y, ew(n), EH)
aid = []
for n, t, pk, c, w in attrs:
    x, y = P(c)
    aid.append((n, vert(t, 'ellipse;whiteSpace=wrap;html=1;fontSize=11;' + ('fontStyle=4;' if pk else ''), x, y, w, AH)))
did = []
for et, c, a, ca, b, cb in diam:
    x, y = P(c)
    did.append((vert(et, 'rhombus;whiteSpace=wrap;html=1;fontSize=11;', x, y, DW, DH), a, ca, b, cb, c))
for n, i in aid:
    arista(ids[n], i)
for i, a, ca, b, cb, c in did:
    arista(ids[a], i, 2)
    arista(ids[b], i, 2)
    for e, card in ((a, ca), (b, cb)):
        lx, ly = salida_rect(E[e][0], ew(e), EH, c)
        lx, ly = P((lx, ly))
        vert(card, 'text;html=1;align=center;verticalAlign=middle;fontStyle=1;fontSize=14;fontColor=#B8461F;', lx, ly, 34, 22)
xml = (f'<mxfile host="app.diagrams.net"><diagram name="Jokoa-ER" id="jokoa-er"><mxGraphModel grid="1" page="0" gridSize="10" '
       f'guides="1" tooltips="1" connect="1" arrows="1" fold="1" pageScale="1" pageWidth="{W}" pageHeight="{H}" math="0" shadow="0"><root>'
       + ''.join(cel) + '</root></mxGraphModel></diagram></mxfile>')
(SALIDA / 'ER-Jokoa-Chen.drawio').write_text(xml, encoding='utf-8')
print(W, H, len(attrs), 'atributos', len(diam), 'relaciones')
