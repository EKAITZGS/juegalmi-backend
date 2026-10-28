<?php

namespace App\Repository;

use App\Entity\Ubicacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ubicacion>
 */
class UbicacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ubicacion::class);
    }

    public function add(Ubicacion $entidad): void
    {
        $this->getEntityManager()->persist($entidad);
        $this->getEntityManager()->flush();
    }

    public function remove(Ubicacion $entidad): void
    {
        $this->getEntityManager()->remove($entidad);
        $this->getEntityManager()->flush();
    }
}
