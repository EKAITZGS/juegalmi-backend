<?php

namespace App\Entity;

use App\Repository\VideojuegoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideojuegoRepository::class)]
class Videojuego
{
    // La clave primaria es la propia relación 1:1 con producto (PK y FK a la vez)
    #[ORM\Id]
    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'producto_id', nullable: false, onDelete: 'CASCADE')]
    private ?Producto $producto = null;

    #[ORM\ManyToOne(inversedBy: 'videojuegos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Plataforma $plataforma = null;

    #[ORM\Column(length: 50)]
    private ?string $genero = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $pegi = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $precioAlquilerDia = null;

    /** @var Collection<int, Alquiler> */
    #[ORM\OneToMany(targetEntity: Alquiler::class, mappedBy: 'videojuego')]
    private Collection $alquileres;

    public function __construct(?Producto $producto = null, ?Plataforma $plataforma = null, ?string $genero = null, ?int $pegi = null, ?string $precioAlquilerDia = null)
    {
        $this->producto = $producto;
        $this->plataforma = $plataforma;
        $this->genero = $genero;
        $this->pegi = $pegi;
        $this->precioAlquilerDia = $precioAlquilerDia;
        $this->alquileres = new ArrayCollection();
    }

    public function getGenero(): ?string
    {
        return $this->genero;
    }

    public function setGenero(string $genero): static
    {
        $this->genero = $genero;

        return $this;
    }

    public function getPegi(): ?int
    {
        return $this->pegi;
    }

    public function setPegi(int $pegi): static
    {
        $this->pegi = $pegi;

        return $this;
    }

    public function getPrecioAlquilerDia(): ?string
    {
        return $this->precioAlquilerDia;
    }

    public function setPrecioAlquilerDia(string $precioAlquilerDia): static
    {
        $this->precioAlquilerDia = $precioAlquilerDia;

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

    public function getPlataforma(): ?Plataforma
    {
        return $this->plataforma;
    }

    public function setPlataforma(?Plataforma $plataforma): static
    {
        $this->plataforma = $plataforma;

        return $this;
    }

    /**
     * @return Collection<int, Alquiler>
     */
    public function getAlquileres(): Collection
    {
        return $this->alquileres;
    }

    public function addAlquiler(Alquiler $alquiler): static
    {
        if (!$this->alquileres->contains($alquiler)) {
            $this->alquileres->add($alquiler);
            $alquiler->setVideojuego($this);
        }

        return $this;
    }

    public function removeAlquiler(Alquiler $alquiler): static
    {
        if ($this->alquileres->removeElement($alquiler)) {
            // set the owning side to null (unless already changed)
            if ($alquiler->getVideojuego() === $this) {
                $alquiler->setVideojuego(null);
            }
        }

        return $this;
    }
}
