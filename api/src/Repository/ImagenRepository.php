<?php

namespace App\Repository;

use App\Entity\Imagen;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Imagen>
 */
class ImagenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Imagen::class);
    }

    public function add(Imagen $entidad): void
    {
        $this->getEntityManager()->persist($entidad);
        $this->getEntityManager()->flush();
    }

    public function remove(Imagen $entidad): void
    {
        $this->getEntityManager()->remove($entidad);
        $this->getEntityManager()->flush();
    }
}
