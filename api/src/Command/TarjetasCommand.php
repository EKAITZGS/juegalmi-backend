<?php

namespace App\Command;

use App\Entity\Articulo;
use App\Entity\Movil;
use App\Entity\Producto;
use App\Entity\Videojuego;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

// Tarjetas ilustradas (SVG, estilo Jokoa) para los productos que no tienen foto: sustituyen al marcador gris.
// Se guardan en api/public/img/tarjetas/<producto>.svg. Idempotente: solo toca productos con imagen de marcador.
#[AsCommand(name: 'app:tarjetas', description: 'Genera tarjetas SVG para los productos sin foto')]
class TarjetasCommand extends Command
{
    private const BASE_URL = 'https://jokoa.duckdns.org/img/tarjetas/';

    /** categoría o plataforma => [color 1, color 2] */
    private const COLORES = [
        'portatil' => ['#1e3a8a', '#3b82f6'], 'sobremesa' => ['#0f766e', '#14b8a6'], 'pc_gaming' => ['#7c1d1d', '#ef4444'],
        'tablet' => ['#5b21b6', '#a78bfa'], 'consola' => ['#1f2937', '#6b7280'], 'mando' => ['#9a3412', '#fb923c'],
        'auriculares' => ['#831843', '#ec4899'], 'teclado' => ['#334155', '#64748b'], 'raton' => ['#166534', '#4ade80'],
        'monitor' => ['#075985', '#38bdf8'], 'silla' => ['#7f1d1d', '#f43f5e'], 'movil' => ['#312e81', '#818cf8'],
        'PlayStation 5' => ['#003087', '#2563eb'], 'PlayStation 4' => ['#00205b', '#1d4ed8'], 'Xbox Series X|S' => ['#14532d', '#22c55e'],
        'Xbox One' => ['#14532d', '#16a34a'], 'Nintendo Switch 2' => ['#991b1b', '#ef4444'], 'Nintendo Switch' => ['#b91c1c', '#f87171'],
        'PC' => ['#1f2937', '#4b5563'],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $carpeta = dirname(__DIR__, 2).'/public/img/tarjetas/';
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $slugger = new AsciiSlugger();
        $hechas = 0;

        foreach ($this->em->getRepository(Producto::class)->findAll() as $producto) {
            $imagen = $producto->getImagenes()->first();
            if (!$imagen || !(str_contains($imagen->getUrl(), 'placehold.co') || str_starts_with($imagen->getUrl(), self::BASE_URL))) {
                continue;
            }
            [$grupo, $subtitulo] = $this->clasificar($producto);
            $archivo = strtolower($slugger->slug($producto->getNombre())).'.svg';
            file_put_contents($carpeta.$archivo, $this->svg($producto->getNombre(), $subtitulo, $grupo));
            $imagen->setUrl(self::BASE_URL.$archivo);
            ++$hechas;
        }
        $this->em->flush();
        $output->writeln("Tarjetas generadas: $hechas.");

        return Command::SUCCESS;
    }

    /** @return array{0:string,1:string} grupo (para color y dibujo) y subtítulo */
    private function clasificar(Producto $producto): array
    {
        if ($articulo = $this->em->getRepository(Articulo::class)->findOneBy(['producto' => $producto])) {
            return [$articulo->getCategoria(), Articulo::CATEGORIAS[$articulo->getCategoria()].' · '.$articulo->getMarca()];
        }
        if ($movil = $this->em->getRepository(Movil::class)->findOneBy(['producto' => $producto])) {
            return ['movil', 'Móvil · '.$movil->getMarca()];
        }
        $juego = $this->em->getRepository(Videojuego::class)->findOneBy(['producto' => $producto]);

        return [$juego?->getPlataforma()?->getNombre() ?? 'PC', $juego ? $juego->getPlataforma()->getNombre().' · '.$juego->getGenero() : 'Videojuego'];
    }

    private function svg(string $nombre, string $subtitulo, string $grupo): string
    {
        [$c1, $c2] = self::COLORES[$grupo] ?? ['#1f2937', '#6b7280'];
        // Formato vertical 3:4, como las portadas: encaja en el marco de la tarjeta sin recortes
        $lineas = array_slice(explode("\n", wordwrap($nombre, 16, "\n", true)), 0, 3);
        $texto = '';
        foreach ($lineas as $i => $linea) {
            $texto .= sprintf('<text x="300" y="%d" font-size="46" font-weight="700" text-anchor="middle" fill="#fff">%s</text>', 540 + $i * 56, htmlspecialchars($linea, ENT_XML1));
        }
        $y = 540 + (count($lineas) - 1) * 56 + 50;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 800" font-family="Segoe UI, Roboto, Helvetica, Arial, sans-serif">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$c1.'"/><stop offset="1" stop-color="'.$c2.'"/></linearGradient></defs>'
            .'<rect width="600" height="800" fill="url(#g)"/>'
            .'<circle cx="540" cy="90" r="210" fill="#fff" fill-opacity=".07"/><circle cx="60" cy="800" r="200" fill="#fff" fill-opacity=".06"/>'
            .'<g fill="none" stroke="#fff" stroke-opacity=".85" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" transform="translate(140 150) scale(1.6)">'.$this->dibujo($grupo).'</g>'
            .$texto
            .'<text x="300" y="'.$y.'" font-size="26" text-anchor="middle" fill="#fff" fill-opacity=".8">'.htmlspecialchars($subtitulo, ENT_XML1).'</text>'
            .'<text x="300" y="760" font-size="22" font-weight="800" letter-spacing="6" text-anchor="middle" fill="#fff" fill-opacity=".55">JOKOA</text>'
            .'</svg>';
    }

    /** Dibujo sencillo de 200x160 aprox. según el tipo de producto */
    private function dibujo(string $grupo): string
    {
        return match ($grupo) {
            'portatil' => '<rect x="30" y="20" width="140" height="90" rx="8"/><path d="M10 124h180l-12 16H22z"/>',
            'sobremesa' => '<rect x="20" y="30" width="70" height="110" rx="8"/><circle cx="55" cy="75" r="18"/><path d="M110 40h80v60h-80zM150 100v22M130 124h40"/>',
            'pc_gaming' => '<rect x="50" y="10" width="100" height="140" rx="10"/><circle cx="100" cy="55" r="22"/><circle cx="100" cy="55" r="5"/><path d="M70 105h60M70 122h60"/>',
            'tablet' => '<rect x="30" y="15" width="140" height="125" rx="14"/><circle cx="100" cy="128" r="3"/>',
            'consola' => '<rect x="25" y="55" width="150" height="55" rx="14"/><path d="M45 80h50M130 82h.1M150 82h.1"/>',
            'mando' => '<path d="M60 40h80c25 0 40 35 45 70 4 22-18 30-32 14l-14-20h-78l-14 20c-14 16-36 8-32-14 5-35 20-70 45-70z"/><path d="M70 70v22M59 81h22M130 74h.1M148 88h.1"/>',
            'auriculares' => '<path d="M45 100V80a55 55 0 0 1 110 0v20"/><rect x="30" y="95" width="28" height="50" rx="10"/><rect x="142" y="95" width="28" height="50" rx="10"/>',
            'teclado' => '<rect x="10" y="40" width="180" height="90" rx="10"/><path d="M30 65h12M56 65h12M82 65h12M108 65h12M134 65h12M160 65h6M30 87h12M56 87h12M82 87h12M108 87h12M134 87h12M160 87h6M55 110h90"/>',
            'raton' => '<rect x="65" y="15" width="70" height="125" rx="35"/><path d="M100 15v45M65 60h70"/>',
            'monitor' => '<rect x="15" y="20" width="170" height="100" rx="8"/><path d="M100 120v22M70 144h60"/>',
            'silla' => '<path d="M70 15h60a12 12 0 0 1 12 12v55H58V27a12 12 0 0 1 12-12zM52 90h96v22H52zM100 112v25M60 146h80M60 146l-6 8M140 146l6 8"/>',
            'movil' => '<rect x="65" y="10" width="70" height="140" rx="14"/><path d="M90 24h20"/>',
            default => '<rect x="45" y="15" width="110" height="140" rx="10"/><circle cx="100" cy="75" r="30"/><circle cx="100" cy="75" r="6"/>',
        };
    }
}
