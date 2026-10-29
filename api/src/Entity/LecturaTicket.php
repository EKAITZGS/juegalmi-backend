<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

// Hasta qué mensaje ha leído cada usuario de la conversación de un ticket (para contar los no leídos)
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_lectura', columns: ['ticket_id', 'usuario_id'])]
class LecturaTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Ticket $ticket = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $ultimoLeido = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function getUltimoLeido(): int
    {
        return $this->ultimoLeido;
    }
}
