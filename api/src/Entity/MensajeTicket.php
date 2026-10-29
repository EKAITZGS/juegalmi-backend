<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Un mensaje de la conversación de un ticket de soporte (del cliente o de un administrador)
#[ORM\Entity]
class MensajeTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Al borrar el ticket, MariaDB borra también su conversación
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Ticket $ticket = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $texto = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fecha = null;

    public function __construct(?Ticket $ticket = null, ?Usuario $usuario = null, ?string $texto = null)
    {
        $this->ticket = $ticket;
        $this->usuario = $usuario;
        $this->texto = $texto;
        $this->fecha = new \DateTime();
    }

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

    public function getTexto(): ?string
    {
        return $this->texto;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }
}
