<?php

namespace App\Controller;

use App\Entity\Tienda;
use App\Entity\Usuario;
use App\Repository\TiendaRepository;
use App\Repository\UsuarioRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

// Servicios web del panel de administración: solo los puede usar un usuario con ROLE_ADMIN
#[Route('/ws/admin')]
class AdminController extends AbstractController
{
    private function noEsAdmin(): ?JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo un administrador puede hacer esto'], 403);
        }

        return null;
    }

    private function usuarioJson(Usuario $u): array
    {
        return [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'nombre' => $u->getNombre(),
            'apellidos' => $u->getApellidos(),
            'telefono' => $u->getTelefono(),
            'rol' => in_array('ROLE_ADMIN', $u->getRoles(), true) ? 'admin' : 'usuario',
            'activo' => $u->isActivo(),
            'fecha_alta' => $u->getFechaAlta()?->format('Y-m-d'),
        ];
    }

    // ===================== Resumen para el inicio del panel =====================

    #[Route('/resumen', name: 'ws_admin_resumen', methods: ['GET'])]
    public function resumen(EntityManagerInterface $em): JsonResponse
    {
        if ($denegado = $this->noEsAdmin()) {
            return $denegado;
        }

        $cn = $em->getConnection();
        $uno = fn (string $sql) => (float) $cn->fetchOne($sql);

        return $this->json([
            'usuarios' => (int) $uno('SELECT COUNT(*) FROM usuario'),
            'usuarios_activos' => (int) $uno('SELECT COUNT(*) FROM usuario WHERE activo = 1'),
            'videojuegos' => (int) $uno('SELECT COUNT(*) FROM videojuego'),
            'moviles' => (int) $uno('SELECT COUNT(*) FROM movil'),
            'compras' => (int) $uno('SELECT COUNT(*) FROM compra'),
            'ingresos' => round($uno('SELECT COALESCE(SUM(cantidad * precio_unitario), 0) FROM linea_compra'), 2),
            'alquileres_activos' => (int) $uno('SELECT COUNT(*) FROM alquiler WHERE fecha_devolucion IS NULL'),
            'tickets_abiertos' => (int) $uno("SELECT COUNT(*) FROM ticket WHERE estado <> 'cerrado'"),
            'stock_bajo' => (int) $uno('SELECT COUNT(*) FROM producto WHERE stock <= 3'),
        ]);
    }

    // ===================== Usuarios =====================

    #[Route('/usuarios', name: 'ws_admin_usuarios', methods: ['GET'])]
    public function usuarios(UsuarioRepository $usuarioRepository): JsonResponse
    {
        if ($denegado = $this->noEsAdmin()) {
            return $denegado;
        }

        return $this->json(array_map(fn (Usuario $u) => $this->usuarioJson($u), $usuarioRepository->findBy([], ['id' => 'ASC'])));
    }

    #[Route('/usuarios/add', name: 'ws_admin_usuarios_add', methods: ['POST'])]
    public function addUsuario(Request $request, UsuarioRepository $usuarioRepository, UserPasswordHasherInterface $hasher): JsonResponse
    {
        if ($denegado = $this->noEsAdmin()) {
            return $denegado;
        }

        $d = json_decode($request->getContent(), true) ?? [];
        foreach (['email', 'nombre', 'apellidos', 'password'] as $campo) {
            if (empty($d[$campo])) {
                return $this->json(['resultado' => 'error', 'mensaje' => "Falta el campo $campo"], 400);
            }
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El email no es válido'], 400);
        }
        if (strlen($d['password']) < 6) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'La contraseña debe tener al menos 6 caracteres'], 400);
        }

        $roles = ($d['rol'] ?? 'usuario') === 'admin' ? ['ROLE_ADMIN'] : ['ROLE_USER'];
        $usuario = new Usuario($d['email'], $d['nombre'], $d['apellidos'], $d['telefono'] ?? null, $roles);
        $usuario->setPassword($hasher->hashPassword($usuario, $d['password']));

        try {
            $usuarioRepository->add($usuario);
        } catch (UniqueConstraintViolationException) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ya existe un usuario con ese email'], 409);
        }

        return $this->json(['resultado' => 'ok', 'id' => $usuario->getId()]);
    }

    #[Route('/usuarios/update/{id}', name: 'ws_admin_usuarios_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateUsuario(int $id, Request $request, UsuarioRepository $usuarioRepository, UserPasswordHasherInterface $hasher): JsonResponse
    {
        if ($denegado = $this->noEsAdmin()) {
            return $denegado;
        }

        $usuario = $usuarioRepository->findOneBy(['id' => $id]);
        if ($usuario === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Usuario no encontrado'], 404);
        }

        $d = json_decode($request->getContent(), true) ?? [];
        $esAdmin = ($d['rol'] ?? 'usuario') === 'admin';
        $activo = (bool) ($d['activo'] ?? true);

        // Para no quedarse sin acceso al panel, un administrador no puede desactivarse ni quitarse el rol a sí mismo
        /** @var Usuario $yo */
        $yo = $this->getUser();
        if ($yo->getId() === $usuario->getId() && (!$activo || !$esAdmin)) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'No puedes desactivarte ni quitarte el rol de administrador a ti mismo'], 409);
        }
        if (empty($d['nombre']) || empty($d['apellidos'])) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Nombre y apellidos son obligatorios'], 400);
        }

        $usuario->setNombre($d['nombre']);
        $usuario->setApellidos($d['apellidos']);
        $usuario->setTelefono($d['telefono'] ?? null);
        $usuario->setRoles($esAdmin ? ['ROLE_ADMIN'] : ['ROLE_USER']);
        $usuario->setActivo($activo);

        if (!empty($d['password'])) {
            if (strlen($d['password']) < 6) {
                return $this->json(['resultado' => 'error', 'mensaje' => 'La contraseña debe tener al menos 6 caracteres'], 400);
            }
            $usuario->setPassword($hasher->hashPassword($usuario, $d['password']));
        }

        $usuarioRepository->add($usuario);

        return $this->json(['resultado' => 'ok']);
    }

    // ===================== Subida de imágenes de producto =====================

    #[Route('/imagenes', name: 'ws_admin_imagenes', methods: ['POST'])]
    public function subirImagen(Request $request): JsonResponse
    {
        if ($denegado = $this->noEsAdmin()) {
            return $denegado;
        }

        $archivo = $request->files->get('archivo');
        if ($archivo === null || !$archivo->isValid()) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'No se ha recibido ninguna imagen válida (máximo 2 MB)'], 400);
        }
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        // Se mira el contenido real del archivo, no el nombre que trae
        $extension = $extensiones[(new \finfo(FILEINFO_MIME_TYPE))->file($archivo->getPathname())] ?? null;
        if ($extension === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Solo se admiten imágenes JPG, PNG o WebP'], 415);
        }

        $carpeta = $this->getParameter('kernel.project_dir').'/public/uploads';
        $nombre = bin2hex(random_bytes(12)).'.'.$extension;
        $archivo->move($carpeta, $nombre);

        // El HTTPS lo termina el bastión: la URL pública es la del dominio con https
        $esquema = $request->headers->get('X-Forwarded-Proto', $request->getScheme());

        return $this->json(['resultado' => 'ok', 'url' => $esquema.'://'.$request->getHost().'/uploads/'.$nombre]);
    }

    // ===================== Tiendas =====================

    private function aplicarTienda(Tienda $t, array $d): ?string
    {
        foreach (['nombre', 'direccion', 'telefono', 'horario', 'latitud', 'longitud'] as $campo) {
            if (!isset($d[$campo]) || $d[$campo] === '') {
                return "Falta el campo $campo";
            }
        }
        if (!is_numeric($d['latitud']) || abs((float) $d['latitud']) > 90 || !is_numeric($d['longitud']) || abs((float) $d['longitud']) > 180) {
            return 'Las coordenadas no son válidas';
        }
        $t->setNombre($d['nombre'])->setDireccion($d['direccion'])->setTelefono($d['telefono'])->setHorario($d['horario'])
            ->setLatitud(number_format((float) $d['latitud'], 6, '.', ''))->setLongitud(number_format((float) $d['longitud'], 6, '.', ''));

        return null;
    }

    #[Route('/tiendas/add', name: 'ws_admin_tiendas_add', methods: ['POST'])]
    public function addTienda(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($denegado = $this->noEsAdmin()) {
            return $denegado;
        }
        $tienda = new Tienda();
        if ($error = $this->aplicarTienda($tienda, json_decode($request->getContent(), true) ?? [])) {
            return $this->json(['resultado' => 'error', 'mensaje' => $error], 400);
        }
        try {
            $em->persist($tienda);
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ya existe una tienda con ese nombre'], 409);
        }

        return $this->json(['resultado' => 'ok', 'id' => $tienda->getId()]);
    }

    #[Route('/tiendas/update/{id}', name: 'ws_admin_tiendas_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateTienda(int $id, Request $request, TiendaRepository $tiendaRepository, EntityManagerInterface $em): JsonResponse
    {
        if ($denegado = $this->noEsAdmin()) {
            return $denegado;
        }
        $tienda = $tiendaRepository->find($id);
        if ($tienda === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Tienda no encontrada'], 404);
        }
        if ($error = $this->aplicarTienda($tienda, json_decode($request->getContent(), true) ?? [])) {
            return $this->json(['resultado' => 'error', 'mensaje' => $error], 400);
        }
        try {
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ya existe una tienda con ese nombre'], 409);
        }

        return $this->json(['resultado' => 'ok']);
    }

    #[Route('/tiendas/delete/{id}', name: 'ws_admin_tiendas_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteTienda(int $id, TiendaRepository $tiendaRepository, EntityManagerInterface $em): JsonResponse
    {
        if ($denegado = $this->noEsAdmin()) {
            return $denegado;
        }
        $tienda = $tiendaRepository->find($id);
        if ($tienda === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Tienda no encontrada'], 404);
        }
        $em->remove($tienda);
        $em->flush();

        return $this->json(['resultado' => 'ok']);
    }
}
