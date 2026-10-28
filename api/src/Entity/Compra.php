<?php

namespace App\Entity;

use App\Repository\CompraRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompraRepository::class)]
class Compra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'compras')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fecha = null;

    /** @var Collection<int, LineaCompra> */
    #[ORM\OneToMany(targetEntity: LineaCompra::class, mappedBy: 'compra', cascade: ['persist'], orphanRemoval: true)]
    private Collection $lineas;

    public function __construct(?Usuario $usuario = null)
    {
        $this->usuario = $usuario;
        $this->fecha = new \DateTime();
        $this->lineas = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFecha(): ?\DateTime
    {
        return $this->fecha;
    }

    public function setFecha(\DateTime $fecha): static
    {
        $this->fecha = $fecha;

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

    /**
     * @return Collection<int, LineaCompra>
     */
    public function getLineas(): Collection
    {
        return $this->lineas;
    }

    public function addLinea(LineaCompra $linea): static
    {
        if (!$this->lineas->contains($linea)) {
            $this->lineas->add($linea);
            $linea->setCompra($this);
        }

        return $this;
    }

    public function removeLinea(LineaCompra $linea): static
    {
        if ($this->lineas->removeElement($linea)) {
            // set the owning side to null (unless already changed)
            if ($linea->getCompra() === $this) {
                $linea->setCompra(null);
            }
        }

        return $this;
    }
}
