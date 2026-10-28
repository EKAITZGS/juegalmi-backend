<?php

namespace App\Entity;

use App\Repository\ProductoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductoRepository::class)]
class Producto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $precio = null;

    #[ORM\Column]
    private ?int $stock = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaAlta = null;

    /** @var Collection<int, Imagen> */
    #[ORM\OneToMany(targetEntity: Imagen::class, mappedBy: 'producto', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['orden' => 'ASC'])]
    private Collection $imagenes;

    /** @var Collection<int, LineaCompra> */
    #[ORM\OneToMany(targetEntity: LineaCompra::class, mappedBy: 'producto')]
    private Collection $lineaCompras;

    public function __construct(?string $nombre = null, ?string $descripcion = null, ?string $precio = null, ?int $stock = null)
    {
        $this->nombre = $nombre;
        $this->descripcion = $descripcion;
        $this->precio = $precio;
        $this->stock = $stock;
        $this->fechaAlta = new \DateTime();
        $this->imagenes = new ArrayCollection();
        $this->lineaCompras = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

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

    public function getPrecio(): ?string
    {
        return $this->precio;
    }

    public function setPrecio(string $precio): static
    {
        $this->precio = $precio;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getFechaAlta(): ?\DateTime
    {
        return $this->fechaAlta;
    }

    public function setFechaAlta(\DateTime $fechaAlta): static
    {
        $this->fechaAlta = $fechaAlta;

        return $this;
    }

    /**
     * @return Collection<int, Imagen>
     */
    public function getImagenes(): Collection
    {
        return $this->imagenes;
    }

    public function addImagen(Imagen $imagen): static
    {
        if (!$this->imagenes->contains($imagen)) {
            $this->imagenes->add($imagen);
            $imagen->setProducto($this);
        }

        return $this;
    }

    public function removeImagen(Imagen $imagen): static
    {
        if ($this->imagenes->removeElement($imagen)) {
            // set the owning side to null (unless already changed)
            if ($imagen->getProducto() === $this) {
                $imagen->setProducto(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LineaCompra>
     */
    public function getLineaCompras(): Collection
    {
        return $this->lineaCompras;
    }

    public function addLineaCompra(LineaCompra $lineaCompra): static
    {
        if (!$this->lineaCompras->contains($lineaCompra)) {
            $this->lineaCompras->add($lineaCompra);
            $lineaCompra->setProducto($this);
        }

        return $this;
    }

    public function removeLineaCompra(LineaCompra $lineaCompra): static
    {
        if ($this->lineaCompras->removeElement($lineaCompra)) {
            // set the owning side to null (unless already changed)
            if ($lineaCompra->getProducto() === $this) {
                $lineaCompra->setProducto(null);
            }
        }

        return $this;
    }
}
