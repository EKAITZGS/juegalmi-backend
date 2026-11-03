<?php

namespace App\Command;

use App\Entity\Articulo;
use App\Entity\Imagen;
use App\Entity\Movil;
use App\Entity\Plataforma;
use App\Entity\Producto;
use App\Entity\Videojuego;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// Segunda ampliación del catálogo: más plataformas, juegos y móviles, y los artículos de las secciones
// Ordenadores, Tablets y Zona gaming. Idempotente: solo crea lo que falta (por nombre).
// Precios y stock son de demostración. Las fotos se asignan aparte (api/public/img/juegos/<archivo>).
#[AsCommand(name: 'app:catalogo-ampliado', description: 'Más juegos, móviles y artículos (ordenadores, tablets, zona gaming)')]
class CatalogoAmpliadoCommand extends Command
{
    private const BASE_IMG = 'https://jokoa.duckdns.org/img/juegos/';

    /** nombre del producto => archivo (se rellena al añadir las fotos) */
    private const IMAGENES = [
        'PlayStation 5 Pro' => 'playstation-5-pro.jpg',
        'PlayStation 5 Slim (con lector)' => 'playstation-5-slim-con-lector.png',
        'Xbox Series X' => 'xbox-series-x.jpg',
        'Xbox Series S' => 'xbox-series-s.jpg',
        'Nintendo Switch 2' => 'nintendo-switch-2.jpg',
        'Nintendo Switch OLED' => 'nintendo-switch-oled.png',
        'Nintendo Switch Lite' => 'nintendo-switch-lite.jpg',
        'Steam Deck OLED' => 'steam-deck-oled.jpg',
        'Mando DualSense (PS5)' => 'mando-dualsense-ps5.jpg',
        'Mando inalámbrico Xbox' => 'mando-inalambrico-xbox.jpg',
        'Nintendo Switch Pro Controller' => 'nintendo-switch-pro-controller.jpg',
        'iPad 11"' => 'ipad-11.png',
        'iPad Air 11" M3' => 'ipad-air-11-m3.jpg',
        'iPad Pro 13" M4' => 'ipad-pro-13-m4.jpg',
        'iPad mini 8,3"' => 'ipad-mini-8-3.jpg',
        'MacBook Air 13" M4' => 'macbook-air-13-m4.jpg',
        'Mac mini M4' => 'mac-mini-m4.jpg',
        'iPhone 17 Pro' => 'iphone-17-pro.jpg',
        'iPhone 17 Pro Max' => 'iphone-17-pro-max.jpg',
        'Galaxy S25 Ultra' => 'galaxy-s25-ultra.jpg',
        'Galaxy A16 5G' => 'galaxy-a16-5g.jpg',
        'Xiaomi 15' => 'xiaomi-15.jpg',
        'Honor 400' => 'honor-400.jpg',
        'Redmi Note 14' => 'redmi-note-14.jpg',
        'Age of Empires IV' => 'age-of-empires-iv.png',
        'Animal Crossing: New Horizons' => 'animal-crossing-new-horizons.jpg',
        'Bloodborne' => 'bloodborne.jpg',
        'Celeste' => 'celeste.png',
        'Counter-Strike 2' => 'counter-strike-2.jpg',
        'Diablo IV' => 'diablo-iv.png',
        'EA Sports FC 25' => 'ea-sports-fc-25.jpg',
        'Euro Truck Simulator 2' => 'euro-truck-simulator-2.jpg',
        'Final Fantasy VII Rebirth' => 'final-fantasy-vii-rebirth.png',
        'Fire Emblem: Three Houses' => 'fire-emblem-three-houses.jpg',
        'Football Manager 2026' => 'football-manager-2026.png',
        'Forza Horizon 4' => 'forza-horizon-4.jpg',
        'Gears 5' => 'gears-5.png',
        'Hades II' => 'hades-ii.jpg',
        'Hogwarts Legacy' => 'hogwarts-legacy.png',
        'Hollow Knight' => 'hollow-knight.jpg',
        'Horizon Forbidden West' => 'horizon-forbidden-west.jpg',
        'It Takes Two' => 'it-takes-two.png',
        'Just Dance 2026' => 'just-dance-2026.jpg',
        'Kirby y la Tierra Olvidada' => 'kirby-y-la-tierra-olvidada.jpg',
        'Luigi\'s Mansion 3' => 'luigi-s-mansion-3.jpg',
        'Mario Kart 8 Deluxe' => 'mario-kart-8-deluxe.jpg',
        'Mario Party Superstars' => 'mario-party-superstars.jpg',
        'Ori and the Will of the Wisps' => 'ori-and-the-will-of-the-wisps.jpg',
        'Overcooked! 2' => 'overcooked-2.png',
        'Pokémon Escarlata' => 'pokemon-escarlata.png',
        'Portal 2' => 'portal-2.jpg',
        'Ratchet & Clank: Rift Apart' => 'ratchet-clank-rift-apart.png',
        'Resident Evil 4 Remake' => 'resident-evil-4-remake.jpg',
        'Sea of Thieves' => 'sea-of-thieves.jpg',
        'Silent Hill 2' => 'silent-hill-2.jpg',
        'Slay the Spire' => 'slay-the-spire.jpg',
        'Splatoon 3' => 'splatoon-3.jpg',
        'Split Fiction' => 'split-fiction.jpg',
        'Street Fighter 6' => 'street-fighter-6.jpg',
        'Super Smash Bros. Ultimate' => 'super-smash-bros-ultimate.jpg',
        'Tekken 8' => 'tekken-8.jpg',
        'Terraria' => 'terraria.jpg',
        'The Last of Us Parte II Remastered' => 'the-last-of-us-parte-ii-remastered.png',
        'Acer Nitro V 15' => 'acer-nitro-v-15.jpg',
        'Dell XPS 13' => 'dell-xps-13.jpg',
        'Logitech MX Master 3S' => 'logitech-mx-master-3s.jpg',
        'Logitech G Pro X Superlight 2' => 'logitech-g-pro-x-superlight-2.jpg',
        'Logitech K380' => 'logitech-k380.jpg',
        'Galaxy Tab A9+' => 'galaxy-tab-a9.jpg',
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(Producto::class);
        $plataformas = [];
        foreach ($this->em->getRepository(Plataforma::class)->findAll() as $p) {
            $plataformas[$p->getNombre()] = $p;
        }
        foreach (['PlayStation 4', 'Xbox One', 'Nintendo Switch'] as $nombre) {
            if (!isset($plataformas[$nombre])) {
                $this->em->persist($plataformas[$nombre] = new Plataforma($nombre));
            }
        }
        $this->em->flush();

        $nuevos = 0;
        // [nombre, descripción, precio, stock, plataforma, género, PEGI, alquiler/día, subgénero, jugadores, online, duración, dificultad, etiquetas]
        foreach ($this->juegos() as [$nombre, $desc, $precio, $stock, $plat, $genero, $pegi, $alq, $sub, $jug, $online, $dur, $dif, $etq]) {
            if ($repo->findOneBy(['nombre' => $nombre])) {
                continue;
            }
            $producto = $this->producto($nombre, $desc, $precio, $stock, ['subgenero' => $sub, 'jugadores' => $jug, 'online' => $online, 'duracion' => $dur, 'dificultad' => $dif, 'idioma' => 'español', 'etiquetas' => $etq]);
            $this->em->persist((new Videojuego())->setProducto($producto)->setPlataforma($plataformas[$plat])->setGenero($genero)->setPegi($pegi)->setPrecioAlquilerDia($alq));
            ++$nuevos;
        }
        // [nombre, descripción, precio, stock, marca, almacenamiento, RAM, pantalla, cámara, batería, sistema, gama, ideal para]
        foreach ($this->moviles() as [$nombre, $desc, $precio, $stock, $marca, $alm, $ram, $pan, $cam, $bat, $sis, $gama, $ideal]) {
            if ($repo->findOneBy(['nombre' => $nombre])) {
                continue;
            }
            $producto = $this->producto($nombre, $desc, $precio, $stock, ['pantalla' => $pan, 'camara' => $cam, 'bateria' => $bat, 'sistema' => $sis, 'cinco_g' => true, 'gama' => $gama, 'ideal_para' => $ideal]);
            $this->em->persist((new Movil())->setProducto($producto)->setMarca($marca)->setAlmacenamientoGb($alm)->setRamGb($ram));
            ++$nuevos;
        }
        // [categoría, nombre, descripción, precio, stock, marca, montaje, características]
        foreach ($this->articulos() as [$categoria, $nombre, $desc, $precio, $stock, $marca, $montaje, $car]) {
            if ($repo->findOneBy(['nombre' => $nombre])) {
                continue;
            }
            $producto = $this->producto($nombre, $desc, $precio, $stock, $car);
            $this->em->persist((new Articulo())->setProducto($producto)->setCategoria($categoria)->setMarca($marca)->setMontaje($montaje));
            ++$nuevos;
        }
        $this->em->flush();

        // Fotos y tarjetas: api/public/img/juegos/<archivo>
        $fotos = 0;
        foreach (self::IMAGENES as $nombre => $archivo) {
            if ($imagen = $repo->findOneBy(['nombre' => $nombre])?->getImagenes()->first()) {
                $imagen->setUrl(self::BASE_IMG.$archivo);
                ++$fotos;
            }
        }
        $this->em->flush();
        $output->writeln("Productos nuevos: $nuevos. Imágenes asignadas: $fotos.");

        return Command::SUCCESS;
    }

    private function producto(string $nombre, string $descripcion, string $precio, int $stock, array $caracteristicas): Producto
    {
        return (new Producto($nombre, $descripcion, $precio, $stock))
            ->setCaracteristicas($caracteristicas)
            ->addImagen((new Imagen())->setUrl('https://placehold.co/600x400/png?text='.urlencode($nombre))->setOrden(1));
    }

    private function juegos(): array
    {
        return [
            ['EA Sports FC 25', 'Fútbol con ligas y equipos oficiales para PlayStation 4.', '29.99', 6, 'PlayStation 4', 'Deportes', 3, '1.50', 'fútbol', '1-4', true, 'sin fin', 'media', ['deportes', 'competitivo']],
            ['The Last of Us Parte II Remastered', 'Supervivencia y drama posapocalíptico, con mejoras gráficas.', '39.99', 5, 'PlayStation 5', 'Acción-aventura', 18, '2.00', 'aventura de supervivencia', '1', false, '25 h', 'media', ['historia', 'tensión']],
            ['Horizon Forbidden West', 'Aloy explora un oeste postapocalíptico lleno de máquinas gigantes.', '39.99', 6, 'PlayStation 5', 'Acción-aventura', 16, '2.00', 'mundo abierto de acción', '1', false, '40 h', 'media', ['mundo abierto', 'historia']],
            ['Bloodborne', 'Rol de acción gótico, exigente y atmosférico.', '19.99', 4, 'PlayStation 4', 'Rol', 18, '1.00', 'souls-like', '1', true, '35 h', 'alta', ['desafiante', 'fantasía oscura']],
            ['Resident Evil 4 Remake', 'Survival horror de acción con Leon S. Kennedy.', '39.99', 6, 'PlayStation 5', 'Terror', 18, '2.00', 'survival horror', '1', false, '15 h', 'media', ['terror', 'acción']],
            ['Silent Hill 2', 'Remake del clásico de terror psicológico.', '59.99', 4, 'PlayStation 5', 'Terror', 18, '2.50', 'terror psicológico', '1', false, '13 h', 'media', ['terror', 'historia']],
            ['Final Fantasy VII Rebirth', 'JRPG con mundo abierto y combates de acción por turnos.', '69.99', 5, 'PlayStation 5', 'Rol', 16, '2.50', 'JRPG de acción', '1', false, '60 h', 'media', ['rpg', 'historia']],
            ['Street Fighter 6', 'Lucha 2D con modo historia y un online muy completo.', '49.99', 5, 'PlayStation 5', 'Lucha', 12, '2.00', 'lucha 2D', '1-2', true, 'sin fin', 'media', ['competitivo', 'multijugador']],
            ['Tekken 8', 'Lucha 3D con combates espectaculares.', '59.99', 4, 'PlayStation 5', 'Lucha', 16, '2.50', 'lucha 3D', '1-2', true, 'sin fin', 'media-alta', ['competitivo', 'multijugador']],
            ['It Takes Two', 'Cooperativo obligatorio para dos con ideas nuevas cada nivel.', '29.99', 6, 'PlayStation 5', 'Plataformas', 12, '1.50', 'cooperativo de plataformas', '2', true, '12 h', 'baja', ['cooperativo', 'pareja']],
            ['Ratchet & Clank: Rift Apart', 'Aventura de acción con saltos entre dimensiones.', '39.99', 5, 'PlayStation 5', 'Plataformas', 12, '2.00', 'plataformas y disparos', '1', false, '12 h', 'baja', ['familiar', 'acción']],
            ['Gears 5', 'Shooter en tercera persona con cooperativo.', '19.99', 5, 'Xbox One', 'Disparos', 18, '1.00', 'shooter en tercera persona', '1-3', true, '10 h', 'media', ['acción', 'cooperativo']],
            ['Sea of Thieves', 'Piratas cooperativos en un mar abierto.', '29.99', 6, 'Xbox Series X|S', 'Aventura', 12, '1.50', 'aventura pirata cooperativa', '1-4', true, 'sin fin', 'baja', ['cooperativo', 'online']],
            ['Forza Horizon 4', 'Carreras de mundo abierto en Gran Bretaña.', '19.99', 6, 'Xbox One', 'Conducción', 3, '1.00', 'carreras de mundo abierto', '1-12', true, '30 h', 'baja', ['coches', 'relajado']],
            ['Diablo IV', 'Rol de acción con botín y mazmorras sin fin.', '49.99', 5, 'Xbox Series X|S', 'Rol', 18, '2.00', 'ARPG de mazmorras', '1-4', true, '40+ h', 'media', ['rpg', 'cooperativo']],
            ['Ori and the Will of the Wisps', 'Plataformas 2D preciosos y emotivos.', '19.99', 6, 'Xbox Series X|S', 'Plataformas', 7, '1.00', 'metroidvania', '1', false, '12 h', 'media', ['2D', 'historia']],
            ['Animal Crossing: New Horizons', 'Isla paradisíaca que decoras y vives a tu ritmo.', '49.99', 8, 'Nintendo Switch', 'Simulación', 3, '1.50', 'simulación de vida', '1-4', true, 'sin fin', 'baja', ['familiar', 'relajado', 'cooperativo']],
            ['Mario Kart 8 Deluxe', 'El clásico de carreras de karts para toda la familia.', '49.99', 10, 'Nintendo Switch', 'Carreras', 3, '1.50', 'carreras arcade', '1-4', true, 'sin fin', 'baja', ['familiar', 'multijugador']],
            ['Super Smash Bros. Ultimate', 'Lucha de plataformas con los personajes de Nintendo.', '49.99', 7, 'Nintendo Switch', 'Lucha', 12, '1.50', 'lucha de plataformas', '1-8', true, 'sin fin', 'media', ['multijugador', 'fiesta']],
            ['Splatoon 3', 'Tiroteos de tinta por equipos, rápidos y coloridos.', '49.99', 6, 'Nintendo Switch', 'Disparos', 7, '1.50', 'shooter de tinta', '1-8', true, 'sin fin', 'media', ['multijugador', 'competitivo']],
            ['Kirby y la Tierra Olvidada', 'Plataformas 3D amable, ideal para empezar.', '49.99', 6, 'Nintendo Switch', 'Plataformas', 7, '1.50', 'plataformas 3D', '1-2', false, '10 h', 'baja', ['familiar', 'cooperativo']],
            ['Fire Emblem: Three Houses', 'Estrategia táctica y vida en la academia.', '49.99', 4, 'Nintendo Switch', 'Estrategia', 12, '2.00', 'rol táctico', '1', false, '60 h', 'media', ['rpg', 'historia']],
            ['Luigi\'s Mansion 3', 'Aventura con fantasmas en un hotel encantado.', '49.99', 5, 'Nintendo Switch', 'Aventura', 7, '1.50', 'aventura y puzles', '1-2', true, '12 h', 'baja', ['familiar', 'cooperativo']],
            ['Hollow Knight', 'Metroidvania de bichos en un reino subterráneo enorme.', '14.99', 8, 'Nintendo Switch', 'Aventura', 7, '1.00', 'metroidvania', '1', false, '30 h', 'alta', ['2D', 'desafiante']],
            ['Celeste', 'Plataformas de precisión con una historia sensible.', '19.99', 8, 'Nintendo Switch', 'Plataformas', 7, '1.00', 'plataformas de precisión', '1', false, '8 h', 'alta', ['2D', 'desafiante', 'historia']],
            ['Overcooked! 2', 'Cocina caótica en equipo, risas aseguradas.', '24.99', 7, 'Nintendo Switch', 'Simulación', 3, '1.00', 'cooperativo caótico', '1-4', true, '10 h', 'media', ['cooperativo', 'fiesta', 'familiar']],
            ['Pokémon Escarlata', 'Mundo abierto Pokémon para explorar con amigos.', '49.99', 7, 'Nintendo Switch', 'Rol', 7, '1.50', 'rol de captura', '1-4', true, '40 h', 'baja', ['familiar', 'cooperativo']],
            ['Counter-Strike 2', 'Shooter táctico competitivo por equipos.', '14.99', 10, 'PC', 'Disparos', 18, '1.00', 'shooter táctico', '1-10', true, 'sin fin', 'alta', ['competitivo', 'esports']],
            ['Hogwarts Legacy', 'Rol de acción en el mundo mágico de Hogwarts.', '39.99', 6, 'PC', 'Rol', 12, '2.00', 'rol de magia', '1', false, '40 h', 'baja', ['familiar', 'fantasía']],
            ['Age of Empires IV', 'Estrategia en tiempo real histórica.', '39.99', 5, 'PC', 'Estrategia', 12, '2.00', 'estrategia en tiempo real', '1-8', true, '30 h', 'media', ['estrategia']],
            ['Football Manager 2026', 'Dirige a tu club y construye una dinastía.', '49.99', 6, 'PC', 'Estrategia', 3, '2.00', 'gestión deportiva', '1', true, 'sin fin', 'media', ['deportes', 'estrategia']],
            ['Euro Truck Simulator 2', 'Conduce camiones por Europa con calma.', '9.99', 8, 'PC', 'Simulación', 3, '1.00', 'simulación de camiones', '1', true, 'sin fin', 'baja', ['relajado', 'conducción']],
            ['Portal 2', 'Puzles con portales y mucho humor. Cooperativo incluido.', '9.99', 8, 'PC', 'Puzles', 12, '1.00', 'puzles en primera persona', '1-2', true, '10 h', 'media', ['puzles', 'cooperativo']],
            ['Terraria', 'Explora, construye y combate en 2D.', '9.99', 8, 'PC', 'Aventura', 12, '1.00', 'aventura y construcción 2D', '1-8', true, 'sin fin', 'media', ['creativo', 'cooperativo']],
            ['Slay the Spire', 'Cartas roguelike muy adictivo.', '14.99', 8, 'PC', 'Estrategia', 12, '1.00', 'cartas roguelike', '1', false, 'sin fin', 'media', ['cartas', 'roguelike']],
            ['Hades II', 'Secuela del roguelike de acción mitológico.', '29.99', 7, 'PC', 'Acción', 12, '1.50', 'roguelike de acción', '1', false, '30+ h', 'media-alta', ['roguelike', 'mitología']],
            ['Split Fiction', 'Cooperativo para dos entre fantasía y ciencia ficción.', '39.99', 6, 'PlayStation 5', 'Aventura', 12, '2.00', 'cooperativo narrativo', '2', true, '12 h', 'baja', ['cooperativo', 'pareja']],
            ['Mario Party Superstars', 'Minijuegos para jugar con amigos y familia.', '49.99', 7, 'Nintendo Switch', 'Fiesta', 3, '1.50', 'minijuegos', '1-4', true, 'sin fin', 'baja', ['fiesta', 'familiar']],
            ['Just Dance 2026', 'Baila con tus amigos al ritmo de los éxitos.', '39.99', 7, 'Nintendo Switch', 'Música', 3, '1.00', 'baile', '1-6', true, 'sin fin', 'baja', ['fiesta', 'familiar']],
        ];
    }

    private function moviles(): array
    {
        return [
            ['Galaxy S25 Ultra', 'Tope de gama de Samsung con S Pen y cámara de 200 MP.', '1399.00', 3, 'Samsung', 512, 12, '6,9" AMOLED 120 Hz', 'cuádruple, 200 MP con zoom 5x', 'dura un día completo', 'Android', 'alta', ['fotografía', 'productividad', 'juegos']],
            ['Galaxy S25 FE', 'Experiencia premium de Samsung a un precio más contenido.', '699.00', 5, 'Samsung', 256, 8, '6,7" AMOLED 120 Hz', 'triple 50 MP', 'dura un día completo', 'Android', 'media-alta', ['uso diario', 'fotografía']],
            ['Galaxy A16 5G', 'Móvil sencillo con 5G, pantalla grande y mucha batería.', '199.00', 9, 'Samsung', 128, 4, '6,7" AMOLED 90 Hz', 'triple 50 MP', 'dura casi dos días', 'Android', 'económica', ['precio ajustado', 'batería', 'primer móvil']],
            ['Galaxy Z Flip7', 'Plegable de concha: pequeño en el bolsillo, grande al abrirlo.', '1199.00', 3, 'Samsung', 256, 12, '6,9" AMOLED plegable', 'doble 50 MP', 'dura un día', 'Android', 'alta', ['diseño', 'redes sociales']],
            ['iPhone 17 Pro', 'Pantalla de 120 Hz, cámara con zoom 4x y mucha potencia.', '1299.00', 3, 'Apple', 256, 12, '6,3" OLED 120 Hz', 'triple 48 MP con zoom 4x', 'dura un día completo', 'iOS', 'alta', ['fotografía', 'vídeo', 'juegos']],
            ['iPhone 17 Pro Max', 'El iPhone más grande, con la mejor batería.', '1499.00', 2, 'Apple', 512, 12, '6,9" OLED 120 Hz', 'triple 48 MP con zoom 4x', 'dura más de un día', 'iOS', 'alta', ['fotografía', 'vídeo', 'batería']],
            ['iPhone 15', 'Un iPhone muy completo a precio rebajado.', '659.00', 5, 'Apple', 128, 6, '6,1" OLED 60 Hz', 'doble 48 MP', 'dura un día', 'iOS', 'media-alta', ['uso diario', 'precio ajustado']],
            ['Pixel 10 Pro', 'Cámara de referencia y funciones de IA de Google.', '1099.00', 3, 'Google', 256, 16, '6,3" OLED 120 Hz', 'triple 50 MP con zoom 5x', 'dura un día completo', 'Android puro', 'alta', ['fotografía', 'inteligencia artificial']],
            ['Xiaomi 15', 'Compacto y potente, con cámaras Leica.', '799.00', 4, 'Xiaomi', 256, 12, '6,36" AMOLED 120 Hz', 'triple 50 MP Leica', 'dura un día completo', 'Android', 'alta', ['fotografía', 'compacto']],
            ['Redmi Note 14', 'Móvil económico muy equilibrado.', '229.00', 9, 'Xiaomi', 128, 6, '6,67" AMOLED 120 Hz', '108 MP', 'dura más de un día', 'Android', 'económica', ['precio ajustado', 'uso diario']],
            ['Poco F7', 'Rendimiento de gama alta a precio de media: ideal para jugar.', '399.00', 6, 'Xiaomi', 256, 12, '6,83" AMOLED 120 Hz', 'doble 50 MP', 'batería enorme de 6500 mAh', 'Android', 'media', ['juegos', 'batería', 'precio ajustado']],
            ['Honor 400', 'Diseño fino, buena cámara y carga rápida.', '399.00', 5, 'Honor', 256, 8, '6,55" AMOLED 120 Hz', 'doble 200 MP + 12 MP', 'dura un día y medio', 'Android', 'media', ['fotografía', 'uso diario']],
            ['Motorola Razr 60', 'Plegable asequible y muy original.', '699.00', 4, 'Motorola', 256, 8, '6,9" pOLED plegable', 'doble 50 MP', 'dura un día', 'Android casi puro', 'media-alta', ['diseño', 'originalidad']],
            ['Moto G15', 'Lo básico bien hecho, para quien quiere un móvil barato.', '129.00', 12, 'Motorola', 64, 4, '6,7" LCD 90 Hz', '50 MP', 'dura casi dos días', 'Android casi puro', 'económica', ['precio ajustado', 'primer móvil']],
            ['Realme 14 Pro', 'Cambia de color con el frío y tiene buenas cámaras.', '349.00', 5, 'Realme', 256, 8, '6,77" AMOLED 120 Hz', 'doble 50 MP con zoom 3x', 'dura un día y medio', 'Android', 'media', ['diseño', 'fotografía']],
            ['Xperia 1 VII', 'Para quien graba vídeo como profesional.', '1499.00', 2, 'Sony', 256, 12, '6,5" OLED 120 Hz', 'triple 48 MP con zoom óptico', 'dura un día', 'Android', 'alta', ['vídeo', 'fotografía']],
        ];
    }

    private function articulos(): array
    {
        return [
            // ----- Portátiles -----
            ['portatil', 'MacBook Air 13" M4', 'Ligero, silencioso y con batería para todo el día.', '1099.00', 5, 'Apple', '0.00', ['procesador' => 'Apple M4', 'ram_gb' => 16, 'almacenamiento_gb' => 256, 'pantalla' => '13,6" Liquid Retina', 'grafica' => 'integrada (10 núcleos)', 'sistema' => 'macOS', 'peso_kg' => 1.24, 'bateria' => 'hasta 18 h', 'ideal_para' => ['estudiar', 'trabajar', 'portabilidad']]],
            ['portatil', 'MacBook Pro 14" M4 Pro', 'Pantalla espectacular y potencia para edición y programación.', '2199.00', 3, 'Apple', '0.00', ['procesador' => 'Apple M4 Pro', 'ram_gb' => 24, 'almacenamiento_gb' => 512, 'pantalla' => '14,2" Liquid Retina XDR', 'grafica' => 'integrada (16 núcleos)', 'sistema' => 'macOS', 'peso_kg' => 1.6, 'bateria' => 'hasta 17 h', 'ideal_para' => ['vídeo', 'programación', 'diseño']]],
            ['portatil', 'Lenovo IdeaPad Slim 3', 'Portátil básico y fiable para estudiar y navegar.', '499.00', 8, 'Lenovo', '0.00', ['procesador' => 'AMD Ryzen 5 7520U', 'ram_gb' => 16, 'almacenamiento_gb' => 512, 'pantalla' => '15,6" Full HD', 'grafica' => 'integrada', 'sistema' => 'Windows 11', 'peso_kg' => 1.6, 'bateria' => 'hasta 8 h', 'ideal_para' => ['estudiar', 'precio ajustado']]],
            ['portatil', 'ASUS Vivobook 16', 'Pantalla grande y rendimiento equilibrado para el día a día.', '649.00', 6, 'ASUS', '0.00', ['procesador' => 'Intel Core i5-13420H', 'ram_gb' => 16, 'almacenamiento_gb' => 512, 'pantalla' => '16" Full HD+', 'grafica' => 'integrada', 'sistema' => 'Windows 11', 'peso_kg' => 1.88, 'bateria' => 'hasta 9 h', 'ideal_para' => ['trabajar', 'uso diario']]],
            ['portatil', 'HP Pavilion Aero 13', 'Ultraligero con buen rendimiento para trabajar en movimiento.', '799.00', 5, 'HP', '0.00', ['procesador' => 'AMD Ryzen 7 8840U', 'ram_gb' => 16, 'almacenamiento_gb' => 512, 'pantalla' => '13,3" WUXGA', 'grafica' => 'integrada', 'sistema' => 'Windows 11', 'peso_kg' => 0.98, 'bateria' => 'hasta 12 h', 'ideal_para' => ['portabilidad', 'trabajar']]],
            ['portatil', 'Dell XPS 13', 'Diseño premium y compacto con pantalla muy nítida.', '1399.00', 3, 'Dell', '0.00', ['procesador' => 'Intel Core Ultra 7', 'ram_gb' => 32, 'almacenamiento_gb' => 1024, 'pantalla' => '13,4" OLED', 'grafica' => 'integrada', 'sistema' => 'Windows 11', 'peso_kg' => 1.2, 'bateria' => 'hasta 14 h', 'ideal_para' => ['trabajar', 'diseño', 'portabilidad']]],
            ['portatil', 'Acer Nitro V 15', 'Portátil gaming de entrada para jugar en Full HD.', '899.00', 6, 'Acer', '0.00', ['procesador' => 'Intel Core i5-13420H', 'ram_gb' => 16, 'almacenamiento_gb' => 512, 'pantalla' => '15,6" Full HD 144 Hz', 'grafica' => 'NVIDIA RTX 4050', 'sistema' => 'Windows 11', 'peso_kg' => 2.1, 'bateria' => 'hasta 6 h', 'ideal_para' => ['juegos', 'precio ajustado']]],
            ['portatil', 'ASUS TUF Gaming A16', 'Resistente y potente para jugar sin gastar de más.', '1199.00', 5, 'ASUS', '0.00', ['procesador' => 'AMD Ryzen 7 7735HS', 'ram_gb' => 16, 'almacenamiento_gb' => 512, 'pantalla' => '16" Full HD+ 165 Hz', 'grafica' => 'AMD Radeon RX 7600S', 'sistema' => 'Windows 11', 'peso_kg' => 2.2, 'bateria' => 'hasta 8 h', 'ideal_para' => ['juegos', 'estudiar']]],
            ['portatil', 'MSI Katana 15', 'Gaming con gráfica potente y pantalla fluida.', '1299.00', 4, 'MSI', '0.00', ['procesador' => 'Intel Core i7-13620H', 'ram_gb' => 16, 'almacenamiento_gb' => 1024, 'pantalla' => '15,6" Full HD 144 Hz', 'grafica' => 'NVIDIA RTX 4060', 'sistema' => 'Windows 11', 'peso_kg' => 2.25, 'bateria' => 'hasta 5 h', 'ideal_para' => ['juegos', 'streaming']]],
            ['portatil', 'Lenovo Legion 5', 'Gaming equilibrado con muy buena refrigeración.', '1599.00', 4, 'Lenovo', '0.00', ['procesador' => 'AMD Ryzen 7 8845HS', 'ram_gb' => 32, 'almacenamiento_gb' => 1024, 'pantalla' => '16" WQXGA 165 Hz', 'grafica' => 'NVIDIA RTX 4070', 'sistema' => 'Windows 11', 'peso_kg' => 2.4, 'bateria' => 'hasta 6 h', 'ideal_para' => ['juegos', 'edición']]],
            ['portatil', 'ASUS ROG Strix G16', 'Gaming de gama alta con pantalla rápida.', '1999.00', 3, 'ASUS', '0.00', ['procesador' => 'Intel Core i9-14900HX', 'ram_gb' => 32, 'almacenamiento_gb' => 1024, 'pantalla' => '16" QHD 240 Hz', 'grafica' => 'NVIDIA RTX 4070', 'sistema' => 'Windows 11', 'peso_kg' => 2.5, 'bateria' => 'hasta 5 h', 'ideal_para' => ['juegos', 'competitivo']]],
            ['portatil', 'Razer Blade 16', 'Gaming premium en chasis de aluminio.', '2999.00', 2, 'Razer', '0.00', ['procesador' => 'AMD Ryzen AI 9 HX 370', 'ram_gb' => 32, 'almacenamiento_gb' => 2048, 'pantalla' => '16" OLED QHD+ 240 Hz', 'grafica' => 'NVIDIA RTX 5070 Ti', 'sistema' => 'Windows 11', 'peso_kg' => 2.1, 'bateria' => 'hasta 6 h', 'ideal_para' => ['juegos', 'creación de contenido']]],
            // ----- Sobremesa -----
            ['sobremesa', 'Mac mini M4', 'Pequeñísimo, silencioso y muy potente.', '699.00', 5, 'Apple', '0.00', ['procesador' => 'Apple M4', 'ram_gb' => 16, 'almacenamiento_gb' => 256, 'grafica' => 'integrada (10 núcleos)', 'sistema' => 'macOS', 'formato' => 'mini PC', 'ideal_para' => ['trabajar', 'programar', 'poco espacio']]],
            ['sobremesa', 'iMac 24" M4', 'Todo en uno con pantalla 4.5K.', '1599.00', 3, 'Apple', '0.00', ['procesador' => 'Apple M4', 'ram_gb' => 16, 'almacenamiento_gb' => 256, 'pantalla' => '24" 4.5K', 'grafica' => 'integrada (10 núcleos)', 'sistema' => 'macOS', 'formato' => 'todo en uno', 'ideal_para' => ['estudiar', 'diseño', 'oficina']]],
            ['sobremesa', 'HP Pavilion Desktop TP01', 'Torre sencilla para casa y oficina.', '549.00', 6, 'HP', '0.00', ['procesador' => 'Intel Core i5-13400', 'ram_gb' => 16, 'almacenamiento_gb' => 512, 'grafica' => 'integrada', 'sistema' => 'Windows 11', 'formato' => 'torre', 'ideal_para' => ['oficina', 'estudiar']]],
            ['sobremesa', 'Lenovo IdeaCentre AIO 3', 'Todo en uno con pantalla Full HD.', '749.00', 4, 'Lenovo', '0.00', ['procesador' => 'AMD Ryzen 5 7430U', 'ram_gb' => 16, 'almacenamiento_gb' => 512, 'pantalla' => '24" Full HD', 'grafica' => 'integrada', 'sistema' => 'Windows 11', 'formato' => 'todo en uno', 'ideal_para' => ['familia', 'oficina']]],
            ['sobremesa', 'Intel NUC 14 Pro', 'Mini PC discreto para trabajar o como centro multimedia.', '599.00', 5, 'Intel', '0.00', ['procesador' => 'Intel Core Ultra 5', 'ram_gb' => 16, 'almacenamiento_gb' => 512, 'grafica' => 'integrada', 'sistema' => 'Windows 11', 'formato' => 'mini PC', 'ideal_para' => ['oficina', 'multimedia']]],
            ['sobremesa', 'Jokoa Oficina', 'PC montado en Jokoa para ofimática, estudios y teletrabajo.', '649.00', 6, 'Jokoa', '49.00', ['procesador' => 'AMD Ryzen 5 5600G', 'ram_gb' => 16, 'memoria' => 'Crucial 16 GB (2x8) DDR4-3200', 'almacenamiento_gb' => 512, 'almacenamiento' => 'Samsung PM9B1 512 GB NVMe', 'grafica' => 'AMD Radeon integrada (Vega 7)', 'placa' => 'Gigabyte A520M S2H', 'fuente' => 'Corsair CV450 450 W 80+ Bronze', 'caja' => 'Cooler Master MasterBox Q300L (micro-ATX)', 'refrigeracion' => 'AMD Wraith Stealth (incluido)', 'sistema' => 'Windows 11', 'ideal_para' => ['oficina', 'estudiar'], 'incluye' => 'montaje, instalación del sistema y pruebas de estabilidad']],
            ['sobremesa', 'Jokoa Creador', 'PC montado en Jokoa para edición de vídeo y diseño.', '1199.00', 4, 'Jokoa', '69.00', ['procesador' => 'AMD Ryzen 7 7700', 'ram_gb' => 32, 'memoria' => 'Kingston FURY Beast 32 GB (2x16) DDR5-5600', 'almacenamiento_gb' => 1024, 'almacenamiento' => 'Kingston KC3000 1 TB NVMe', 'grafica' => 'Gigabyte GeForce RTX 4060 WINDFORCE OC 8G', 'placa' => 'MSI MAG B650 TOMAHAWK WIFI', 'fuente' => 'be quiet! Pure Power 12 M 650 W 80+ Gold', 'caja' => 'Fractal Design North', 'refrigeracion' => 'Thermalright Peerless Assassin 120 SE', 'sistema' => 'Windows 11', 'ideal_para' => ['vídeo', 'diseño', 'programar'], 'incluye' => 'montaje, instalación del sistema y pruebas de estabilidad']],
            // ----- PC gaming montados en Jokoa -----
            ['pc_gaming', 'Jokoa Spark', 'PC gaming de entrada montado en Jokoa: Full HD a buenos fps.', '799.00', 5, 'Jokoa', '59.00', ['procesador' => 'AMD Ryzen 5 5600', 'ram_gb' => 16, 'memoria' => 'Corsair Vengeance LPX 16 GB (2x8) DDR4-3200', 'almacenamiento_gb' => 500, 'almacenamiento' => 'WD Blue SN570 500 GB NVMe', 'grafica' => 'Gigabyte GeForce RTX 3050 WINDFORCE OC 8G', 'placa' => 'MSI MAG B550 TOMAHAWK', 'fuente' => 'Corsair CX550 550 W 80+ Bronze', 'caja' => 'Corsair 4000D (cristal templado)', 'refrigeracion' => 'AMD Wraith Stealth (incluido)', 'sistema' => 'Windows 11', 'ideal_para' => ['juegos', 'precio ajustado'], 'incluye' => 'montaje, cableado ordenado, actualización de BIOS y pruebas de estabilidad']],
            ['pc_gaming', 'Jokoa Blaze', 'PC gaming equilibrado montado en Jokoa para jugar a todo en Full HD o 1440p.', '1199.00', 5, 'Jokoa', '69.00', ['procesador' => 'AMD Ryzen 5 7600', 'ram_gb' => 32, 'memoria' => 'Corsair Vengeance 32 GB (2x16) DDR5-5600', 'almacenamiento_gb' => 1024, 'almacenamiento' => 'WD Black SN770 1 TB NVMe', 'grafica' => 'MSI GeForce RTX 4060 Ti VENTUS 2X BLACK 8G', 'placa' => 'MSI PRO B650-S WIFI', 'fuente' => 'Corsair RM650 (2021) 650 W 80+ Gold', 'caja' => 'Cooler Master MasterBox TD500 Mesh (cristal templado, ARGB)', 'refrigeracion' => 'Thermalright Peerless Assassin 120 SE', 'sistema' => 'Windows 11', 'ideal_para' => ['juegos', 'streaming'], 'incluye' => 'montaje, cableado ordenado, actualización de BIOS y pruebas de estabilidad']],
            ['pc_gaming', 'Jokoa Storm', 'PC gaming potente montado en Jokoa para 1440p a altas tasas de fps.', '1699.00', 4, 'Jokoa', '79.00', ['procesador' => 'AMD Ryzen 7 7800X3D', 'ram_gb' => 32, 'memoria' => 'G.Skill Trident Z5 Neo RGB 32 GB (2x16) DDR5-6000 CL30', 'almacenamiento_gb' => 2048, 'almacenamiento' => 'Samsung 990 PRO 2 TB NVMe', 'grafica' => 'Gigabyte GeForce RTX 4070 SUPER WINDFORCE OC 12G', 'placa' => 'ASUS TUF GAMING B650-PLUS WIFI', 'fuente' => 'Corsair RM750e 750 W 80+ Gold', 'caja' => 'Lian Li LANCOOL 216', 'refrigeracion' => 'DeepCool AK620', 'sistema' => 'Windows 11', 'ideal_para' => ['juegos', 'competitivo', 'streaming'], 'incluye' => 'montaje, cableado ordenado, actualización de BIOS y pruebas de estabilidad']],
            ['pc_gaming', 'Jokoa Titan', 'PC gaming tope de gama montado en Jokoa para 4K.', '2899.00', 3, 'Jokoa', '99.00', ['procesador' => 'AMD Ryzen 9 9950X3D', 'ram_gb' => 64, 'memoria' => 'Corsair Vengeance RGB 64 GB (2x32) DDR5-6000 CL30', 'almacenamiento_gb' => 2048, 'almacenamiento' => 'Samsung 990 PRO 2 TB NVMe', 'grafica' => 'ASUS Prime GeForce RTX 5080 16GB OC', 'placa' => 'ASUS ROG STRIX X870-A GAMING WIFI', 'fuente' => 'Corsair HX1000i (2023) 1000 W 80+ Platinum', 'caja' => 'Lian Li O11 Dynamic EVO (cristal templado)', 'refrigeracion' => 'NZXT Kraken Elite 360 (AIO 360 mm)', 'sistema' => 'Windows 11', 'ideal_para' => ['juegos 4K', 'creación de contenido', 'streaming'], 'incluye' => 'montaje, refrigeración líquida, cableado ordenado y pruebas de estabilidad']],
            ['pc_gaming', 'Jokoa Compacto', 'PC gaming pequeño montado en Jokoa para quien tiene poco espacio.', '1399.00', 4, 'Jokoa', '79.00', ['procesador' => 'Intel Core i5-14600K', 'ram_gb' => 32, 'memoria' => 'Corsair Vengeance 32 GB (2x16) DDR5-5600', 'almacenamiento_gb' => 1024, 'almacenamiento' => 'WD Black SN770 1 TB NVMe', 'grafica' => 'ASUS Dual GeForce RTX 4060 Ti 8GB', 'placa' => 'ASUS ROG STRIX B760-I GAMING WIFI (mini-ITX)', 'fuente' => 'Cooler Master V650 SFX Gold 650 W', 'caja' => 'Cooler Master NR200P (mini-ITX)', 'refrigeracion' => 'Arctic Liquid Freezer III 240', 'sistema' => 'Windows 11', 'ideal_para' => ['juegos', 'poco espacio'], 'incluye' => 'montaje, cableado ordenado y pruebas de estabilidad']],
            // ----- Tablets -----
            ['tablet', 'iPad 11"', 'El iPad para todo: estudiar, ver series y dibujar.', '399.00', 8, 'Apple', '0.00', ['pantalla' => '11" Liquid Retina', 'almacenamiento_gb' => 128, 'sistema' => 'iPadOS', 'conectividad' => 'Wi-Fi', 'lapiz' => 'Apple Pencil (USB-C)', 'ideal_para' => ['estudiar', 'ver series', 'dibujar']]],
            ['tablet', 'iPad Air 11" M3', 'Potente y ligero, con soporte para Pencil Pro.', '699.00', 5, 'Apple', '0.00', ['pantalla' => '11" Liquid Retina', 'almacenamiento_gb' => 128, 'sistema' => 'iPadOS', 'conectividad' => 'Wi-Fi', 'lapiz' => 'Apple Pencil Pro', 'ideal_para' => ['dibujar', 'trabajar', 'juegos']]],
            ['tablet', 'iPad Pro 13" M4', 'La tablet más potente, con pantalla OLED.', '1299.00', 3, 'Apple', '0.00', ['pantalla' => '13" Ultra Retina XDR OLED', 'almacenamiento_gb' => 256, 'sistema' => 'iPadOS', 'conectividad' => 'Wi-Fi', 'lapiz' => 'Apple Pencil Pro', 'ideal_para' => ['diseño', 'vídeo', 'trabajar']]],
            ['tablet', 'iPad mini 8,3"', 'Pequeña y cómoda, para llevar a todas partes.', '549.00', 5, 'Apple', '0.00', ['pantalla' => '8,3" Liquid Retina', 'almacenamiento_gb' => 128, 'sistema' => 'iPadOS', 'conectividad' => 'Wi-Fi', 'lapiz' => 'Apple Pencil Pro', 'ideal_para' => ['leer', 'portabilidad']]],
            ['tablet', 'Galaxy Tab S10 FE', 'Tablet Android con S Pen incluido.', '499.00', 6, 'Samsung', '0.00', ['pantalla' => '10,9" LCD 90 Hz', 'almacenamiento_gb' => 128, 'sistema' => 'Android', 'conectividad' => 'Wi-Fi', 'lapiz' => 'S Pen incluido', 'ideal_para' => ['estudiar', 'apuntes', 'ver series']]],
            ['tablet', 'Galaxy Tab S10 Ultra', 'La pantalla más grande en una tablet Android.', '1199.00', 3, 'Samsung', '0.00', ['pantalla' => '14,6" AMOLED 120 Hz', 'almacenamiento_gb' => 256, 'sistema' => 'Android', 'conectividad' => 'Wi-Fi', 'lapiz' => 'S Pen incluido', 'ideal_para' => ['trabajar', 'diseño', 'ver series']]],
            ['tablet', 'Galaxy Tab A9+', 'Tablet económica para toda la familia.', '199.00', 10, 'Samsung', '0.00', ['pantalla' => '11" LCD 90 Hz', 'almacenamiento_gb' => 64, 'sistema' => 'Android', 'conectividad' => 'Wi-Fi', 'lapiz' => 'no incluido', 'ideal_para' => ['familia', 'ver series', 'precio ajustado']]],
            ['tablet', 'Lenovo Tab P12', 'Pantalla grande y buen sonido a precio medio.', '349.00', 5, 'Lenovo', '0.00', ['pantalla' => '12,7" 3K', 'almacenamiento_gb' => 128, 'sistema' => 'Android', 'conectividad' => 'Wi-Fi', 'lapiz' => 'Lenovo Tab Pen Plus incluido', 'ideal_para' => ['estudiar', 'ver series']]],
            ['tablet', 'Xiaomi Pad 7', 'Pantalla 3.2K a 144 Hz y mucha potencia por poco dinero.', '399.00', 6, 'Xiaomi', '0.00', ['pantalla' => '11,2" 3,2K 144 Hz', 'almacenamiento_gb' => 256, 'sistema' => 'Android', 'conectividad' => 'Wi-Fi', 'lapiz' => 'opcional', 'ideal_para' => ['juegos', 'ver series', 'precio ajustado']]],
            ['tablet', 'Amazon Fire HD 10', 'Tablet económica para leer y ver vídeos.', '149.00', 8, 'Amazon', '0.00', ['pantalla' => '10,1" Full HD', 'almacenamiento_gb' => 32, 'sistema' => 'Fire OS', 'conectividad' => 'Wi-Fi', 'lapiz' => 'no', 'ideal_para' => ['leer', 'niños', 'precio ajustado']]],
            // ----- Consolas -----
            ['consola', 'PlayStation 5 Slim (con lector)', 'La consola de Sony con lector de discos.', '549.99', 6, 'Sony', '0.00', ['almacenamiento' => '1 TB SSD', 'resolucion' => '4K hasta 120 fps', 'lector' => 'sí', 'incluye' => 'un mando DualSense', 'ideal_para' => ['juegos', 'exclusivos de Sony']]],
            ['consola', 'PlayStation 5 Digital', 'Sin lector, para comprar solo juegos digitales.', '449.99', 6, 'Sony', '0.00', ['almacenamiento' => '1 TB SSD', 'resolucion' => '4K hasta 120 fps', 'lector' => 'no', 'incluye' => 'un mando DualSense', 'ideal_para' => ['juegos digitales', 'precio ajustado']]],
            ['consola', 'PlayStation 5 Pro', 'La PS5 más potente: mejores gráficos y trazado de rayos.', '799.99', 3, 'Sony', '0.00', ['almacenamiento' => '2 TB SSD', 'resolucion' => '4K a 60 fps+, PSSR', 'lector' => 'no (opcional)', 'incluye' => 'un mando DualSense', 'ideal_para' => ['juegos', 'mejor calidad gráfica']]],
            ['consola', 'Xbox Series X', 'La Xbox más potente, con Game Pass.', '499.99', 5, 'Microsoft', '0.00', ['almacenamiento' => '1 TB SSD', 'resolucion' => '4K hasta 120 fps', 'lector' => 'sí', 'incluye' => 'un mando inalámbrico', 'ideal_para' => ['juegos', 'Game Pass']]],
            ['consola', 'Xbox Series S', 'Pequeña y económica, ideal con Game Pass.', '299.99', 8, 'Microsoft', '0.00', ['almacenamiento' => '512 GB SSD', 'resolucion' => '1440p hasta 120 fps', 'lector' => 'no', 'incluye' => 'un mando inalámbrico', 'ideal_para' => ['precio ajustado', 'Game Pass']]],
            ['consola', 'Nintendo Switch 2', 'La nueva consola híbrida de Nintendo.', '469.99', 7, 'Nintendo', '0.00', ['almacenamiento' => '256 GB', 'resolucion' => '4K en TV, 1080p portátil', 'lector' => 'cartuchos', 'incluye' => 'dos mandos Joy-Con 2', 'ideal_para' => ['familia', 'juego portátil']]],
            ['consola', 'Nintendo Switch OLED', 'Switch con pantalla OLED, portátil y de sobremesa.', '349.99', 6, 'Nintendo', '0.00', ['almacenamiento' => '64 GB', 'resolucion' => '1080p en TV, 720p portátil', 'lector' => 'cartuchos', 'incluye' => 'dos mandos Joy-Con', 'ideal_para' => ['familia', 'juego portátil']]],
            ['consola', 'Nintendo Switch Lite', 'Consola solo portátil, ligera y económica.', '219.99', 6, 'Nintendo', '0.00', ['almacenamiento' => '32 GB', 'resolucion' => '720p portátil', 'lector' => 'cartuchos', 'incluye' => 'la consola', 'ideal_para' => ['niños', 'portátil', 'precio ajustado']]],
            ['consola', 'Steam Deck OLED', 'PC portátil para jugar a tu biblioteca de Steam.', '569.00', 4, 'Valve', '0.00', ['almacenamiento' => '512 GB', 'resolucion' => '1280x800 OLED 90 Hz', 'lector' => 'no', 'incluye' => 'funda', 'ideal_para' => ['juegos de PC', 'portátil']]],
            ['consola', 'ASUS ROG Ally X', 'PC portátil con Windows para jugar donde quieras.', '799.00', 3, 'ASUS', '0.00', ['almacenamiento' => '1 TB', 'resolucion' => '1080p 120 Hz', 'lector' => 'no', 'incluye' => 'funda', 'ideal_para' => ['juegos de PC', 'portátil']]],
            // ----- Mandos -----
            ['mando', 'Mando DualSense (PS5)', 'El mando oficial de PS5 con gatillos adaptativos.', '69.99', 12, 'Sony', '0.00', ['compatible' => 'PlayStation 5, PC', 'conexion' => 'inalámbrico', 'extras' => 'gatillos adaptativos y vibración háptica', 'ideal_para' => ['juegos de PS5']]],
            ['mando', 'Mando DualSense Edge (PS5)', 'Mando pro de PS5 con botones traseros y ajustes.', '229.99', 4, 'Sony', '0.00', ['compatible' => 'PlayStation 5, PC', 'conexion' => 'inalámbrico', 'extras' => 'botones traseros, perfiles y palancas intercambiables', 'ideal_para' => ['competitivo']]],
            ['mando', 'Mando inalámbrico Xbox', 'Mando oficial de Xbox, también para PC.', '59.99', 12, 'Microsoft', '0.00', ['compatible' => 'Xbox, PC, móvil', 'conexion' => 'inalámbrico', 'extras' => 'pilas AA o batería', 'ideal_para' => ['juegos de Xbox', 'juegos de PC']]],
            ['mando', 'Nintendo Switch Pro Controller', 'Mando cómodo para jugar en la tele con Switch.', '64.99', 8, 'Nintendo', '0.00', ['compatible' => 'Nintendo Switch, PC', 'conexion' => 'inalámbrico', 'extras' => 'vibración HD y NFC', 'ideal_para' => ['juegos de Switch']]],
            ['mando', 'Joy-Con 2 (pareja)', 'Mandos pequeños de Switch 2, para jugar en pareja.', '89.99', 6, 'Nintendo', '0.00', ['compatible' => 'Nintendo Switch 2', 'conexion' => 'inalámbrico', 'extras' => 'modo ratón', 'ideal_para' => ['juegos en pareja']]],
            ['mando', 'Mando 8BitDo Ultimate 2', 'Mando versátil para PC, Switch y móvil.', '59.99', 8, '8BitDo', '0.00', ['compatible' => 'PC, Switch, Android', 'conexion' => 'inalámbrico 2,4 GHz', 'extras' => 'gatillos Hall, base de carga', 'ideal_para' => ['juegos de PC', 'precio ajustado']]],
            // ----- Auriculares -----
            ['auriculares', 'Sony Pulse 3D', 'Auriculares oficiales de PS5 con sonido 3D.', '99.99', 8, 'Sony', '0.00', ['tipo' => 'diadema inalámbrica', 'microfono' => 'doble integrado', 'compatible' => 'PlayStation 5, PS4, PC', 'bateria' => 'hasta 12 h', 'ideal_para' => ['juegos de PS5']]],
            ['auriculares', 'SteelSeries Arctis Nova 7', 'Cómodos y con sonido muy claro, inalámbricos.', '179.99', 7, 'SteelSeries', '0.00', ['tipo' => 'diadema inalámbrica', 'microfono' => 'retráctil', 'compatible' => 'PC, PlayStation, Switch', 'bateria' => 'hasta 38 h', 'ideal_para' => ['juegos', 'sesiones largas']]],
            ['auriculares', 'HyperX Cloud II', 'Clásico gaming con sonido envolvente 7.1.', '89.99', 10, 'HyperX', '0.00', ['tipo' => 'diadema con cable', 'microfono' => 'desmontable', 'compatible' => 'PC, consolas', 'bateria' => 'no aplica', 'ideal_para' => ['juegos', 'precio ajustado']]],
            ['auriculares', 'Logitech G Pro X 2', 'Auriculares de competición con sonido preciso.', '229.00', 5, 'Logitech', '0.00', ['tipo' => 'diadema inalámbrica', 'microfono' => 'con filtro de voz', 'compatible' => 'PC, PlayStation', 'bateria' => 'hasta 50 h', 'ideal_para' => ['competitivo', 'esports']]],
            ['auriculares', 'Razer BlackShark V2 X', 'Ligeros y con buen micrófono, con cable.', '59.99', 9, 'Razer', '0.00', ['tipo' => 'diadema con cable', 'microfono' => 'cardioide', 'compatible' => 'PC, consolas', 'bateria' => 'no aplica', 'ideal_para' => ['juegos', 'precio ajustado']]],
            ['auriculares', 'Corsair HS80 Max', 'Inalámbricos con gran autonomía y sonido espacial.', '149.99', 6, 'Corsair', '0.00', ['tipo' => 'diadema inalámbrica', 'microfono' => 'con modo silencio', 'compatible' => 'PC, PlayStation', 'bateria' => 'hasta 65 h', 'ideal_para' => ['juegos', 'sesiones largas']]],
            // ----- Teclados -----
            ['teclado', 'Logitech G915 TKL', 'Mecánico inalámbrico de perfil bajo.', '199.99', 5, 'Logitech', '0.00', ['formato' => 'TKL (sin teclado numérico)', 'tipo' => 'mecánico inalámbrico de perfil bajo', 'interruptores' => 'GL Tactile', 'iluminacion' => 'RGB', 'ideal_para' => ['juegos', 'trabajar']]],
            ['teclado', 'Razer BlackWidow V4', 'Mecánico completo con ruedas y teclas macro.', '189.99', 5, 'Razer', '0.00', ['formato' => 'completo', 'tipo' => 'mecánico con cable', 'interruptores' => 'Razer Green (clic)', 'iluminacion' => 'RGB', 'ideal_para' => ['juegos', 'macros']]],
            ['teclado', 'Corsair K70 RGB', 'Mecánico robusto y personalizable.', '149.99', 6, 'Corsair', '0.00', ['formato' => 'completo', 'tipo' => 'mecánico con cable', 'interruptores' => 'Cherry MX Red', 'iluminacion' => 'RGB', 'ideal_para' => ['juegos']]],
            ['teclado', 'Keychron K2', 'Compacto y silencioso, bueno para escribir y jugar.', '99.00', 7, 'Keychron', '0.00', ['formato' => '75 %', 'tipo' => 'mecánico inalámbrico', 'interruptores' => 'Gateron Brown', 'iluminacion' => 'RGB', 'ideal_para' => ['escribir', 'jugar', 'Mac y Windows']]],
            ['teclado', 'Logitech K380', 'Teclado inalámbrico compacto para móvil, tablet y PC.', '39.99', 12, 'Logitech', '0.00', ['formato' => 'compacto', 'tipo' => 'membrana inalámbrico', 'interruptores' => 'no aplica', 'iluminacion' => 'no', 'ideal_para' => ['tablet', 'trabajar', 'precio ajustado']]],
            // ----- Ratones -----
            ['raton', 'Logitech G502 X', 'Ratón gaming clásico con 13 botones.', '79.99', 8, 'Logitech', '0.00', ['sensor' => 'HERO 25K', 'conexion' => 'con cable', 'peso_g' => 89, 'dpi_max' => 25600, 'ideal_para' => ['juegos', 'MMO']]],
            ['raton', 'Logitech G Pro X Superlight 2', 'Ligerísimo y preciso, de esports.', '169.99', 5, 'Logitech', '0.00', ['sensor' => 'HERO 2', 'conexion' => 'inalámbrico', 'peso_g' => 60, 'dpi_max' => 44000, 'ideal_para' => ['competitivo', 'shooters']]],
            ['raton', 'Razer DeathAdder V3', 'Ergonómico para manos grandes.', '69.99', 8, 'Razer', '0.00', ['sensor' => 'Focus Pro 30K', 'conexion' => 'con cable', 'peso_g' => 59, 'dpi_max' => 30000, 'ideal_para' => ['juegos', 'shooters']]],
            ['raton', 'SteelSeries Rival 3', 'Ratón gaming económico y fiable.', '29.99', 12, 'SteelSeries', '0.00', ['sensor' => 'TrueMove Core', 'conexion' => 'con cable', 'peso_g' => 77, 'dpi_max' => 8500, 'ideal_para' => ['juegos', 'precio ajustado']]],
            ['raton', 'Logitech MX Master 3S', 'El ratón de oficina por excelencia, silencioso y cómodo.', '109.99', 6, 'Logitech', '0.00', ['sensor' => 'Darkfield 8K', 'conexion' => 'inalámbrico', 'peso_g' => 141, 'dpi_max' => 8000, 'ideal_para' => ['trabajar', 'programar']]],
            // ----- Monitores -----
            ['monitor', 'LG UltraGear 27" 1440p 180 Hz', 'Monitor gaming IPS rápido y con buen color.', '299.00', 6, 'LG', '0.00', ['tamano' => '27"', 'resolucion' => '2560x1440', 'frecuencia_hz' => 180, 'panel' => 'IPS', 'tiempo_respuesta' => '1 ms', 'ideal_para' => ['juegos', 'competitivo']]],
            ['monitor', 'Samsung Odyssey G5 32"', 'Curvo y envolvente para jugar a 165 Hz.', '279.00', 5, 'Samsung', '0.00', ['tamano' => '32"', 'resolucion' => '2560x1440', 'frecuencia_hz' => 165, 'panel' => 'VA curvo', 'tiempo_respuesta' => '1 ms', 'ideal_para' => ['juegos', 'inmersión']]],
            ['monitor', 'ASUS ROG Swift OLED 27"', 'OLED de 240 Hz para jugar al máximo.', '899.00', 3, 'ASUS', '0.00', ['tamano' => '27"', 'resolucion' => '2560x1440', 'frecuencia_hz' => 240, 'panel' => 'OLED', 'tiempo_respuesta' => '0,03 ms', 'ideal_para' => ['juegos', 'competitivo']]],
            ['monitor', 'Dell S2425H 24"', 'Monitor Full HD para trabajar y estudiar.', '129.00', 8, 'Dell', '0.00', ['tamano' => '24"', 'resolucion' => '1920x1080', 'frecuencia_hz' => 100, 'panel' => 'IPS', 'tiempo_respuesta' => '4 ms', 'ideal_para' => ['oficina', 'estudiar']]],
            ['monitor', 'AOC 24G2 24"', 'Monitor gaming de entrada a 144 Hz.', '149.00', 8, 'AOC', '0.00', ['tamano' => '24"', 'resolucion' => '1920x1080', 'frecuencia_hz' => 144, 'panel' => 'IPS', 'tiempo_respuesta' => '1 ms', 'ideal_para' => ['juegos', 'precio ajustado']]],
            ['monitor', 'LG 27UP850 27" 4K', 'Monitor 4K para trabajar con mucho detalle.', '449.00', 4, 'LG', '0.00', ['tamano' => '27"', 'resolucion' => '3840x2160', 'frecuencia_hz' => 60, 'panel' => 'IPS', 'tiempo_respuesta' => '5 ms', 'ideal_para' => ['diseño', 'vídeo', 'trabajar']]],
            // ----- Sillas -----
            ['silla', 'Secretlab Titan Evo', 'La silla gaming de referencia, con ajuste lumbar.', '549.00', 3, 'Secretlab', '0.00', ['material' => 'cuero sintético', 'peso_max_kg' => 130, 'reclinable' => 'hasta 165°', 'reposabrazos' => '4D', 'ideal_para' => ['sesiones largas', 'comodidad']]],
            ['silla', 'Razer Iskur V2', 'Silla ergonómica con soporte lumbar integrado.', '499.00', 3, 'Razer', '0.00', ['material' => 'cuero sintético', 'peso_max_kg' => 136, 'reclinable' => 'hasta 152°', 'reposabrazos' => '4D', 'ideal_para' => ['sesiones largas', 'ergonomía']]],
            ['silla', 'Corsair TC100 Relaxed', 'Silla cómoda y asequible, con tela transpirable.', '199.00', 5, 'Corsair', '0.00', ['material' => 'tela', 'peso_max_kg' => 120, 'reclinable' => 'hasta 180°', 'reposabrazos' => '2D', 'ideal_para' => ['precio ajustado', 'comodidad']]],
            ['silla', 'Newskill Banshee', 'Silla gaming económica con buen acolchado.', '149.00', 6, 'Newskill', '0.00', ['material' => 'cuero sintético', 'peso_max_kg' => 120, 'reclinable' => 'hasta 180°', 'reposabrazos' => '2D', 'ideal_para' => ['precio ajustado', 'estudiar']]],
        ];
    }
}
