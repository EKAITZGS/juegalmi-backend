<?php

namespace App\EventListener;

use App\Entity\Ubicacion;
use App\Entity\Usuario;
use App\Repository\UbicacionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Antes de cada petición a la API, si la app manda X-Latitud y X-Longitud, guarda dónde estaba el usuario
#[AsEventListener(event: KernelEvents::CONTROLLER)]
class UbicacionListener
{
    public function __construct(private Security $security, private UbicacionRepository $ubicacionRepository)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $usuario = $this->security->getUser();
        $latitud = $request->headers->get('X-Latitud');
        $longitud = $request->headers->get('X-Longitud');

        if (!$usuario instanceof Usuario || !is_numeric($latitud) || !is_numeric($longitud)) {
            return;
        }

        $ubicacion = new Ubicacion($usuario, $latitud, $longitud, substr($request->getMethod().' '.$request->getPathInfo(), 0, 100));

        $this->ubicacionRepository->add($ubicacion);
    }
}
