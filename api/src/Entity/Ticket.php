<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Departamento $departamento = null;

    #[ORM\Column(length: 150)]
    private ?string $asunto = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 100)]
    private ?string $dispositivo = null;

    // soporte: abierto, en_proceso, cerrado. reparacion: recibido, presupuestado, en_reparacion, listo, cerrado (CHECK en la base de datos)
    #[ORM\Column(length: 20)]
    private ?string $estado = null;

    public const TIPOS = ['soporte', 'reparacion'];
    public const ESTADOS = [
        'soporte' => ['abierto', 'en_proceso', 'cerrado'],
        'reparacion' => ['recibido', 'presupuestado', 'en_reparacion', 'listo', 'cerrado'],
    ];

    #[ORM\Column(length: 20, options: ['default' => 'soporte'])]
    private string $tipo = 'soporte';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $presupuesto = null;

    // null = el cliente aún no ha respondido, true = aceptado, false = rechazado
    #[ORM\Column(nullable: true)]
    private ?bool $presupuestoAceptado = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaApertura = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaCierre = null;

    public function __construct(?Usuario $usuario = null, ?Departamento $departamento = null, ?string $asunto = null, ?string $descripcion = null, ?string $dispositivo = null, string $tipo = 'soporte')
    {
        $this->usuario = $usuario;
        $this->departamento = $departamento;
        $this->asunto = $asunto;
        $this->descripcion = $descripcion;
        $this->dispositivo = $dispositivo;
        $this->tipo = $tipo;
        $this->estado = $tipo === 'reparacion' ? 'recibido' : 'abierto';
        $this->fechaApertura = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAsunto(): ?string
    {
        return $this->asunto;
    }

    public function setAsunto(string $asunto): static
    {
        $this->asunto = $asunto;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getDispositivo(): ?string
    {
        return $this->dispositivo;
    }

    public function setDispositivo(string $dispositivo): static
    {
        $this->dispositivo = $dispositivo;

        return $this;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function getPresupuesto(): ?string
    {
        return $this->presupuesto;
    }

    public function setPresupuesto(?string $presupuesto): static
    {
        $this->presupuesto = $presupuesto;
        // Un presupuesto nuevo vuelve a esperar la respuesta del cliente
        $this->presupuestoAceptado = null;

        return $this;
    }

    public function getPresupuestoAceptado(): ?bool
    {
        return $this->presupuestoAceptado;
    }

    public function setPresupuestoAceptado(?bool $presupuestoAceptado): static
    {
        $this->presupuestoAceptado = $presupuestoAceptado;

        return $this;
    }

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    public function getFechaApertura(): ?\DateTime
    {
        return $this->fechaApertura;
    }

    public function setFechaApertura(\DateTime $fechaApertura): static
    {
        $this->fechaApertura = $fechaApertura;

        return $this;
    }

    public function getFechaCierre(): ?\DateTime
    {
        return $this->fechaCierre;
    }

    public function setFechaCierre(?\DateTime $fechaCierre): static
    {
        $this->fechaCierre = $fechaCierre;

        return $this;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getDepartamento(): ?Departamento
    {
        return $this->departamento;
    }

    public function setDepartamento(?Departamento $departamento): static
    {
        $this->departamento = $departamento;

        return $this;
    }
}
