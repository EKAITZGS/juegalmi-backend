<?php

namespace App\DataFixtures;

use App\Entity\Alquiler;
use App\Entity\Compra;
use App\Entity\Departamento;
use App\Entity\Imagen;
use App\Entity\LineaCompra;
use App\Entity\Movil;
use App\Entity\Plataforma;
use App\Entity\Producto;
use App\Entity\Ticket;
use App\Entity\Tienda;
use App\Entity\Ubicacion;
use App\Entity\Usuario;
use App\Entity\Videojuego;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

// Datos de ejemplo para la demo: php bin/console doctrine:fixtures:load (BORRA lo que haya antes)
class AppFixtures extends Fixture
{
    // Contraseña de las cuentas de demostración; no es la de ningún servidor
    private const CLAVE_DEMO = 'demo1234';

    public function load(ObjectManager $manager): void
    {
        $plataformas = [];
        foreach (['PlayStation 5', 'Xbox Series X|S', 'Nintendo Switch 2', 'PC'] as $nombre) {
            $manager->persist($plataformas[$nombre] = (new Plataforma())->setNombre($nombre));
        }

        $departamentos = [];
        foreach (['Telefonía', 'Consolas'] as $nombre) {
            $manager->persist($departamentos[$nombre] = (new Departamento())->setNombre($nombre));
        }

        foreach ([
            ['Jokoa Deusto', 'Av. Lehendakari Aguirre 29, Bilbao', '944 000 101', 'L-S 10:00-21:00', '43.271100', '-2.945900'],
            ['Jokoa Casco Viejo', 'Calle Somera 12, Bilbao', '944 000 102', 'L-S 10:00-21:30', '43.257000', '-2.923200'],
            ['Jokoa Barakaldo', 'Plaza Herriko 5, Barakaldo', '944 000 103', 'L-S 10:00-21:00', '43.296700', '-2.989400'],
            ['Jokoa Getxo', 'Calle Ibaiondo 8, Getxo', '944 000 104', 'L-S 10:00-20:30', '43.349600', '-3.010000'],
            ['Jokoa Leioa', 'Av. Sarriena 3, Leioa', '944 000 105', 'L-V 10:00-20:00', '43.327300', '-2.976000'],
        ] as [$nombre, $direccion, $telefono, $horario, $latitud, $longitud]) {
            $manager->persist((new Tienda())
                ->setNombre($nombre)
                ->setDireccion($direccion)
                ->setTelefono($telefono)
                ->setHorario($horario)
                ->setLatitud($latitud)
                ->setLongitud($longitud));
        }

        $usuarios = [];
        foreach ([
            ['admin@juegalmi.com', 'Admin', 'JuegAlmi', null, ['ROLE_ADMIN']],
            ['ane@juegalmi.com', 'Ane', 'Etxeberria Goikoetxea', '600111222', ['ROLE_USER']],
            ['jon@juegalmi.com', 'Jon', 'Agirre Zubiaurre', '600333444', ['ROLE_USER']],
        ] as [$email, $nombre, $apellidos, $telefono, $roles]) {
            $usuarios[$nombre] = (new Usuario())
                ->setEmail($email)
                ->setPassword(password_hash(self::CLAVE_DEMO, PASSWORD_BCRYPT))
                ->setNombre($nombre)
                ->setApellidos($apellidos)
                ->setTelefono($telefono)
                ->setRoles($roles);
            $manager->persist($usuarios[$nombre]);
        }

        $juegos = [];
        $n = 0; // las portadas están en api/public/img/juegos/<n>.jpg (portadas oficiales y fotos de producto, ids 1-12)
        foreach ([
            ['Mario Kart World', 'Carreras de karts en un mundo abierto, para jugar en familia.', '79.99', 6, 'Nintendo Switch 2', 'Carreras', 3, '3.50'],
            ['Donkey Kong Bananza', 'Plataformas en 3D: destruye el escenario para avanzar.', '69.99', 4, 'Nintendo Switch 2', 'Plataformas', 7, '3.00'],
            ['EA Sports FC 26', 'Fútbol con las ligas y equipos oficiales.', '69.99', 10, 'PlayStation 5', 'Deportes', 3, '3.00'],
            ['Gran Turismo 7', 'Simulador de conducción con más de 400 coches.', '59.99', 4, 'PlayStation 5', 'Conducción', 3, '2.50'],
            ['Elden Ring', 'Rol de acción en un mundo abierto de fantasía oscura.', '39.99', 3, 'PlayStation 5', 'Rol', 16, '2.00'],
            ['Forza Horizon 5', 'Carreras en mundo abierto por México.', '49.99', 5, 'Xbox Series X|S', 'Conducción', 3, '2.50'],
            ['Minecraft', 'Construye y explora mundos hechos de bloques.', '29.99', 8, 'PC', 'Sandbox', 7, '1.50'],
            ['Hollow Knight: Silksong', 'Aventura de acción en 2D con Hornet como protagonista.', '19.99', 5, 'PC', 'Aventura', 7, '1.00'],
        ] as [$nombre, $descripcion, $precio, $stock, $plataforma, $genero, $pegi, $alquilerDia]) {
            $juegos[$nombre] = (new Videojuego())
                ->setProducto($this->producto($nombre, $descripcion, $precio, $stock, 'https://jokoa.duckdns.org/img/juegos/'.++$n.'.jpg'))
                ->setPlataforma($plataformas[$plataforma])
                ->setGenero($genero)
                ->setPegi($pegi)
                ->setPrecioAlquilerDia($alquilerDia);
            $manager->persist($juegos[$nombre]);
        }

        $moviles = [];
        foreach ([
            ['Galaxy S25', 'Pantalla AMOLED de 6,2 pulgadas y triple cámara.', '899.00', 5, 'Samsung', 256, 12],
            ['iPhone 17', 'Pantalla de 6,3 pulgadas y chip de última generación.', '959.00', 4, 'Apple', 256, 8],
            ['Pixel 10', 'Android puro con siete años de actualizaciones.', '799.00', 3, 'Google', 128, 12],
            ['Redmi Note 14 Pro', 'Gama media con cámara de 200 MP y carga rápida.', '349.00', 8, 'Xiaomi', 256, 8],
        ] as [$nombre, $descripcion, $precio, $stock, $marca, $almacenamiento, $ram]) {
            $moviles[$nombre] = (new Movil())
                ->setProducto($this->producto($nombre, $descripcion, $precio, $stock, 'https://jokoa.duckdns.org/img/juegos/'.++$n.'.jpg'))
                ->setMarca($marca)
                ->setAlmacenamientoGb($almacenamiento)
                ->setRamGb($ram);
            $manager->persist($moviles[$nombre]);
        }

        $manager->persist((new Compra())
            ->setUsuario($usuarios['Ane'])
            ->setFecha(new \DateTime('-10 days'))
            ->addLinea($this->linea($moviles['Pixel 10']->getProducto(), 1))
            ->addLinea($this->linea($juegos['Minecraft']->getProducto(), 1)));
        $manager->persist((new Compra())
            ->setUsuario($usuarios['Jon'])
            ->setFecha(new \DateTime('-3 days'))
            ->addLinea($this->linea($juegos['EA Sports FC 26']->getProducto(), 2)));

        // Un alquiler ya devuelto y otro en curso
        $manager->persist((new Alquiler())
            ->setUsuario($usuarios['Ane'])
            ->setVideojuego($juegos['Mario Kart World'])
            ->setFechaInicio(new \DateTime('-14 days'))
            ->setFechaFin(new \DateTime('-7 days'))
            ->setFechaDevolucion(new \DateTime('-8 days'))
            ->setPrecioDia($juegos['Mario Kart World']->getPrecioAlquilerDia()));
        $manager->persist((new Alquiler())
            ->setUsuario($usuarios['Jon'])
            ->setVideojuego($juegos['Elden Ring'])
            ->setFechaInicio(new \DateTime('-2 days'))
            ->setFechaFin(new \DateTime('+5 days'))
            ->setPrecioDia($juegos['Elden Ring']->getPrecioAlquilerDia()));

        foreach ([
            ['Ane', 'Telefonía', 'La pantalla táctil no responde', 'Desde una caída, la mitad inferior de la pantalla no reacciona.', 'Samsung Galaxy S23', 'en_proceso', '-4 days', null],
            ['Jon', 'Consolas', 'El mando se mueve solo (drift)', 'El joystick izquierdo se desplaza sin tocarlo.', 'Mando DualSense de PlayStation 5', 'cerrado', '-6 days', '-1 day'],
            ['Ane', 'Consolas', 'La consola no lee los discos', 'Hace ruido al meter un disco y no lo reconoce.', 'Xbox Series X', 'abierto', '-1 day', null],
        ] as [$usuario, $departamento, $asunto, $descripcion, $dispositivo, $estado, $apertura, $cierre]) {
            $manager->persist((new Ticket())
                ->setUsuario($usuarios[$usuario])
                ->setDepartamento($departamentos[$departamento])
                ->setAsunto($asunto)
                ->setDescripcion($descripcion)
                ->setDispositivo($dispositivo)
                ->setEstado($estado)
                ->setFechaApertura(new \DateTime($apertura))
                ->setFechaCierre($cierre ? new \DateTime($cierre) : null));
        }

        // Ubicaciones en Bilbao (el centro ALMI está en Deusto)
        foreach ([
            ['Ane', '43.270900', '-2.946800', 'GET /ws/moviles', '-10 days'],
            ['Ane', '43.270900', '-2.946800', 'POST /ws/compra', '-10 days'],
            ['Jon', '43.263000', '-2.935000', 'POST /ws/alquiler', '-2 days'],
            ['Jon', '43.256900', '-2.923400', 'GET /ws/videojuegos', '-1 day'],
        ] as [$usuario, $latitud, $longitud, $accion, $fecha]) {
            $manager->persist((new Ubicacion())
                ->setUsuario($usuarios[$usuario])
                ->setLatitud($latitud)
                ->setLongitud($longitud)
                ->setAccion($accion)
                ->setFecha(new \DateTime($fecha)));
        }

        $manager->flush();
    }

    private function producto(string $nombre, string $descripcion, string $precio, int $stock, ?string $imagen = null): Producto
    {
        return (new Producto())
            ->setNombre($nombre)
            ->setDescripcion($descripcion)
            ->setPrecio($precio)
            ->setStock($stock)
            ->addImagen((new Imagen())->setUrl($imagen ?? 'https://placehold.co/600x400/png?text='.urlencode($nombre))->setOrden(1));
    }

    // Guarda el precio del producto en el momento de la compra
    private function linea(Producto $producto, int $cantidad): LineaCompra
    {
        return (new LineaCompra())
            ->setProducto($producto)
            ->setCantidad($cantidad)
            ->setPrecioUnitario($producto->getPrecio());
    }
}
