<?php

namespace App\Repository;

use App\Entity\Producto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Producto>
 */
class ProductoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Producto::class);
    }

    public function add(Producto $entidad): void
    {
        $this->getEntityManager()->persist($entidad);
        $this->getEntityManager()->flush();
    }

    public function remove(Producto $entidad): void
    {
        $this->getEntityManager()->remove($entidad);
        $this->getEntityManager()->flush();
    }
}
