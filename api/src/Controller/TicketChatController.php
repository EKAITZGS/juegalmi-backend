<?php

namespace App\Controller;

use App\Entity\MensajeTicket;
use App\Entity\Ticket;
use App\Entity\Usuario;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

// Conversación de un ticket: la ven y escriben su dueño y los administradores
#[Route('/ws/tickets')]
class TicketChatController extends AbstractController
{
    // Devuelve el ticket si el usuario del token puede verlo, o la respuesta de error
    private function ticketAccesible(int $id, TicketRepository $ticketRepository): Ticket|JsonResponse
    {
        $ticket = $ticketRepository->findOneBy(['id' => $id]);
        if ($ticket === null) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ticket no encontrado'], 404);
        }
        if (!$this->isGranted('ROLE_ADMIN') && $ticket->getUsuario() !== $this->getUser()) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'Ese ticket no es tuyo'], 403);
        }

        return $ticket;
    }

    #[Route('/{id}/mensajes', name: 'ws_ticket_mensajes', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function mensajes(int $id, TicketRepository $ticketRepository, EntityManagerInterface $em): JsonResponse
    {
        $ticket = $this->ticketAccesible($id, $ticketRepository);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $lista = [];
        foreach ($em->getRepository(MensajeTicket::class)->findBy(['ticket' => $ticket], ['id' => 'ASC']) as $m) {
            $autor = $m->getUsuario();
            $lista[] = [
                'id' => $m->getId(),
                'usuario_id' => $autor->getId(),
                'autor' => $autor->getNombre(),
                'es_admin' => in_array('ROLE_ADMIN', $autor->getRoles(), true),
                'texto' => $m->getTexto(),
                'fecha' => $m->getFecha()->format('Y-m-d H:i'),
            ];
        }

        return $this->json($lista);
    }

    #[Route('/{id}/mensajes', name: 'ws_ticket_mensajes_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addMensaje(int $id, Request $request, TicketRepository $ticketRepository, EntityManagerInterface $em): JsonResponse
    {
        $ticket = $this->ticketAccesible($id, $ticketRepository);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }
        if ($ticket->getEstado() === 'cerrado') {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El ticket está cerrado'], 409);
        }

        $datos = json_decode($request->getContent(), true) ?? [];
        $texto = trim((string) ($datos['texto'] ?? ''));
        if ($texto === '' || mb_strlen($texto) > 1000) {
            return $this->json(['resultado' => 'error', 'mensaje' => 'El mensaje debe tener entre 1 y 1000 caracteres'], 400);
        }

        /** @var Usuario $yo */
        $yo = $this->getUser();
        $em->persist(new MensajeTicket($ticket, $yo, $texto));

        // Cuando un administrador contesta por primera vez, el ticket pasa a "en curso"
        if ($this->isGranted('ROLE_ADMIN') && $ticket->getEstado() === 'abierto') {
            $ticket->setEstado('en_proceso');
        }
        $em->flush();

        return $this->json(['resultado' => 'ok']);
    }
}
