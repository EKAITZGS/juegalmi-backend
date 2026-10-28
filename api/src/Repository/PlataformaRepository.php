<?php

namespace App\Repository;

use App\Entity\Plataforma;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plataforma>
 */
class PlataformaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plataforma::class);
    }

    public function add(Plataforma $entidad): void
    {
        $this->getEntityManager()->persist($entidad);
        $this->getEntityManager()->flush();
    }

    public function remove(Plataforma $entidad): void
    {
        $this->getEntityManager()->remove($entidad);
        $this->getEntityManager()->flush();
    }
}
