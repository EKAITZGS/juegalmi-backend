<?php

namespace App\Controller;

use App\Entity\Alquiler;
use App\Entity\Compra;
use App\Entity\Imagen;
use App\Entity\LineaCompra;
use App\Entity\Movil;
use App\Entity\Producto;
use App\Entity\Ticket;
use App\Entity\Usuario;
use App\Entity\Videojuego;
use App\Repository\AlquilerRepository;
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
    public function registro(Request $request, UsuarioRepository $usuarioRepository, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $datos = json_decode($request->getContent(), true);

        $usuario = new Usuario($datos['email'], $datos['nombre'], $datos['apellidos'], $datos['telefono'] ?? null, ['ROLE_USER']);
        // Se guarda el hash de la contraseña, nunca la contraseña
        $usuario->setPassword($passwordHasher->hashPassword($usuario, $datos['password']));

        try {
            $usuarioRepository->add($usuario);
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ya existe un usuario con ese email'], 409);
        }

        return $this->json(['resultado' => 'ok', 'id' => $usuario->getId()]);
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
        ]);
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
                ];
                // El total no se guarda (sería redundante): se calcula sumando las líneas
                $total += $linea->getCantidad() * (float) $linea->getPrecioUnitario();
            }
            $lista[] = [
                'id' => $compra->getId(),
                'usuario_id' => $compra->getUsuario()->getId(),
                'usuario' => $compra->getUsuario()->getEmail(),
                'fecha' => $compra->getFecha()->format('Y-m-d H:i'),
                'total' => round($total, 2),
                'lineas' => $lineas,
            ];
        }

        return $this->json($lista);
    }

    #[Route('/compras/add', name: 'ws_compras_add', methods: ['POST'])]
    public function addCompra(Request $request, CompraRepository $compraRepository, ProductoRepository $productoRepository): JsonResponse
    {
        $datos = json_decode($request->getContent(), true);

        if (empty($datos['lineas'])) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'La compra debe tener al menos un producto'], 400);
        }

        // El comprador es el usuario del token, no uno que venga en el JSON
        $compra = new Compra($this->getUser());

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
            $compra->addLinea(new LineaCompra($producto, $linea['cantidad'], $producto->getPrecio()));
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
    public function tickets(TicketRepository $ticketRepository): JsonResponse
    {
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
                'estado' => $ticket->getEstado(),
                'fecha_apertura' => $ticket->getFechaApertura()->format('Y-m-d H:i'),
                'fecha_cierre' => $ticket->getFechaCierre()?->format('Y-m-d H:i'),
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

        $ticket = new Ticket($this->getUser(), $departamento, $datos['asunto'], $datos['descripcion'], $datos['dispositivo']);

        $ticketRepository->add($ticket);

        return $this->json(['resultado' => 'ok', 'id' => $ticket->getId()]);
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
}
