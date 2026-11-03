<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

// Producto que un usuario ha marcado con el corazón
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_favorito', columns: ['usuario_id', 'producto_id'])]
class Favorito
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Producto $producto = null;

    public function __construct(Usuario $usuario, Producto $producto)
    {
        $this->usuario = $usuario;
        $this->producto = $producto;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }
}
