<?php

namespace App\Repository;

use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Usuario>
 */
class UsuarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Usuario::class);
    }

    public function add(Usuario $entidad): void
    {
        $this->getEntityManager()->persist($entidad);
        $this->getEntityManager()->flush();
    }

    public function remove(Usuario $entidad): void
    {
        $this->getEntityManager()->remove($entidad);
        $this->getEntityManager()->flush();
    }
}
