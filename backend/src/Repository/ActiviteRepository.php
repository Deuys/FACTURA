<?php

namespace App\Repository;

use App\Entity\Activite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activite>
 */
class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    /**
     * @return Activite[]
     */
    public function findLatestForUser(
        User $user,
        int $limite = 5
    ): array {
        $limite = max(1, min($limite, 50));

        return $this
            ->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}