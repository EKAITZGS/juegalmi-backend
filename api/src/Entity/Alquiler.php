<?php

namespace App\Entity;

use App\Repository\AlquilerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlquilerRepository::class)]
class Alquiler
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'alquileres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    // Apunta a videojuego, no a producto: la clave externa impide alquilar un móvil
    #[ORM\ManyToOne(inversedBy: 'alquileres')]
    #[ORM\JoinColumn(name: 'videojuego_id', referencedColumnName: 'producto_id', nullable: false)]
    private ?Videojuego $videojuego = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $fechaInicio = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $fechaFin = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaDevolucion = null;

    // Precio por día en el momento del alquiler
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $precioDia = null;

    public function __construct(?Usuario $usuario = null, ?Videojuego $videojuego = null, ?\DateTimeInterface $fechaInicio = null, ?\DateTimeInterface $fechaFin = null, ?string $precioDia = null)
    {
        $this->usuario = $usuario;
        $this->videojuego = $videojuego;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->precioDia = $precioDia;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFechaInicio(): ?\DateTime
    {
        return $this->fechaInicio;
    }

    public function setFechaInicio(\DateTime $fechaInicio): static
    {
        $this->fechaInicio = $fechaInicio;

        return $this;
    }

    public function getFechaFin(): ?\DateTime
    {
        return $this->fechaFin;
    }

    public function setFechaFin(\DateTime $fechaFin): static
    {
        $this->fechaFin = $fechaFin;

        return $this;
    }

    public function getFechaDevolucion(): ?\DateTime
    {
        return $this->fechaDevolucion;
    }

    public function setFechaDevolucion(?\DateTime $fechaDevolucion): static
    {
        $this->fechaDevolucion = $fechaDevolucion;

        return $this;
    }

    public function getPrecioDia(): ?string
    {
        return $this->precioDia;
    }

    public function setPrecioDia(string $precioDia): static
    {
        $this->precioDia = $precioDia;

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

    public function getVideojuego(): ?Videojuego
    {
        return $this->videojuego;
    }

    public function setVideojuego(?Videojuego $videojuego): static
    {
        $this->videojuego = $videojuego;

        return $this;
    }
}
