<?php

namespace App\Command;

use App\Entity\Imagen;
use App\Entity\Movil;
use App\Entity\Plataforma;
use App\Entity\Producto;
use App\Entity\Videojuego;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// Rellena las características que lee el asesor de IA y añade juegos (RPG y gratuitos) y móviles al catálogo.
// Es idempotente: se puede repetir; solo crea lo que falta y reescribe las características de cada producto.
#[AsCommand(name: 'app:catalogo-asistente', description: 'Características de los productos y catálogo ampliado para el asesor de IA')]
class CatalogoAsistenteCommand extends Command
{
    private const IMAGENES = [
        'God of War Ragnarök' => 'god-of-war-ragnarok.jpg', "Marvel's Spider-Man 2" => 'spider-man-2.jpg', 'Astro Bot' => 'astro-bot.jpg',
        'Ghost of Yōtei' => 'ghost-of-yotei.png', 'Halo Infinite' => 'halo-infinite.png', 'Forza Motorsport' => 'forza-motorsport.png',
        'Starfield' => 'starfield.png', 'Avowed' => 'avowed.jpg', 'Call of Duty: Black Ops 6' => 'cod-black-ops-6.png',
        'Super Mario Odyssey' => 'super-mario-odyssey.jpg', 'The Legend of Zelda: Tears of the Kingdom' => 'zelda-tears-of-the-kingdom.jpg',
        'Pokémon Legends: Z-A' => 'pokemon-legends-za.png', 'Metroid Prime 4: Beyond' => 'metroid-prime-4.png', 'Cyberpunk 2077' => 'cyberpunk-2077.jpg',
        'Hades' => 'hades.jpg', 'Stardew Valley' => 'stardew-valley.png', 'Red Dead Redemption 2' => 'red-dead-redemption-2.jpg',
        "Sid Meier's Civilization VI" => 'civilization-vi.jpg', 'The Witcher 3: Wild Hunt' => 'the-witcher-3.jpg', "Baldur's Gate 3" => 'baldurs-gate-3.jpg',
        'Persona 5 Royal' => 'persona-5-royal.jpg', 'Xenoblade Chronicles X' => 'xenoblade-chronicles-x.jpg',
        'iPhone 16' => 'iphone-16.png', 'iPhone 16 Pro' => 'iphone-16-pro.png', 'iPhone 16e' => 'iphone-16e.png', 'Pixel 9a' => 'pixel-9a.png',
        'OnePlus 13' => 'oneplus-13.jpg', 'Xiaomi 15T Pro' => 'xiaomi-15t-pro.jpg', 'Galaxy A55' => 'galaxy-a55.jpg', 'Galaxy A56' => 'galaxy-a56.jpg',
        'Moto G54 5G' => 'moto-g54-5g.jpg', 'Nothing Phone (3a) Pro' => 'nothing-phone-3a-pro.jpg', 'Motorola Edge 60 Pro' => 'motorola-edge-60-pro.jpg',
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // nombre => características (juegos: subgénero, jugadores, online, duración, dificultad, idioma, etiquetas)
        $caracteristicas = [
            'Mario Kart World' => ['subgenero' => 'carreras arcade', 'jugadores' => '1-4 (hasta 24 online)', 'online' => true, 'duracion' => 'sin fin', 'dificultad' => 'baja', 'idioma' => 'español', 'etiquetas' => ['familiar', 'multijugador', 'competitivo']],
            'Donkey Kong Bananza' => ['subgenero' => 'plataformas 3D', 'jugadores' => '1-2', 'online' => false, 'duracion' => '15-20 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['familiar', 'aventura', 'cooperativo']],
            'EA Sports FC 26' => ['subgenero' => 'fútbol', 'jugadores' => '1-4', 'online' => true, 'duracion' => 'sin fin', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['deportes', 'competitivo', 'multijugador']],
            'Gran Turismo 7' => ['subgenero' => 'simulación de conducción', 'jugadores' => '1-2 (hasta 20 online)', 'online' => true, 'duracion' => '40+ h', 'dificultad' => 'media-alta', 'idioma' => 'español', 'etiquetas' => ['coches', 'realista']],
            'Elden Ring' => ['subgenero' => 'rol de acción souls-like', 'jugadores' => '1 (cooperativo online)', 'online' => true, 'duracion' => '60-100 h', 'dificultad' => 'alta', 'idioma' => 'español', 'etiquetas' => ['rpg', 'mundo abierto', 'fantasía oscura', 'desafiante']],
            'Forza Horizon 5' => ['subgenero' => 'carreras en mundo abierto', 'jugadores' => '1-12 online', 'online' => true, 'duracion' => '30+ h', 'dificultad' => 'baja', 'idioma' => 'español', 'etiquetas' => ['coches', 'mundo abierto', 'relajado']],
            'Minecraft' => ['subgenero' => 'construcción y supervivencia', 'jugadores' => '1-8', 'online' => true, 'duracion' => 'sin fin', 'dificultad' => 'baja', 'idioma' => 'español', 'etiquetas' => ['creativo', 'familiar', 'cooperativo']],
            'Hollow Knight: Silksong' => ['subgenero' => 'metroidvania', 'jugadores' => '1', 'online' => false, 'duracion' => '25-40 h', 'dificultad' => 'alta', 'idioma' => 'español', 'etiquetas' => ['2D', 'aventura', 'desafiante']],
            'Galaxy S25' => ['pantalla' => '6,2" AMOLED 120 Hz', 'camara' => 'triple 50 MP', 'bateria' => 'dura un día completo', 'sistema' => 'Android', 'cinco_g' => true, 'gama' => 'alta', 'ideal_para' => ['fotografía', 'uso diario', 'juegos']],
            'iPhone 17' => ['pantalla' => '6,3" OLED 120 Hz', 'camara' => 'doble 48 MP', 'bateria' => 'dura un día completo', 'sistema' => 'iOS', 'cinco_g' => true, 'gama' => 'alta', 'ideal_para' => ['fotografía', 'vídeo', 'uso diario']],
            'Pixel 10' => ['pantalla' => '6,3" OLED 120 Hz', 'camara' => 'triple, muy buena en foto nocturna', 'bateria' => 'dura un día completo', 'sistema' => 'Android puro, 7 años de actualizaciones', 'cinco_g' => true, 'gama' => 'alta', 'ideal_para' => ['fotografía', 'inteligencia artificial', 'durar años']],
            'Redmi Note 14 Pro' => ['pantalla' => '6,67" AMOLED 120 Hz', 'camara' => '200 MP', 'bateria' => 'dura más de un día, carga rápida', 'sistema' => 'Android', 'cinco_g' => true, 'gama' => 'media', 'ideal_para' => ['precio ajustado', 'fotografía', 'uso diario']],
        ];

        // Productos nuevos: [nombre, descripción, precio, stock, plataforma, género, PEGI, alquiler/día, características]
        $juegos = [
            ['The Witcher 3: Wild Hunt', 'Rol de mundo abierto con una de las mejores historias del género.', '24.99', 6, 'PC', 'Rol', 18, '1.50', ['subgenero' => 'rol de fantasía con historia', 'jugadores' => '1', 'online' => false, 'duracion' => '80-120 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['rpg', 'mundo abierto', 'historia']]],
            ['Baldur\'s Gate 3', 'Rol por turnos con decisiones que cambian la historia, solo o en cooperativo.', '59.99', 4, 'PC', 'Rol', 18, '2.50', ['subgenero' => 'rol táctico por turnos', 'jugadores' => '1-4', 'online' => true, 'duracion' => '80-150 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['rpg', 'cooperativo', 'historia', 'fantasía']]],
            ['Persona 5 Royal', 'Rol japonés: instituto de día y robos de corazones de noche.', '39.99', 5, 'PlayStation 5', 'Rol', 16, '2.00', ['subgenero' => 'JRPG por turnos', 'jugadores' => '1', 'online' => false, 'duracion' => '100 h', 'dificultad' => 'media', 'idioma' => 'inglés con subtítulos en español', 'etiquetas' => ['rpg', 'historia', 'anime']]],
            ['Xenoblade Chronicles X', 'Rol de acción en un planeta alienígena enorme para explorar.', '59.99', 4, 'Nintendo Switch 2', 'Rol', 12, '2.50', ['subgenero' => 'JRPG de acción y mundo abierto', 'jugadores' => '1 (online opcional)', 'online' => true, 'duracion' => '70-100 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['rpg', 'mundo abierto', 'ciencia ficción']]],
            ['God of War Ragnarök', 'Kratos y Atreus afrontan el fin del mundo en una aventura de acción mitológica.', '49.99', 6, 'PlayStation 5', 'Acción-aventura', 18, '2.50', ['subgenero' => 'acción y aventura', 'jugadores' => '1', 'online' => false, 'duracion' => '25-35 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['historia', 'mitología', 'acción']]],
            ['Marvel\'s Spider-Man 2', 'Peter y Miles Morales protegen Nueva York en un mundo abierto lleno de acción.', '59.99', 6, 'PlayStation 5', 'Acción', 16, '2.50', ['subgenero' => 'superhéroes de mundo abierto', 'jugadores' => '1', 'online' => false, 'duracion' => '20-25 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['mundo abierto', 'superhéroes', 'historia']]],
            ['Astro Bot', 'Plataformas para toda la familia con un robot muy carismático.', '59.99', 7, 'PlayStation 5', 'Plataformas', 7, '2.50', ['subgenero' => 'plataformas 3D', 'jugadores' => '1', 'online' => false, 'duracion' => '12-15 h', 'dificultad' => 'baja', 'idioma' => 'español', 'etiquetas' => ['familiar', 'plataformas', 'divertido']]],
            ['Ghost of Yōtei', 'Samurái en el Japón feudal: venganza y combates de espada en mundo abierto.', '79.99', 5, 'PlayStation 5', 'Acción-aventura', 18, '3.00', ['subgenero' => 'acción de samuráis', 'jugadores' => '1', 'online' => false, 'duracion' => '30-40 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['mundo abierto', 'historia', 'acción']]],
            ['Halo Infinite', 'El Jefe Maestro vuelve en un shooter con campaña abierta y multijugador.', '29.99', 8, 'Xbox Series X|S', 'Disparos', 16, '1.50', ['subgenero' => 'shooter en primera persona', 'jugadores' => '1-24 online', 'online' => true, 'duracion' => '12-15 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['shooter', 'multijugador', 'ciencia ficción']]],
            ['Forza Motorsport', 'Simulador de circuitos con coches y pistas de alta fidelidad.', '39.99', 5, 'Xbox Series X|S', 'Conducción', 3, '2.00', ['subgenero' => 'simulación de circuitos', 'jugadores' => '1-24 online', 'online' => true, 'duracion' => 'sin fin', 'dificultad' => 'media-alta', 'idioma' => 'español', 'etiquetas' => ['coches', 'realista', 'competitivo']]],
            ['Starfield', 'Rol espacial: explora cientos de planetas y crea tu propia nave.', '49.99', 5, 'Xbox Series X|S', 'Rol', 18, '2.50', ['subgenero' => 'rol espacial de mundo abierto', 'jugadores' => '1', 'online' => false, 'duracion' => '60-100 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['rpg', 'mundo abierto', 'ciencia ficción']]],
            ['Avowed', 'Rol en primera persona en un mundo de fantasía con combates y magia.', '59.99', 4, 'Xbox Series X|S', 'Rol', 16, '2.50', ['subgenero' => 'rol de acción en primera persona', 'jugadores' => '1', 'online' => false, 'duracion' => '25-40 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['rpg', 'fantasía', 'acción']]],
            ['Call of Duty: Black Ops 6', 'Shooter bélico con campaña, multijugador y zombis.', '69.99', 9, 'Xbox Series X|S', 'Disparos', 18, '3.00', ['subgenero' => 'shooter bélico', 'jugadores' => '1-12 online', 'online' => true, 'duracion' => 'sin fin', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['shooter', 'multijugador', 'competitivo']]],
            ['Super Mario Odyssey', 'Mario viaja por reinos de todo tipo con su gorra Cappy.', '49.99', 7, 'Nintendo Switch 2', 'Plataformas', 7, '2.00', ['subgenero' => 'plataformas 3D', 'jugadores' => '1-2', 'online' => false, 'duracion' => '12-20 h', 'dificultad' => 'baja', 'idioma' => 'español', 'etiquetas' => ['familiar', 'plataformas', 'cooperativo']]],
            ['The Legend of Zelda: Tears of the Kingdom', 'Aventura en mundo abierto con libertad total para construir y explorar.', '69.99', 6, 'Nintendo Switch 2', 'Aventura', 12, '3.00', ['subgenero' => 'aventura de mundo abierto', 'jugadores' => '1', 'online' => false, 'duracion' => '70-100 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['mundo abierto', 'aventura', 'creativo']]],
            ['Pokémon Legends: Z-A', 'Captura y combate en la ciudad de Luminalia con un sistema de batalla nuevo.', '69.99', 8, 'Nintendo Switch 2', 'Rol', 7, '3.00', ['subgenero' => 'rol de monstruos', 'jugadores' => '1', 'online' => false, 'duracion' => '30-40 h', 'dificultad' => 'baja', 'idioma' => 'español', 'etiquetas' => ['pokémon', 'familiar', 'aventura']]],
            ['Metroid Prime 4: Beyond', 'Samus explora planetas alienígenas en primera persona.', '69.99', 4, 'Nintendo Switch 2', 'Acción-aventura', 12, '3.00', ['subgenero' => 'aventura en primera persona', 'jugadores' => '1', 'online' => false, 'duracion' => '15-20 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['exploración', 'ciencia ficción', 'acción']]],
            ['Cyberpunk 2077', 'Rol de acción en Night City, una megaurbe futurista.', '29.99', 7, 'PC', 'Rol', 18, '1.50', ['subgenero' => 'rol de acción futurista', 'jugadores' => '1', 'online' => false, 'duracion' => '40-80 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['rpg', 'mundo abierto', 'ciencia ficción']]],
            ['Hades', 'Acción roguelike: escapa del inframundo griego una y otra vez.', '24.99', 6, 'PC', 'Acción', 12, '1.00', ['subgenero' => 'roguelike de acción', 'jugadores' => '1', 'online' => false, 'duracion' => '20-40 h', 'dificultad' => 'media-alta', 'idioma' => 'español', 'etiquetas' => ['roguelike', 'mitología', 'desafiante']]],
            ['Stardew Valley', 'Simulador de granja relajante: cultiva, pesca y haz amigos.', '13.99', 10, 'PC', 'Simulación', 7, '0.80', ['subgenero' => 'simulación de granja', 'jugadores' => '1-4', 'online' => true, 'duracion' => 'sin fin', 'dificultad' => 'baja', 'idioma' => 'español', 'etiquetas' => ['relajado', 'creativo', 'cooperativo']]],
            ['Red Dead Redemption 2', 'Western de mundo abierto con una historia memorable.', '29.99', 5, 'PC', 'Acción-aventura', 18, '1.50', ['subgenero' => 'western de mundo abierto', 'jugadores' => '1', 'online' => false, 'duracion' => '50-70 h', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['mundo abierto', 'historia', 'western']]],
            ['Sid Meier\'s Civilization VI', 'Estrategia por turnos para construir un imperio desde la prehistoria.', '19.99', 6, 'PC', 'Estrategia', 12, '1.00', ['subgenero' => 'estrategia por turnos', 'jugadores' => '1-8', 'online' => true, 'duracion' => 'sin fin', 'dificultad' => 'media', 'idioma' => 'español', 'etiquetas' => ['estrategia', 'historia', 'por turnos']]],
        ];
        $moviles = [
            ['Galaxy A56', 'Gama media con pantalla AMOLED y buenas cámaras.', '449.00', 6, 'Samsung', 256, 8, ['pantalla' => '6,7" AMOLED 120 Hz', 'camara' => 'triple 50 MP', 'bateria' => 'dura más de un día', 'sistema' => 'Android', 'cinco_g' => true, 'gama' => 'media', 'ideal_para' => ['uso diario', 'fotografía', 'relación calidad-precio']]],
            ['Moto G54 5G', 'Móvil básico y fiable de Motorola con 5G y batería grande.', '199.00', 10, 'Motorola', 128, 8, ['pantalla' => '6,5" LCD 120 Hz', 'camara' => 'doble 50 MP', 'bateria' => 'dura casi dos días', 'sistema' => 'Android casi puro', 'cinco_g' => true, 'gama' => 'económica', 'ideal_para' => ['precio ajustado', 'batería', 'primer móvil']]],
            ['Nothing Phone (3a) Pro', 'Diseño transparente diferente, con zoom 3x y software limpio.', '449.00', 4, 'Nothing', 256, 12, ['pantalla' => '6,77" AMOLED 120 Hz', 'camara' => 'triple 50 MP con zoom periscópico 3x', 'bateria' => 'dura un día y medio', 'sistema' => 'Android personalizado', 'cinco_g' => true, 'gama' => 'media', 'ideal_para' => ['diseño', 'fotografía', 'uso diario']]],
            ['Motorola Edge 60 Pro', 'Gama media-alta con pantalla curva, zoom 3x y carga muy rápida.', '599.00', 5, 'Motorola', 512, 12, ['pantalla' => '6,7" pOLED 120 Hz', 'camara' => 'triple 50 MP con zoom 3x', 'bateria' => 'dura más de un día, carga muy rápida', 'sistema' => 'Android casi puro', 'cinco_g' => true, 'gama' => 'media-alta', 'ideal_para' => ['fotografía', 'diseño', 'carga rápida']]],
            ['iPhone 16', 'Cámara de 48 MP, botón de acción y chip rápido, en un tamaño cómodo.', '859.00', 5, 'Apple', 128, 8, ['pantalla' => '6,1" OLED', 'camara' => 'doble 48 MP', 'bateria' => 'dura más de un día', 'sistema' => 'iOS', 'cinco_g' => true, 'gama' => 'alta', 'ideal_para' => ['uso diario', 'fotografía', 'durar años']]],
            ['iPhone 16 Pro', 'Titanio, cámara con zoom 5x y pantalla de 120 Hz.', '1119.00', 3, 'Apple', 256, 8, ['pantalla' => '6,3" OLED 120 Hz', 'camara' => 'triple 48 MP con zoom 5x', 'bateria' => 'dura más de un día', 'sistema' => 'iOS', 'cinco_g' => true, 'gama' => 'alta', 'ideal_para' => ['vídeo', 'fotografía', 'rendimiento']]],
            ['iPhone 16e', 'El iPhone más asequible, con chip actual y buena batería.', '719.00', 5, 'Apple', 128, 8, ['pantalla' => '6,1" OLED', 'camara' => 'sencilla 48 MP', 'bateria' => 'dura más de un día', 'sistema' => 'iOS', 'cinco_g' => true, 'gama' => 'media-alta', 'ideal_para' => ['iOS barato', 'uso diario', 'durar años']]],
            ['Pixel 9a', 'Gama media de Google con muy buena cámara y software limpio.', '549.00', 5, 'Google', 128, 8, ['pantalla' => '6,3" OLED 120 Hz', 'camara' => 'doble 48 MP', 'bateria' => 'dura más de un día', 'sistema' => 'Android puro', 'cinco_g' => true, 'gama' => 'media', 'ideal_para' => ['fotografía', 'uso diario', 'precio ajustado']]],
            ['OnePlus 13', 'Rendimiento de gama alta, carga muy rápida y batería enorme.', '899.00', 4, 'OnePlus', 256, 12, ['pantalla' => '6,82" AMOLED 120 Hz', 'camara' => 'triple 50 MP Hasselblad', 'bateria' => 'dura casi dos días, carga rápida', 'sistema' => 'Android', 'cinco_g' => true, 'gama' => 'alta', 'ideal_para' => ['rendimiento', 'batería', 'juegos']]],
            ['Xiaomi 15T Pro', 'Cámaras Leica y mucha potencia por debajo del precio de un tope de gama.', '799.00', 4, 'Xiaomi', 512, 12, ['pantalla' => '6,83" AMOLED 144 Hz', 'camara' => 'triple 50 MP Leica', 'bateria' => 'dura más de un día, carga muy rápida', 'sistema' => 'Android', 'cinco_g' => true, 'gama' => 'alta', 'ideal_para' => ['fotografía', 'rendimiento', 'relación calidad-precio']]],
            ['Galaxy A55', 'Gama media de Samsung con buen acabado y años de actualizaciones.', '399.00', 6, 'Samsung', 128, 8, ['pantalla' => '6,6" AMOLED 120 Hz', 'camara' => 'triple 50 MP', 'bateria' => 'dura más de un día', 'sistema' => 'Android', 'cinco_g' => true, 'gama' => 'media', 'ideal_para' => ['uso diario', 'fotografía', 'relación calidad-precio']]],
        ];

        $repo = $this->em->getRepository(Producto::class);
        $plataformas = [];
        foreach ($this->em->getRepository(Plataforma::class)->findAll() as $p) {
            $plataformas[$p->getNombre()] = $p;
        }

        $nuevos = 0;
        foreach ($juegos as [$nombre, $descripcion, $precio, $stock, $plataforma, $genero, $pegi, $alquiler, $car]) {
            if (!$repo->findOneBy(['nombre' => $nombre])) {
                $this->em->persist((new Videojuego())
                    ->setProducto($this->producto($nombre, $descripcion, $precio, $stock))
                    ->setPlataforma($plataformas[$plataforma])
                    ->setGenero($genero)->setPegi($pegi)->setPrecioAlquilerDia($alquiler));
                ++$nuevos;
            }
            $caracteristicas[$nombre] = $car;
        }
        foreach ($moviles as [$nombre, $descripcion, $precio, $stock, $marca, $almacenamiento, $ram, $car]) {
            if (!$repo->findOneBy(['nombre' => $nombre])) {
                $this->em->persist((new Movil())
                    ->setProducto($this->producto($nombre, $descripcion, $precio, $stock))
                    ->setMarca($marca)->setAlmacenamientoGb($almacenamiento)->setRamGb($ram));
                ++$nuevos;
            }
            $caracteristicas[$nombre] = $car;
        }
        $this->em->flush();

        $actualizados = 0;
        foreach ($caracteristicas as $nombre => $car) {
            if ($producto = $repo->findOneBy(['nombre' => $nombre])) {
                $producto->setCaracteristicas($car);
                ++$actualizados;
            }
        }
        // Portadas y fotos: api/public/img/juegos/<archivo>
        foreach (self::IMAGENES as $nombre => $archivo) {
            if ($imagen = $repo->findOneBy(['nombre' => $nombre])?->getImagenes()->first()) {
                $imagen->setUrl('https://jokoa.duckdns.org/img/juegos/'.$archivo);
            }
        }
        $this->em->flush();

        // Se retiran los gratuitos y los móviles sustituidos por otros con foto (si algún pedido los usa, se quedan)
        $retirados = 0;
        foreach (['Genshin Impact', 'Fortnite', 'Rocket League', 'Moto G85', 'Nothing Phone (3a)', 'Poco X7 Pro'] as $nombre) {
            try {
                $retirados += $this->em->getConnection()->executeStatement('DELETE FROM producto WHERE nombre = ?', [$nombre]);
            } catch (ForeignKeyConstraintViolationException) {
                $output->writeln("$nombre está en algún pedido: no se retira.");
            }
        }

        $output->writeln("Productos nuevos: $nuevos. Con características: $actualizados. Retirados: $retirados.");

        return Command::SUCCESS;
    }

    private function producto(string $nombre, string $descripcion, string $precio, int $stock): Producto
    {
        return (new Producto($nombre, $descripcion, $precio, $stock))
            ->addImagen((new Imagen())->setUrl('https://placehold.co/600x400/png?text='.urlencode($nombre))->setOrden(1));
    }
}
