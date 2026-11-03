<?php

namespace App\Entity;

use App\Repository\ArticuloRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Artículos que no son videojuegos ni móviles: ordenadores, tablets y zona gaming.
// Las características técnicas están en producto.caracteristicas (JSON).
#[ORM\Entity(repositoryClass: ArticuloRepository::class)]
#[ORM\Index(name: 'idx_articulo_categoria', columns: ['categoria'])]
class Articulo
{
    public const CATEGORIAS = [
        'portatil' => 'Portátiles', 'sobremesa' => 'Sobremesa', 'pc_gaming' => 'PC gaming montados', 'tablet' => 'Tablets',
        'consola' => 'Consolas', 'mando' => 'Mandos', 'auriculares' => 'Auriculares', 'teclado' => 'Teclados',
        'raton' => 'Ratones', 'monitor' => 'Monitores', 'silla' => 'Sillas gaming',
    ];

    /** Categorías con garantía ampliada (igual que los móviles) */
    public const CON_GARANTIA = ['portatil', 'sobremesa', 'pc_gaming', 'tablet'];

    #[ORM\Id]
    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'producto_id', nullable: false, onDelete: 'CASCADE')]
    private ?Producto $producto = null;

    #[ORM\Column(length: 20)]
    private ?string $categoria = null;

    #[ORM\Column(length: 50)]
    private ?string $marca = null;

    // Tarifa de montaje de Jokoa (ya incluida en el precio del producto); 0 si no se monta
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, options: ['default' => '0.00'])]
    private string $montaje = '0.00';

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }

    public function setProducto(Producto $producto): static
    {
        $this->producto = $producto;

        return $this;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function setCategoria(string $categoria): static
    {
        $this->categoria = $categoria;

        return $this;
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

    public function getMontaje(): string
    {
        return $this->montaje;
    }

    public function setMontaje(string $montaje): static
    {
        $this->montaje = $montaje;

        return $this;
    }
}
