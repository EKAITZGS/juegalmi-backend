import math, os
E = {
 'Usuario': (530,900,['PK id','email (único)','password (hash)','nombre','apellidos','telefono','roles (JSON)','fecha_alta']),
 'Producto': (700,300,['PK id','nombre','descripcion','precio','stock','fecha_alta']),
 'Imagen': (1060,300,['PK id','FK producto_id','url','orden']),
 'Movil': (700,80,['PK,FK producto_id','marca','almacenamiento_gb','ram_gb']),
 'Videojuego': (360,300,['PK,FK producto_id','FK plataforma_id','genero','pegi','precio_alquiler_dia']),
 'Plataforma': (30,300,['PK id','nombre']),
 'Compra': (700,620,['PK id','FK usuario_id','fecha']),
 'LineaCompra': (1060,620,['PK id','FK compra_id','FK producto_id','cantidad','precio_unitario']),
 'Alquiler': (360,620,['PK id','FK usuario_id','FK videojuego_id','fecha_inicio','fecha_fin','fecha_devolucion','precio_dia']),
 'Ticket': (30,900,['PK id','FK usuario_id','FK departamento_id','asunto','descripcion','dispositivo','estado','fecha_apertura','fecha_cierre']),
 'Departamento': (30,620,['PK id','nombre']),
 'Ubicacion': (1000,900,['PK id','FK usuario_id','latitud','longitud','fecha','accion']),
}
W = 260; H0 = 36; RH = 24

def box(n):
    x, y, f = E[n]
    return x, y, W, H0 + RH * len(f)

R = [('Plataforma','Videojuego','1','N'), ('Producto','Videojuego','1','0..1'), ('Producto','Movil','1','0..1'),
     ('Producto','Imagen','1','N'), ('Producto','LineaCompra','1','N'), ('Compra','LineaCompra','1','N'),
     ('Usuario','Compra','1','N'), ('Usuario','Alquiler','1','N'), ('Videojuego','Alquiler','1','N'),
     ('Usuario','Ticket','1','N'), ('Departamento','Ticket','1','N'), ('Usuario','Ubicacion','1','N')]

def anchor(a, b):
    ax, ay, aw, ah = box(a); bx, by, bw, bh = box(b)
    acx, acy = ax + aw / 2, ay + ah / 2
    bcx, bcy = bx + bw / 2, by + bh / 2
    dx, dy = bcx - acx, bcy - acy
    if abs(dx) > abs(dy) * 1.6 or abs(dy) < 30:
        if dx > 0: return (ax + aw, acy), (bx, bcy)
        return (ax, acy), (bx + bw, bcy)
    if dy > 0: return (acx, ay + ah), (bcx, by)
    return (acx, ay), (bcx, by + bh)

svg = []
for a, b, ca, cb in R:
    p, q = anchor(a, b)
    svg.append(f'<line x1="{p[0]}" y1="{p[1]}" x2="{q[0]}" y2="{q[1]}" stroke="#5b6b82" stroke-width="2"/>')
    for pt, other, c in ((p, q, ca), (q, p, cb)):
        dx, dy = other[0] - pt[0], other[1] - pt[1]
        d = math.hypot(dx, dy) or 1
        tx, ty = pt[0] + dx / d * 28, pt[1] + dy / d * 28
        ox, oy = -dy / d * 12, dx / d * 12
        svg.append(f'<text x="{tx+ox:.0f}" y="{ty+oy+5:.0f}" font-size="15" font-weight="700" fill="#D85A30" text-anchor="middle">{c}</text>')

for n, (x, y, f) in E.items():
    h = H0 + RH * len(f)
    hc = '#2f7bc4' if n in ('Movil', 'Videojuego') else '#185FA5'
    svg.append(f'<rect x="{x}" y="{y}" width="{W}" height="{h}" rx="8" fill="#fff" stroke="#185FA5" stroke-width="2"/>')
    svg.append(f'<path d="M{x},{y+H0} v-{H0-8} a8,8 0 0 1 8,-8 h{W-16} a8,8 0 0 1 8,8 v{H0-8} z" fill="{hc}"/>')
    svg.append(f'<text x="{x+W/2}" y="{y+24}" font-size="17" font-weight="700" fill="#fff" text-anchor="middle">{n}</text>')
    for i, a in enumerate(f):
        yy = y + H0 + RH * i + 17
        if a.startswith('PK') or a.startswith('FK'):
            tag, name = a.split(' ', 1)
            col = '#D85A30' if 'PK' in tag else '#185FA5'
            nx = x + 78 if ',' in tag else x + 40
            svg.append(f'<text x="{x+10}" y="{yy}" font-size="12" font-weight="700" fill="{col}">{tag}</text>')
            svg.append(f'<text x="{nx}" y="{yy}" font-size="14" fill="#1B2430">{name}</text>')
        else:
            svg.append(f'<text x="{x+40}" y="{yy}" font-size="14" fill="#1B2430">{a}</text>')
        if i < len(f) - 1:
            svg.append(f'<line x1="{x}" y1="{yy+7}" x2="{x+W}" y2="{yy+7}" stroke="#e3e9f2"/>')

head = ('<html><body style="margin:0;background:#F4F7FB"><svg xmlns="http://www.w3.org/2000/svg" width="1360" height="1230" '
        'font-family="Roboto,Segoe UI,Arial,sans-serif"><rect width="1360" height="1230" fill="#F4F7FB"/>'
        '<text x="30" y="40" font-size="26" font-weight="700" fill="#185FA5">Jokoa · Modelo Entidad-Relación</text>'
        '<text x="30" y="63" font-size="14" fill="#5b6b82">Tienda de videojuegos y móviles (MariaDB 11.4)</text>')
foot = ('<text x="680" y="1190" font-size="13" fill="#5b6b82" text-anchor="middle">PK clave primaria · FK clave foránea · '
        '1 / N / 0..1 cardinalidad · Movil y Videojuego son especializaciones 1:1 de Producto (su PK es producto_id)</text></svg></body></html>')
here = os.path.dirname(os.path.abspath(__file__))
open(os.path.join(here, 'modelo-er.html'), 'w', encoding='utf-8').write(head + ''.join(svg) + foot)
