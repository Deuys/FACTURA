<?php

namespace App\Repository;

use App\Entity\Facture;
use App\Entity\User;
use App\Enum\StatutFacture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /**
     * @return Facture[]
     */
    public function findForUserWithFilters(
        User $user,
        ?StatutFacture $statut = null,
        string $ordre = 'DESC'
    ): array {
        $ordre = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $queryBuilder = $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.dateEmission', $ordre);

        if ($statut !== null) {
            $queryBuilder
                ->andWhere('f.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
