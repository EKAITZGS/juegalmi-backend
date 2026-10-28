<?php

namespace App\Entity;

use App\Repository\MovilRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovilRepository::class)]
class Movil
{
    // La clave primaria es la propia relación 1:1 con producto (PK y FK a la vez)
    #[ORM\Id]
    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'producto_id', nullable: false, onDelete: 'CASCADE')]
    private ?Producto $producto = null;

    #[ORM\Column(length: 50)]
    private ?string $marca = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $almacenamientoGb = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $ramGb = null;

    public function __construct(?Producto $producto = null, ?string $marca = null, ?int $almacenamientoGb = null, ?int $ramGb = null)
    {
        $this->producto = $producto;
        $this->marca = $marca;
        $this->almacenamientoGb = $almacenamientoGb;
        $this->ramGb = $ramGb;
    }

    public function getMarca(): ?string
    {
        return $this->marca;
    }

    public function setMarca(string $marca): static
    {
        $this->marca = $marca;

        return $this;
    }

    public function getAlmacenamientoGb(): ?int
    {
        return $this->almacenamientoGb;
    }

    public function setAlmacenamientoGb(int $almacenamientoGb): static
    {
        $this->almacenamientoGb = $almacenamientoGb;

        return $this;
    }

    public function getRamGb(): ?int
    {
        return $this->ramGb;
    }

    public function setRamGb(int $ramGb): static
    {
        $this->ramGb = $ramGb;

        return $this;
    }

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }

    public function setProducto(Producto $producto): static
    {
        $this->producto = $producto;

        return $this;
    }
}
