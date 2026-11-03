<?php

namespace App\Controller;

use App\Entity\Alquiler;
use App\Entity\Articulo;
use App\Entity\Compra;
use App\Entity\Favorito;
use App\Entity\Imagen;
use App\Entity\LineaCompra;
use App\Entity\Movil;
use App\Entity\Producto;
use App\Entity\Ticket;
use App\Entity\Usuario;
use App\Entity\Videojuego;
use App\Repository\AlquilerRepository;
use App\Repository\ArticuloRepository;
use App\Repository\CompraRepository;
use App\Repository\DepartamentoRepository;
use App\Repository\MovilRepository;
use App\Repository\PlataformaRepository;
use App\Repository\ProductoRepository;
use App\Repository\TicketRepository;
use App\Repository\TiendaRepository;
use App\Repository\UsuarioRepository;
use App\Repository\VideojuegoRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use App\Service\Asistente;
use App\Service\Factura;
use Symfony\Component\HttpFoundation\Response;
use App\Service\Garantias;
use App\Service\VerificacionCorreo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ws')]
class WsController extends AbstractController
{
    // ===================== Registro (público; el login lo hace lexik en POST /ws/login) =====================

    #[Route('/registro', name: 'ws_registro', methods: ['POST'])]
    public function registro(Request $request, UsuarioRepository $usuarioRepository, UserPasswordHasherInterface $passwordHasher, VerificacionCorreo $verificacion, RateLimiterFactory $registroLimiter): JsonResponse
    {
        // Freno anti bots: pocas altas por hora desde la misma conexión
        if (!$registroLimiter->create($request->getClientIp() ?? 'desconocida')->consume()->isAccepted()) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Demasiados registros desde tu conexión. Inténtalo más tarde'], 429);
        }

        $datos = json_decode($request->getContent(), true) ?? [];
        foreach (['email', 'nombre', 'apellidos', 'password'] as $campo) {
            if (empty($datos[$campo]) || !is_string($datos[$campo])) {
                return $this->json(['resultado' => 'error', 'mensaje' => "Falta el campo $campo"], 400);
            }
        }
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El email no es válido'], 400);
        }
        if (strlen($datos['password']) < 6) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'La contraseña debe tener al menos 6 caracteres'], 400);
        }

        $usuario = new Usuario($datos['email'], $datos['nombre'], $datos['apellidos'], $datos['telefono'] ?? null, ['ROLE_USER']);
        // Se guarda el hash de la contraseña, nunca la contraseña
        $usuario->setPassword($passwordHasher->hashPassword($usuario, $datos['password']));
        // Con la verificación activa, la cuenta no entra hasta abrir el enlace del correo
        $usuario->setVerificado(!$verificacion->activa());

        try {
            $usuarioRepository->add($usuario);
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ya existe un usuario con ese email'], 409);
        }

        if ($verificacion->activa()) {
            try {
                $verificacion->enviar($usuario);
            } catch (\Throwable) {
                // Sin correo no hay forma de verificar: se deshace el alta para que pueda repetirla
                $usuarioRepository->remove($usuario);

                return $this->json(['resultado' => 'error', 'mensaje' => 'No se pudo enviar el correo de verificación. Inténtalo más tarde'], 503);
            }
        }

        return $this->json(['resultado' => 'ok', 'id' => $usuario->getId(), 'verificacion' => $verificacion->activa()]);
    }

    // ===================== Perfil del usuario del token =====================

    #[Route('/perfil', name: 'ws_perfil', methods: ['GET'])]
    public function perfil(): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $this->getUser();

        return $this->json([
            'id' => $usuario->getId(),
            'email' => $usuario->getEmail(),
            'nombre' => $usuario->getNombre(),
            'apellidos' => $usuario->getApellidos(),
            'telefono' => $usuario->getTelefono(),
            'roles' => $usuario->getRoles(),
        ]);
    }

    #[Route('/perfil/update', name: 'ws_perfil_update', methods: ['PUT'])]
    public function updatePerfil(Request $request, UsuarioRepository $usuarioRepository, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $this->getUser();
        $datos = json_decode($request->getContent(), true) ?? [];

        if (empty($datos['nombre']) || empty($datos['apellidos'])) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Nombre y apellidos son obligatorios'], 400);
        }
        $usuario->setNombre($datos['nombre']);
        $usuario->setApellidos($datos['apellidos']);
        $usuario->setTelefono($datos['telefono'] ?? null);

        // Para cambiar la contraseña hay que dar la actual (por si alguien usa un móvil ajeno)
        if (!empty($datos['password_nueva'])) {
            if (empty($datos['password_actual']) || !$passwordHasher->isPasswordValid($usuario, $datos['password_actual'])) {
                return $this->json(['resultado' => 'error', 'mensaje' => 'La contraseña actual no es correcta'], 403);
            }
            if (strlen($datos['password_nueva']) < 6) {
                return $this->json(['resultado' => 'error', 'mensaje' => 'La contraseña nueva debe tener al menos 6 caracteres'], 400);
            }
            $usuario->setPassword($passwordHasher->hashPassword($usuario, $datos['password_nueva']));
        }

        $usuarioRepository->add($usuario);

        return $this->json(['resultado' => 'ok']);
    }

    // ===================== Plataformas y departamentos =====================

    #[Route('/plataformas', name: 'ws_plataformas', methods: ['GET'])]
    public function plataformas(PlataformaRepository $plataformaRepository): JsonResponse
    {
        $plataformas = $plataformaRepository->findAll();

        $lista = [];
        foreach ($plataformas as $plataforma) {
            $lista[] = [
                'id' => $plataforma->getId(),
                'nombre' => $plataforma->getNombre(),
            ];
        }

        return $this->json($lista);
    }

    #[Route('/departamentos', name: 'ws_departamentos', methods: ['GET'])]
    public function departamentos(DepartamentoRepository $departamentoRepository): JsonResponse
    {
        $departamentos = $departamentoRepository->findAll();

        $lista = [];
        foreach ($departamentos as $departamento) {
            $lista[] = [
                'id' => $departamento->getId(),
                'nombre' => $departamento->getNombre(),
            ];
        }

        return $this->json($lista);
    }

    #[Route('/tiendas', name: 'ws_tiendas', methods: ['GET'])]
    public function tiendas(TiendaRepository $tiendaRepository): JsonResponse
    {
        $lista = [];
        foreach ($tiendaRepository->findBy([], ['nombre' => 'ASC']) as $tienda) {
            $lista[] = [
                'id' => $tienda->getId(),
                'nombre' => $tienda->getNombre(),
                'direccion' => $tienda->getDireccion(),
                'telefono' => $tienda->getTelefono(),
                'horario' => $tienda->getHorario(),
                'latitud' => (float) $tienda->getLatitud(),
                'longitud' => (float) $tienda->getLongitud(),
            ];
        }

        return $this->json($lista);
    }

    // ===================== Videojuegos =====================

    #[Route('/videojuegos', name: 'ws_videojuegos', methods: ['GET'])]
    public function videojuegos(VideojuegoRepository $videojuegoRepository): JsonResponse
    {
        $videojuegos = $videojuegoRepository->findAll();

        $lista = [];
        foreach ($videojuegos as $videojuego) {
            $producto = $videojuego->getProducto();
            $imagenes = [];
            foreach ($producto->getImagenes() as $imagen) {
                $imagenes[] = $imagen->getUrl();
            }
            $lista[] = [
                'id' => $producto->getId(),
                'nombre' => $producto->getNombre(),
                'descripcion' => $producto->getDescripcion(),
                'precio' => (float) $producto->getPrecio(),
                'stock' => $producto->getStock(),
                'imagenes' => $imagenes,
                'plataforma_id' => $videojuego->getPlataforma()->getId(),
                'plataforma' => $videojuego->getPlataforma()->getNombre(),
                'genero' => $videojuego->getGenero(),
                'pegi' => $videojuego->getPegi(),
                'precio_alquiler_dia' => (float) $videojuego->getPrecioAlquilerDia(),
            ];
        }

        return $this->json($lista);
    }

    #[Route('/videojuegos/{id}', name: 'ws_videojuego', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function videojuego(int $id, VideojuegoRepository $videojuegoRepository): JsonResponse
    {
        $videojuego = $videojuegoRepository->findOneBy(['producto' => $id]);

        if ($videojuego === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Videojuego no encontrado'], 404);
        }

        $producto = $videojuego->getProducto();
        $imagenes = [];
        foreach ($producto->getImagenes() as $imagen) {
            $imagenes[] = $imagen->getUrl();
        }

        return $this->json([
            'id' => $producto->getId(),
            'nombre' => $producto->getNombre(),
            'descripcion' => $producto->getDescripcion(),
            'precio' => (float) $producto->getPrecio(),
            'stock' => $producto->getStock(),
            'imagenes' => $imagenes,
            'plataforma_id' => $videojuego->getPlataforma()->getId(),
            'plataforma' => $videojuego->getPlataforma()->getNombre(),
            'genero' => $videojuego->getGenero(),
            'pegi' => $videojuego->getPegi(),
            'precio_alquiler_dia' => (float) $videojuego->getPrecioAlquilerDia(),
        ]);
    }

    #[Route('/videojuegos/add', name: 'ws_videojuegos_add', methods: ['POST'])]
    public function addVideojuego(Request $request, VideojuegoRepository $videojuegoRepository, PlataformaRepository $plataformaRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede dar de alta productos'], 403);
        }

        $datos = json_decode($request->getContent(), true);

        $plataforma = $plataformaRepository->findOneBy(['id' => $datos['plataforma_id']]);
        if ($plataforma === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Plataforma no encontrada'], 404);
        }

        $producto = new Producto($datos['nombre'], $datos['descripcion'], $datos['precio'], $datos['stock']);
        $producto->addImagen(new Imagen(null, $datos['imagen'], 1));

        $videojuego = new Videojuego($producto, $plataforma, $datos['genero'], $datos['pegi'], $datos['precio_alquiler_dia']);

        $videojuegoRepository->add($videojuego);

        return $this->json(['resultado' => 'ok', 'id' => $producto->getId()]);
    }

    #[Route('/videojuegos/update/{id}', name: 'ws_videojuegos_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateVideojuego(int $id, Request $request, VideojuegoRepository $videojuegoRepository, PlataformaRepository $plataformaRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede modificar productos'], 403);
        }

        $videojuego = $videojuegoRepository->findOneBy(['producto' => $id]);
        if ($videojuego === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Videojuego no encontrado'], 404);
        }

        $datos = json_decode($request->getContent(), true);

        $plataforma = $plataformaRepository->findOneBy(['id' => $datos['plataforma_id']]);
        if ($plataforma === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Plataforma no encontrada'], 404);
        }

        $producto = $videojuego->getProducto();
        $producto->setNombre($datos['nombre']);
        $producto->setDescripcion($datos['descripcion']);
        $producto->setPrecio($datos['precio']);
        $producto->setStock($datos['stock']);
        $imagen = $producto->getImagenes()->first() ?: new Imagen(null, null, 1);
        $producto->addImagen($imagen->setUrl($datos['imagen']));

        $videojuego->setPlataforma($plataforma);
        $videojuego->setGenero($datos['genero']);
        $videojuego->setPegi($datos['pegi']);
        $videojuego->setPrecioAlquilerDia($datos['precio_alquiler_dia']);

        $videojuegoRepository->add($videojuego);

        return $this->json(['resultado' => 'ok']);
    }

    #[Route('/videojuegos/delete/{id}', name: 'ws_videojuegos_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteVideojuego(int $id, VideojuegoRepository $videojuegoRepository, ProductoRepository $productoRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede eliminar productos'], 403);
        }

        $videojuego = $videojuegoRepository->findOneBy(['producto' => $id]);
        if ($videojuego === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Videojuego no encontrado'], 404);
        }

        try {
            // Se borra el producto: MariaDB borra en cascada su videojuego y sus imágenes
            $productoRepository->remove($videojuego->getProducto());
        } catch (ForeignKeyConstraintViolationException $e) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El videojuego tiene compras o alquileres y no se puede eliminar'], 409);
        }

        return $this->json(['resultado' => 'ok']);
    }

    // ===================== Móviles =====================

    #[Route('/moviles', name: 'ws_moviles', methods: ['GET'])]
    public function moviles(MovilRepository $movilRepository): JsonResponse
    {
        $moviles = $movilRepository->findAll();

        $lista = [];
        foreach ($moviles as $movil) {
            $producto = $movil->getProducto();
            $imagenes = [];
            foreach ($producto->getImagenes() as $imagen) {
                $imagenes[] = $imagen->getUrl();
            }
            $lista[] = [
                'id' => $producto->getId(),
                'nombre' => $producto->getNombre(),
                'descripcion' => $producto->getDescripcion(),
                'precio' => (float) $producto->getPrecio(),
                'stock' => $producto->getStock(),
                'imagenes' => $imagenes,
                'marca' => $movil->getMarca(),
                'almacenamiento_gb' => $movil->getAlmacenamientoGb(),
                'ram_gb' => $movil->getRamGb(),
                'garantias' => Garantias::planes($producto->getPrecio()),
            ];
        }

        return $this->json($lista);
    }

    #[Route('/moviles/{id}', name: 'ws_movil', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function movil(int $id, MovilRepository $movilRepository): JsonResponse
    {
        $movil = $movilRepository->findOneBy(['producto' => $id]);

        if ($movil === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Móvil no encontrado'], 404);
        }

        $producto = $movil->getProducto();
        $imagenes = [];
        foreach ($producto->getImagenes() as $imagen) {
            $imagenes[] = $imagen->getUrl();
        }

        return $this->json([
            'id' => $producto->getId(),
            'nombre' => $producto->getNombre(),
            'descripcion' => $producto->getDescripcion(),
            'precio' => (float) $producto->getPrecio(),
            'stock' => $producto->getStock(),
            'imagenes' => $imagenes,
            'marca' => $movil->getMarca(),
            'almacenamiento_gb' => $movil->getAlmacenamientoGb(),
            'ram_gb' => $movil->getRamGb(),
            'garantias' => Garantias::planes($producto->getPrecio()),
        ]);
    }

    // ===================== Artículos (ordenadores, tablets y zona gaming) =====================

    #[Route('/articulos', name: 'ws_articulos', methods: ['GET'])]
    public function articulos(Request $request, ArticuloRepository $articuloRepository): JsonResponse
    {
        $categoria = $request->query->get('categoria');
        $filtro = isset(Articulo::CATEGORIAS[$categoria]) ? ['categoria' => $categoria] : [];

        return $this->json(array_map($this->articuloJson(...), $articuloRepository->findBy($filtro)));
    }

    #[Route('/articulos/{id}', name: 'ws_articulo', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function articulo(int $id, ArticuloRepository $articuloRepository): JsonResponse
    {
        $articulo = $articuloRepository->findOneBy(['producto' => $id]);

        return $articulo === null
            ? $this->json(['resultado' => 'error', 'mensaje' => 'Artículo no encontrado'], 404)
            : $this->json($this->articuloJson($articulo));
    }

    private function articuloJson(Articulo $articulo): array
    {
        $producto = $articulo->getProducto();

        return [
            'id' => $producto->getId(),
            'nombre' => $producto->getNombre(),
            'descripcion' => $producto->getDescripcion(),
            'precio' => (float) $producto->getPrecio(),
            'stock' => $producto->getStock(),
            'imagenes' => array_map(fn (Imagen $i) => $i->getUrl(), $producto->getImagenes()->toArray()),
            'categoria' => $articulo->getCategoria(),
            'marca' => $articulo->getMarca(),
            'montaje' => (float) $articulo->getMontaje(),
            'caracteristicas' => $producto->getCaracteristicas() ?? new \stdClass(),
            'garantias' => in_array($articulo->getCategoria(), Articulo::CON_GARANTIA, true) ? Garantias::planes($producto->getPrecio()) : [],
        ];
    }

    // Valida los datos de un artículo y los vuelca en él; devuelve el error 400 si algo no cuadra
    private function volcarArticulo(Articulo $articulo, mixed $d): ?JsonResponse
    {
        $error = fn (string $m) => $this->json(['resultado' => 'error', 'mensaje' => $m], 400);
        if (!is_array($d)) {
            return $error('Datos no válidos');
        }
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $marca = trim((string) ($d['marca'] ?? ''));
        $descripcion = trim((string) ($d['descripcion'] ?? ''));
        if ($nombre === '' || strlen($nombre) > 150 || $marca === '' || strlen($marca) > 50 || $descripcion === '') {
            return $error('Nombre, marca y descripción son obligatorios');
        }
        if (!isset(Articulo::CATEGORIAS[$d['categoria'] ?? ''])) {
            return $error('Categoría no válida');
        }
        if (!is_numeric($d['precio'] ?? null) || $d['precio'] < 0 || !is_numeric($d['montaje'] ?? 0) || ($d['montaje'] ?? 0) < 0) {
            return $error('El precio y el montaje deben ser números de 0 o más');
        }
        if (!is_int($d['stock'] ?? null) || $d['stock'] < 0) {
            return $error('El stock debe ser un entero de 0 o más');
        }
        $caracteristicas = $d['caracteristicas'] ?? [];
        if (!is_array($caracteristicas)) {
            return $error('Las características deben ser un objeto');
        }
        $imagen = trim((string) ($d['imagen'] ?? ''));
        if ($imagen !== '' && !preg_match('#^(https?://|/)#', $imagen)) {
            return $error('La imagen debe ser una URL');
        }

        $producto = $articulo->getProducto() ?? new Producto();
        $producto->setNombre($nombre);
        $producto->setDescripcion($descripcion);
        $producto->setPrecio(number_format((float) $d['precio'], 2, '.', ''));
        $producto->setStock($d['stock']);
        $producto->setCaracteristicas($caracteristicas ?: null);
        if ($imagen !== '') {
            $primera = $producto->getImagenes()->first() ?: new Imagen(null, null, 1);
            $producto->addImagen($primera->setUrl($imagen));
        }
        $articulo->setProducto($producto);
        $articulo->setCategoria($d['categoria']);
        $articulo->setMarca($marca);
        $articulo->setMontaje(number_format((float) ($d['montaje'] ?? 0), 2, '.', ''));

        return null;
    }

    #[Route('/articulos/add', name: 'ws_articulos_add', methods: ['POST'])]
    public function addArticulo(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede dar de alta productos'], 403);
        }
        $articulo = new Articulo();
        if ($fallo = $this->volcarArticulo($articulo, json_decode($request->getContent(), true))) {
            return $fallo;
        }
        $em->persist($articulo);
        $em->flush();

        return $this->json(['resultado' => 'ok', 'id' => $articulo->getProducto()->getId()]);
    }

    #[Route('/articulos/update/{id}', name: 'ws_articulos_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateArticulo(int $id, Request $request, ArticuloRepository $articuloRepository, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede modificar productos'], 403);
        }
        $articulo = $articuloRepository->findOneBy(['producto' => $id]);
        if ($articulo === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Artículo no encontrado'], 404);
        }
        if ($fallo = $this->volcarArticulo($articulo, json_decode($request->getContent(), true))) {
            return $fallo;
        }
        $em->flush();

        return $this->json(['resultado' => 'ok']);
    }

    #[Route('/articulos/delete/{id}', name: 'ws_articulos_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteArticulo(int $id, ArticuloRepository $articuloRepository, ProductoRepository $productoRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede eliminar productos'], 403);
        }
        $articulo = $articuloRepository->findOneBy(['producto' => $id]);
        if ($articulo === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Artículo no encontrado'], 404);
        }
        try {
            // Se borra el producto: MariaDB borra en cascada su artículo, sus imágenes y favoritos
            $productoRepository->remove($articulo->getProducto());
        } catch (ForeignKeyConstraintViolationException $e) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El artículo tiene compras y no se puede eliminar'], 409);
        }

        return $this->json(['resultado' => 'ok']);
    }

    #[Route('/moviles/add', name: 'ws_moviles_add', methods: ['POST'])]
    public function addMovil(Request $request, MovilRepository $movilRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede dar de alta productos'], 403);
        }

        $datos = json_decode($request->getContent(), true);

        $producto = new Producto($datos['nombre'], $datos['descripcion'], $datos['precio'], $datos['stock']);
        $producto->addImagen(new Imagen(null, $datos['imagen'], 1));

        $movil = new Movil($producto, $datos['marca'], $datos['almacenamiento_gb'], $datos['ram_gb']);

        $movilRepository->add($movil);

        return $this->json(['resultado' => 'ok', 'id' => $producto->getId()]);
    }

    #[Route('/moviles/update/{id}', name: 'ws_moviles_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateMovil(int $id, Request $request, MovilRepository $movilRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede modificar productos'], 403);
        }

        $movil = $movilRepository->findOneBy(['producto' => $id]);
        if ($movil === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Móvil no encontrado'], 404);
        }

        $datos = json_decode($request->getContent(), true);

        $producto = $movil->getProducto();
        $producto->setNombre($datos['nombre']);
        $producto->setDescripcion($datos['descripcion']);
        $producto->setPrecio($datos['precio']);
        $producto->setStock($datos['stock']);
        $imagen = $producto->getImagenes()->first() ?: new Imagen(null, null, 1);
        $producto->addImagen($imagen->setUrl($datos['imagen']));

        $movil->setMarca($datos['marca']);
        $movil->setAlmacenamientoGb($datos['almacenamiento_gb']);
        $movil->setRamGb($datos['ram_gb']);

        $movilRepository->add($movil);

        return $this->json(['resultado' => 'ok']);
    }

    #[Route('/moviles/delete/{id}', name: 'ws_moviles_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteMovil(int $id, MovilRepository $movilRepository, ProductoRepository $productoRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede eliminar productos'], 403);
        }

        $movil = $movilRepository->findOneBy(['producto' => $id]);
        if ($movil === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Móvil no encontrado'], 404);
        }

        try {
            // Se borra el producto: MariaDB borra en cascada su movil y sus imágenes
            $productoRepository->remove($movil->getProducto());
        } catch (ForeignKeyConstraintViolationException $e) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El móvil tiene compras y no se puede eliminar'], 409);
        }

        return $this->json(['resultado' => 'ok']);
    }

    // ===================== Compras =====================

    #[Route('/compras', name: 'ws_compras', methods: ['GET'])]
    public function compras(CompraRepository $compraRepository): JsonResponse
    {
        // El administrador ve todas; un cliente, solo las suyas
        if ($this->isGranted('ROLE_ADMIN')) {
            $compras = $compraRepository->findBy([], ['fecha' => 'DESC']);
        } else {
            $compras = $compraRepository->findBy(['usuario' => $this->getUser()], ['fecha' => 'DESC']);
        }

        $lista = [];
        foreach ($compras as $compra) {
            $lineas = [];
            $total = 0;
            foreach ($compra->getLineas() as $linea) {
                $lineas[] = [
                    'producto_id' => $linea->getProducto()->getId(),
                    'nombre' => $linea->getProducto()->getNombre(),
                    'cantidad' => $linea->getCantidad(),
                    'precio_unitario' => (float) $linea->getPrecioUnitario(),
                    'garantia_meses' => $linea->getGarantiaMeses(),
                    'precio_garantia' => (float) $linea->getPrecioGarantia(),
                ];
                // El total no se guarda (sería redundante): se calcula sumando las líneas, con su garantía si la hay
                $total += $linea->getCantidad() * ((float) $linea->getPrecioUnitario() + (float) $linea->getPrecioGarantia());
            }
            $lista[] = [
                'id' => $compra->getId(),
                'usuario_id' => $compra->getUsuario()->getId(),
                'usuario' => $compra->getUsuario()->getEmail(),
                'fecha' => $compra->getFecha()->format('Y-m-d H:i'),
                'total' => round($total, 2),
                'tienda_id' => $compra->getTienda()?->getId(),
                'tienda' => $compra->getTienda()?->getNombre(),
                'lineas' => $lineas,
            ];
        }

        return $this->json($lista);
    }

    // Factura en PDF: la ve su dueño y los administradores
    #[Route('/compras/{id}/factura', name: 'ws_compras_factura', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function facturaCompra(int $id, CompraRepository $compraRepository, Factura $factura): Response
    {
        $compra = $compraRepository->find($id);
        if ($compra === null || (!$this->isGranted('ROLE_ADMIN') && $compra->getUsuario() !== $this->getUser())) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Compra no encontrada'], 404);
        }

        return new Response($factura->pdf($compra), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="factura-'.$compra->getId().'.pdf"',
        ]);
    }

    #[Route('/compras/add', name: 'ws_compras_add', methods: ['POST'])]
    public function addCompra(Request $request, CompraRepository $compraRepository, ProductoRepository $productoRepository, MovilRepository $movilRepository, ArticuloRepository $articuloRepository, TiendaRepository $tiendaRepository): JsonResponse
    {
        $datos = json_decode($request->getContent(), true);

        if (empty($datos['lineas'])) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'La compra debe tener al menos un producto'], 400);
        }

        // El comprador es el usuario del token, no uno que venga en el JSON
        $compra = new Compra($this->getUser());

        // Tienda donde se recoge: opcional, pero si viene tiene que existir
        if (($datos['tienda_id'] ?? null) !== null) {
            $tienda = $tiendaRepository->find((int) $datos['tienda_id']);
            if ($tienda === null) {
                return $this->json(['resultado' => 'error', 'mensaje' => 'La tienda elegida no existe'], 400);
            }
            $compra->setTienda($tienda);
        }

        foreach ($datos['lineas'] as $linea) {
            $producto = $productoRepository->findOneBy(['id' => $linea['producto_id']]);
            if ($producto === null) {
                return $this->json(['resultado' => 'error', 'mensaje' => 'Producto no encontrado'], 404);
            }
            if ($producto->getStock() < $linea['cantidad']) {
                return $this->json(['resultado' => 'error', 'mensaje' => 'No hay stock suficiente de '.$producto->getNombre()], 409);
            }

            $producto->setStock($producto->getStock() - $linea['cantidad']);

            // Se guarda el precio de hoy: el del producto puede cambiar después
            $nueva = new LineaCompra($producto, $linea['cantidad'], $producto->getPrecio());

            // Garantía ampliada: solo móviles, tablets y ordenadores, con planes válidos; el precio lo pone el servidor
            if (($linea['garantia_meses'] ?? null) !== null) {
                $precioGarantia = Garantias::precio($producto->getPrecio(), (int) $linea['garantia_meses']);
                $articulo = $articuloRepository->findOneBy(['producto' => $producto]);
                $admite = $articulo !== null ? in_array($articulo->getCategoria(), Articulo::CON_GARANTIA, true) : $movilRepository->findOneBy(['producto' => $producto]) !== null;
                if ($precioGarantia === null || !$admite) {
                    return $this->json(['resultado' => 'error', 'mensaje' => 'Garantía no disponible para '.$producto->getNombre()], 400);
                }
                $nueva->setGarantia((int) $linea['garantia_meses'], $precioGarantia);
            }
            $compra->addLinea($nueva);
        }

        $compraRepository->add($compra);

        return $this->json(['resultado' => 'ok', 'id' => $compra->getId()]);
    }

    // ===================== Alquileres =====================

    #[Route('/alquileres', name: 'ws_alquileres', methods: ['GET'])]
    public function alquileres(AlquilerRepository $alquilerRepository): JsonResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $alquileres = $alquilerRepository->findBy([], ['fechaInicio' => 'DESC']);
        } else {
            $alquileres = $alquilerRepository->findBy(['usuario' => $this->getUser()], ['fechaInicio' => 'DESC']);
        }

        $lista = [];
        foreach ($alquileres as $alquiler) {
            $lista[] = [
                'id' => $alquiler->getId(),
                'usuario_id' => $alquiler->getUsuario()->getId(),
                'usuario' => $alquiler->getUsuario()->getEmail(),
                'videojuego_id' => $alquiler->getVideojuego()->getProducto()->getId(),
                'videojuego' => $alquiler->getVideojuego()->getProducto()->getNombre(),
                'fecha_inicio' => $alquiler->getFechaInicio()->format('Y-m-d'),
                'fecha_fin' => $alquiler->getFechaFin()->format('Y-m-d'),
                'fecha_devolucion' => $alquiler->getFechaDevolucion()?->format('Y-m-d'),
                'precio_dia' => (float) $alquiler->getPrecioDia(),
            ];
        }

        return $this->json($lista);
    }

    #[Route('/alquileres/add', name: 'ws_alquileres_add', methods: ['POST'])]
    public function addAlquiler(Request $request, AlquilerRepository $alquilerRepository, VideojuegoRepository $videojuegoRepository): JsonResponse
    {
        $datos = json_decode($request->getContent(), true);

        $videojuego = $videojuegoRepository->findOneBy(['producto' => $datos['videojuego_id']]);
        if ($videojuego === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Videojuego no encontrado'], 404);
        }

        $producto = $videojuego->getProducto();
        if ($producto->getStock() < 1) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'No quedan copias de '.$producto->getNombre()], 409);
        }
        $producto->setStock($producto->getStock() - 1);

        $alquiler = new Alquiler($this->getUser(), $videojuego, new \DateTime('today'), new \DateTime('today +'.$datos['dias'].' days'), $videojuego->getPrecioAlquilerDia());

        $alquilerRepository->add($alquiler);

        return $this->json(['resultado' => 'ok', 'id' => $alquiler->getId()]);
    }

    #[Route('/alquileres/devolver/{id}', name: 'ws_alquileres_devolver', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function devolverAlquiler(int $id, AlquilerRepository $alquilerRepository): JsonResponse
    {
        $alquiler = $alquilerRepository->findOneBy(['id' => $id]);
        if ($alquiler === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Alquiler no encontrado'], 404);
        }
        if (!$this->isGranted('ROLE_ADMIN') && $alquiler->getUsuario() !== $this->getUser()) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ese alquiler no es tuyo'], 403);
        }
        if ($alquiler->getFechaDevolucion() !== null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Este alquiler ya se devolvió'], 409);
        }

        $producto = $alquiler->getVideojuego()->getProducto();
        $producto->setStock($producto->getStock() + 1);
        $alquiler->setFechaDevolucion(new \DateTime('today'));

        $alquilerRepository->add($alquiler);

        return $this->json(['resultado' => 'ok']);
    }

    // ===================== Tickets de soporte =====================

    #[Route('/tickets', name: 'ws_tickets', methods: ['GET'])]
    public function tickets(TicketRepository $ticketRepository, EntityManagerInterface $em): JsonResponse
    {
        // Cuántos mensajes tiene la conversación de cada ticket
        $conteos = $em->getConnection()->fetchAllKeyValue('SELECT ticket_id, COUNT(*) FROM mensaje_ticket GROUP BY ticket_id');
        // Y cuántos de esos mensajes no ha leído todavía el usuario del token (los escritos por otros)
        $noLeidos = $em->getConnection()->fetchAllKeyValue(
            'SELECT m.ticket_id, COUNT(*) FROM mensaje_ticket m
               LEFT JOIN lectura_ticket l ON l.ticket_id = m.ticket_id AND l.usuario_id = :yo
              WHERE m.usuario_id <> :yo AND m.id > COALESCE(l.ultimo_leido, 0) GROUP BY m.ticket_id',
            ['yo' => $this->getUser()->getId()],
        );
        if ($this->isGranted('ROLE_ADMIN')) {
            $tickets = $ticketRepository->findBy([], ['fechaApertura' => 'DESC']);
        } else {
            $tickets = $ticketRepository->findBy(['usuario' => $this->getUser()], ['fechaApertura' => 'DESC']);
        }

        $lista = [];
        foreach ($tickets as $ticket) {
            $lista[] = [
                'id' => $ticket->getId(),
                'usuario_id' => $ticket->getUsuario()->getId(),
                'usuario' => $ticket->getUsuario()->getEmail(),
                'departamento_id' => $ticket->getDepartamento()->getId(),
                'departamento' => $ticket->getDepartamento()->getNombre(),
                'asunto' => $ticket->getAsunto(),
                'descripcion' => $ticket->getDescripcion(),
                'dispositivo' => $ticket->getDispositivo(),
                'tipo' => $ticket->getTipo(),
                'presupuesto' => $ticket->getPresupuesto() === null ? null : (float) $ticket->getPresupuesto(),
                'presupuesto_aceptado' => $ticket->getPresupuestoAceptado(),
                'estado' => $ticket->getEstado(),
                'fecha_apertura' => $ticket->getFechaApertura()->format('Y-m-d H:i'),
                'fecha_cierre' => $ticket->getFechaCierre()?->format('Y-m-d H:i'),
                'n_mensajes' => (int) ($conteos[$ticket->getId()] ?? 0),
                'no_leidos' => (int) ($noLeidos[$ticket->getId()] ?? 0),
            ];
        }

        return $this->json($lista);
    }

    #[Route('/tickets/add', name: 'ws_tickets_add', methods: ['POST'])]
    public function addTicket(Request $request, TicketRepository $ticketRepository, DepartamentoRepository $departamentoRepository): JsonResponse
    {
        $datos = json_decode($request->getContent(), true);

        $departamento = $departamentoRepository->findOneBy(['id' => $datos['departamento_id']]);
        if ($departamento === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Departamento no encontrado'], 404);
        }

        $tipo = $datos['tipo'] ?? 'soporte';
        if (!in_array($tipo, Ticket::TIPOS, true)) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Tipo de ticket no válido'], 400);
        }

        $ticket = new Ticket($this->getUser(), $departamento, $datos['asunto'], $datos['descripcion'], $datos['dispositivo'], $tipo);

        $ticketRepository->add($ticket);

        return $this->json(['resultado' => 'ok', 'id' => $ticket->getId()]);
    }

    // El cliente acepta o rechaza el presupuesto de su reparación: aceptado pasa a "en reparación", rechazado cierra el ticket
    #[Route('/tickets/presupuesto/{id}', name: 'ws_tickets_presupuesto', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function responderPresupuesto(int $id, Request $request, TicketRepository $ticketRepository): JsonResponse
    {
        $ticket = $ticketRepository->findOneBy(['id' => $id, 'usuario' => $this->getUser()]);
        if ($ticket === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ticket no encontrado'], 404);
        }
        if ($ticket->getTipo() !== 'reparacion' || $ticket->getEstado() !== 'presupuestado') {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Este ticket no tiene un presupuesto pendiente'], 409);
        }
        $aceptado = (json_decode($request->getContent(), true) ?? [])['aceptado'] ?? null;
        if (!is_bool($aceptado)) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Indica si aceptas el presupuesto'], 400);
        }

        $ticket->setPresupuestoAceptado($aceptado);
        $ticket->setEstado($aceptado ? 'en_reparacion' : 'cerrado');
        $ticket->setFechaCierre($aceptado ? null : new \DateTime());
        $ticketRepository->add($ticket);

        return $this->json(['resultado' => 'ok']);
    }

    // ===================== Favoritos (ids de producto; los clientes ya tienen los catálogos) =====================

    #[Route('/favoritos', name: 'ws_favoritos', methods: ['GET'])]
    public function favoritos(EntityManagerInterface $em): JsonResponse
    {
        $ids = $em->createQuery('SELECT IDENTITY(f.producto) FROM App\Entity\Favorito f WHERE f.usuario = :u ORDER BY f.id DESC')
            ->setParameter('u', $this->getUser())
            ->getSingleColumnResult();

        return $this->json(array_map('intval', $ids));
    }

    #[Route('/favoritos/{id}', name: 'ws_favoritos_add', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function addFavorito(int $id, EntityManagerInterface $em): JsonResponse
    {
        $producto = $em->getRepository(Producto::class)->find($id);
        if ($producto === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Producto no encontrado'], 404);
        }
        if ($em->getRepository(Favorito::class)->findOneBy(['usuario' => $this->getUser(), 'producto' => $producto]) === null) {
            $em->persist(new Favorito($this->getUser(), $producto));
            $em->flush();
        }

        return $this->json(['resultado' => 'ok']);
    }

    #[Route('/favoritos/{id}', name: 'ws_favoritos_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteFavorito(int $id, EntityManagerInterface $em): JsonResponse
    {
        $em->createQuery('DELETE FROM App\Entity\Favorito f WHERE f.usuario = :u AND f.producto = :p')
            ->setParameters(['u' => $this->getUser(), 'p' => $id])
            ->execute();

        return $this->json(['resultado' => 'ok']);
    }

    #[Route('/tickets/update/{id}', name: 'ws_tickets_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateTicket(int $id, Request $request, TicketRepository $ticketRepository, DepartamentoRepository $departamentoRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede gestionar tickets'], 403);
        }

        $ticket = $ticketRepository->findOneBy(['id' => $id]);
        if ($ticket === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ticket no encontrado'], 404);
        }

        $datos = json_decode($request->getContent(), true);

        $departamento = $departamentoRepository->findOneBy(['id' => $datos['departamento_id']]);
        if ($departamento === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Departamento no encontrado'], 404);
        }

        $ticket->setDepartamento($departamento);
        $ticket->setAsunto($datos['asunto']);
        $ticket->setDescripcion($datos['descripcion']);
        $ticket->setDispositivo($datos['dispositivo']);
        if (!in_array($datos['estado'], Ticket::ESTADOS[$ticket->getTipo()], true)) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Estado no válido para este tipo de ticket'], 400);
        }
        // Presupuesto de la reparación (opcional); si cambia, el cliente tiene que volver a aceptarlo
        if (array_key_exists('presupuesto', $datos) && $ticket->getTipo() === 'reparacion') {
            $presupuesto = $datos['presupuesto'];
            if ($presupuesto !== null && (!is_numeric($presupuesto) || $presupuesto < 0 || $presupuesto > 99999)) {
                return $this->json(['resultado' => 'error', 'mensaje' => 'Presupuesto no válido'], 400);
            }
            $nuevo = $presupuesto === null ? null : number_format((float) $presupuesto, 2, '.', '');
            if ($nuevo !== $ticket->getPresupuesto()) {
                $ticket->setPresupuesto($nuevo);
            }
        }
        if ($datos['estado'] === 'presupuestado' && $ticket->getPresupuesto() === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Indica el presupuesto antes de enviarlo al cliente'], 409);
        }
        // No se repara ni se entrega sin que el cliente haya aceptado el presupuesto
        if (in_array($datos['estado'], ['en_reparacion', 'listo'], true) && $ticket->getPresupuestoAceptado() !== true) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El cliente aún no ha aceptado el presupuesto'], 409);
        }
        $ticket->setEstado($datos['estado']);
        // Al cerrar se apunta la fecha; si se reabre, se quita
        $ticket->setFechaCierre($datos['estado'] === 'cerrado' ? new \DateTime() : null);

        $ticketRepository->add($ticket);

        return $this->json(['resultado' => 'ok']);
    }

    #[Route('/tickets/delete/{id}', name: 'ws_tickets_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteTicket(int $id, TicketRepository $ticketRepository): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede gestionar tickets'], 403);
        }

        $ticket = $ticketRepository->findOneBy(['id' => $id]);
        if ($ticket === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ticket no encontrado'], 404);
        }

        $ticketRepository->remove($ticket);

        return $this->json(['resultado' => 'ok']);
    }

    // ===================== Asesor de compra con IA =====================

    #[Route('/asistente', name: 'ws_asistente', methods: ['POST'])]
    public function asistente(Request $request, Asistente $asistente, RateLimiterFactory $asistenteLimiter, \Psr\Log\LoggerInterface $logger): JsonResponse
    {
        if (!$asistente->activo()) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El asesor no está disponible ahora mismo'], 503);
        }
        // Cada consulta cuesta dinero: tope por usuario y hora
        if (!$asistenteLimiter->create($this->getUser()->getUserIdentifier())->consume()->isAccepted()) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Has hecho muchas consultas seguidas. Inténtalo en un rato'], 429);
        }

        // Solo turnos de texto (máx. 10, 600 caracteres cada uno); el último debe ser del cliente
        $historial = [];
        foreach (array_slice((json_decode($request->getContent(), true) ?? [])['mensajes'] ?? [], -10) as $m) {
            if (!is_array($m) || !in_array($m['role'] ?? null, ['user', 'assistant'], true) || !is_string($m['content'] ?? null) || trim($m['content']) === '') {
                return $this->json(['resultado' => 'error', 'mensaje' => 'Mensajes no válidos'], 400);
            }
            $historial[] = ['role' => $m['role'], 'content' => mb_substr($m['content'], 0, 600)];
        }
        while ($historial && $historial[0]['role'] !== 'user') {
            array_shift($historial);
        }
        if (!$historial || end($historial)['role'] !== 'user') {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Escribe tu pregunta'], 400);
        }

        // El proveedor gratuito falla de vez en cuando (límites, llamadas mal formadas): un reintento lo resuelve casi siempre
        for ($intento = 1; $intento <= 2; ++$intento) {
            try {
                return $this->json($asistente->responder($historial));
            } catch (\Throwable $e) {
                $logger->error("Asesor de IA (intento $intento): " . $e->getMessage());
            }
        }

        return $this->json(['resultado' => 'error', 'mensaje' => 'El asesor no pudo responder. Inténtalo de nuevo en un momento'], 503);
    }
}
