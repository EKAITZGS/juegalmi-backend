<?php

namespace App\Service;

use App\Entity\Compra;
use Dompdf\Dompdf;

// Factura en PDF de una compra: se genera al pedirla, con los precios guardados en las líneas
final class Factura
{
    private const IVA = 0.21;

    public function pdf(Compra $compra): string
    {
        $h = fn (?string $t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
        $eur = fn (float $n) => number_format($n, 2, ',', '.').' €';

        $filas = '';
        $total = 0.0;
        foreach ($compra->getLineas() as $l) {
            $unidad = (float) $l->getPrecioUnitario();
            $garantia = (float) $l->getPrecioGarantia();
            $importe = $l->getCantidad() * ($unidad + $garantia);
            $total += $importe;
            $detalle = $h($l->getProducto()->getNombre());
            if ($l->getGarantiaMeses()) {
                $detalle .= '<br><small>Garantía ampliada +'.$l->getGarantiaMeses().' meses ('.$eur($garantia).' por unidad)</small>';
            }
            $filas .= '<tr><td>'.$detalle.'</td><td class="n">'.$l->getCantidad().'</td><td class="n">'.$eur($unidad + $garantia).'</td><td class="n">'.$eur($importe).'</td></tr>';
        }
        $base = $total / (1 + self::IVA);
        $numero = sprintf('JOK-%s-%05d', $compra->getFecha()->format('Y'), $compra->getId());
        $u = $compra->getUsuario();
        $tienda = $compra->getTienda();
        $recogida = $tienda ? $h($tienda->getNombre()).'<br>'.$h($tienda->getDireccion()) : 'Sin tienda indicada';

        $html = <<<HTML
<html><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1b2430; }
h1 { margin: 0; font-size: 26px; color: #1b5fa8; }
.cab { width: 100%; margin-bottom: 24px; }
.cab td { vertical-align: top; }
.d { text-align: right; }
table.l { width: 100%; border-collapse: collapse; margin-top: 8px; }
table.l th { background: #e3eefb; text-align: left; padding: 8px; }
table.l td { padding: 8px; border-bottom: 1px solid #dde3ee; }
.n { text-align: right; white-space: nowrap; }
.tot { width: 40%; margin-left: 60%; margin-top: 16px; border-collapse: collapse; }
.tot td { padding: 4px 8px; }
.tot .g { font-size: 14px; font-weight: bold; border-top: 2px solid #1b5fa8; }
.pie { margin-top: 36px; color: #5b6478; font-size: 9px; }
</style></head><body>
<table class="cab"><tr>
<td><h1>Jokoa</h1>Tienda de videojuegos y móviles<br>Bizkaia</td>
<td class="d"><b>Factura {$h($numero)}</b><br>Fecha: {$compra->getFecha()->format('d/m/Y')}</td>
</tr></table>
<table class="cab"><tr>
<td><b>Cliente</b><br>{$h($u->getNombre())} {$h($u->getApellidos())}<br>{$h($u->getEmail())}</td>
<td class="d"><b>Recogida en tienda</b><br>{$recogida}</td>
</tr></table>
<table class="l"><tr><th>Producto</th><th class="n">Uds.</th><th class="n">Precio ud.</th><th class="n">Importe</th></tr>{$filas}</table>
<table class="tot">
<tr><td>Base imponible</td><td class="n">{$eur($base)}</td></tr>
<tr><td>IVA 21 %</td><td class="n">{$eur($total - $base)}</td></tr>
<tr class="g"><td class="g">Total</td><td class="n g">{$eur($total)}</td></tr>
</table>
<p class="pie">Los precios incluyen IVA. Documento generado por la tienda de demostración Jokoa (proyecto de clase): no tiene validez fiscal.</p>
</body></html>
HTML;

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
