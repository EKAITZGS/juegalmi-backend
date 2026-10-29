<?php

namespace App\Controller;

use App\Repository\UsuarioRepository;
use App\Service\VerificacionCorreo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

// Servicios públicos (sin token): confirmar el correo y pedir otro enlace
#[Route('/ws/verificar')]
class VerificacionController extends AbstractController
{
    #[Route('', name: 'ws_verificar', methods: ['POST'])]
    public function confirmar(Request $request, VerificacionCorreo $verificacion, RateLimiterFactory $reenvioLimiter): JsonResponse
    {
        // También se limitan los intentos, para que nadie pruebe enlaces al azar
        if (!$reenvioLimiter->create($request->getClientIp() ?? 'desconocida')->consume()->isAccepted()) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Demasiados intentos. Espera un rato.'], 429);
        }

        $d = json_decode($request->getContent(), true) ?? [];
        $resultado = $verificacion->confirmar((string) ($d['email'] ?? ''), (string) ($d['token'] ?? ''));

        return match ($resultado) {
            'ok' => $this->json(['resultado' => 'ok']),
            'caducado' => $this->json(['resultado' => 'error', 'mensaje' => 'El enlace ha caducado: pide uno nuevo'], 410),
            default => $this->json(['resultado' => 'error', 'mensaje' => 'El enlace no es válido o ya se usó. Si ya verificaste tu cuenta, entra con tu email y contraseña'], 400),
        };
    }

    #[Route('/reenviar', name: 'ws_verificar_reenviar', methods: ['POST'])]
    public function reenviar(Request $request, UsuarioRepository $usuarioRepository, VerificacionCorreo $verificacion, RateLimiterFactory $reenvioLimiter): JsonResponse
    {
        if (!$reenvioLimiter->create($request->getClientIp() ?? 'desconocida')->consume()->isAccepted()) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Demasiados intentos. Espera un rato.'], 429);
        }

        $d = json_decode($request->getContent(), true) ?? [];
        $usuario = $usuarioRepository->findOneBy(['email' => (string) ($d['email'] ?? '')]);
        if ($usuario !== null && !$usuario->isVerificado() && $verificacion->activa() && $verificacion->puedeReenviar($usuario)) {
            try {
                $verificacion->enviar($usuario);
            } catch (\Throwable) {
                return $this->json(['resultado' => 'error', 'mensaje' => 'No se pudo enviar el correo'], 503);
            }
        }

        // Siempre la misma respuesta: así no se averigua qué correos están registrados
        return $this->json(['resultado' => 'ok']);
    }
}
