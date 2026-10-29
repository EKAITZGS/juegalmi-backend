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

// Conversación de un ticket: la ven y escriben su dueño y los administradores.
// Cada usuario tiene su propio "leído hasta aquí" para saber qué mensajes le faltan por ver.
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

    // Mensajes de otros que el usuario aún no ha visto, agrupados por ticket. Un cliente solo cuenta los de sus tickets
    #[Route('/no-leidos', name: 'ws_tickets_no_leidos', methods: ['GET'])]
    public function noLeidos(EntityManagerInterface $em): JsonResponse
    {
        /** @var Usuario $yo */
        $yo = $this->getUser();
        $soloMios = $this->isGranted('ROLE_ADMIN') ? '' : 'AND t.usuario_id = :yo';
        $filas = $em->getConnection()->fetchAllAssociative(
            "SELECT t.id, t.asunto, COUNT(*) AS no_leidos
               FROM mensaje_ticket m
               JOIN ticket t ON t.id = m.ticket_id
               LEFT JOIN lectura_ticket l ON l.ticket_id = m.ticket_id AND l.usuario_id = :yo
              WHERE m.usuario_id <> :yo AND m.id > COALESCE(l.ultimo_leido, 0) $soloMios
              GROUP BY t.id, t.asunto
              ORDER BY MAX(m.id) DESC",
            ['yo' => $yo->getId()],
        );

        $tickets = array_map(fn (array $f) => ['id' => (int) $f['id'], 'asunto' => $f['asunto'], 'no_leidos' => (int) $f['no_leidos']], $filas);

        return $this->json(['total' => array_sum(array_column($tickets, 'no_leidos')), 'tickets' => $tickets]);
    }

    // Al abrir la conversación se marcan como leídos todos sus mensajes
    #[Route('/{id}/mensajes', name: 'ws_ticket_mensajes', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function mensajes(int $id, TicketRepository $ticketRepository, EntityManagerInterface $em): JsonResponse
    {
        $ticket = $this->ticketAccesible($id, $ticketRepository);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $lista = [];
        $ultimo = 0;
        foreach ($em->getRepository(MensajeTicket::class)->findBy(['ticket' => $ticket], ['id' => 'ASC']) as $m) {
            $autor = $m->getUsuario();
            $ultimo = $m->getId();
            $lista[] = [
                'id' => $m->getId(),
                'usuario_id' => $autor->getId(),
                'autor' => $autor->getNombre(),
                'es_admin' => in_array('ROLE_ADMIN', $autor->getRoles(), true),
                'texto' => $m->getTexto(),
                'fecha' => $m->getFecha()->format('Y-m-d H:i'),
            ];
        }

        /** @var Usuario $yo */
        $yo = $this->getUser();
        $em->getConnection()->executeStatement(
            'INSERT INTO lectura_ticket (ticket_id, usuario_id, ultimo_leido) VALUES (:t, :u, :m)
             ON DUPLICATE KEY UPDATE ultimo_leido = GREATEST(ultimo_leido, VALUES(ultimo_leido))',
            ['t' => $ticket->getId(), 'u' => $yo->getId(), 'm' => $ultimo],
        );

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
