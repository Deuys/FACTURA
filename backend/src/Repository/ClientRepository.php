<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\StatutFacture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    private const SORT_FIELDS = [
        'nom' => 'c.nom',
        'entreprise' => 'c.entreprise',
        'createdAt' => 'c.createdAt',
        'ville' => 'c.ville',
    ];

    private const ALLOWED_FILTERS = [
        'tous',
        'nouveaux',
        'a_jour',
        'en_attente',
        'en_retard',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * @return Client[]
     */
    public function findForUserWithFilters(
        User $user,
        ?string $recherche = null,
        string $filtre = 'tous',
        string $tri = 'nom',
        string $ordre = 'ASC'
    ): array {
        $ordre = strtoupper($ordre) === 'DESC' ? 'DESC' : 'ASC';

        $champTri = self::SORT_FIELDS[$tri]
            ?? self::SORT_FIELDS['nom'];

        $queryBuilder = $this
            ->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user);

        $this->applySearch($queryBuilder, $recherche);
        $this->applyFilter($queryBuilder, $filtre);

        $queryBuilder->orderBy($champTri, $ordre);

        if ($tri !== 'nom') {
            $queryBuilder->addOrderBy('c.nom', 'ASC');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    private function applySearch(
        QueryBuilder $queryBuilder,
        ?string $recherche
    ): void {
        $recherche = trim((string) $recherche);

        if ($recherche === '') {
            return;
        }

        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->orX(
                    'LOWER(c.nom) LIKE :recherche',
                    'LOWER(c.prenom) LIKE :recherche',
                    'LOWER(c.entreprise) LIKE :recherche',
                    'LOWER(c.email) LIKE :recherche',
                    'LOWER(c.telephone) LIKE :recherche',
                    'LOWER(c.ville) LIKE :recherche'
                )
            )
            ->setParameter(
                'recherche',
                '%' . mb_strtolower($recherche) . '%'
            );
    }

    private function applyFilter(
        QueryBuilder $queryBuilder,
        string $filtre
    ): void {
        match ($filtre) {
            'nouveaux' => $this->applyNewFilter($queryBuilder),
            'a_jour' => $this->applyUpToDateFilter($queryBuilder),
            'en_attente' => $this->applyPendingFilter($queryBuilder),
            'en_retard' => $this->applyLateFilter($queryBuilder),
            default => null,
        };
    }

    private function applyNewFilter(QueryBuilder $queryBuilder): void
    {
        $queryBuilder
            ->andWhere('c.createdAt >= :dateNouveaux')
            ->setParameter(
                'dateNouveaux',
                new \DateTimeImmutable('-30 days')
            );
    }

    /**
     * Client ne possédant aucune facture impayée arrivée à échéance.
     */
    private function applyUpToDateFilter(QueryBuilder $queryBuilder): void
    {
        $subQuery = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\Facture', 'f_up_to_date')
            ->where('f_up_to_date.client = c')
            ->andWhere('f_up_to_date.dateEcheance < :today')
            ->andWhere('f_up_to_date.statut != :statutPayee');

        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->not(
                    $queryBuilder->expr()->exists(
                        $subQuery->getDQL()
                    )
                )
            )
            ->setParameter(
                'today',
                new \DateTimeImmutable('today')
            )
            ->setParameter(
                'statutPayee',
                StatutFacture::PAYEE
            );
    }

    /**
     * Client possédant au moins une facture non payée,
     * dont l’échéance n’est pas dépassée.
     */
    private function applyPendingFilter(QueryBuilder $queryBuilder): void
    {
        $subQuery = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\Facture', 'f_pending')
            ->where('f_pending.client = c')
            ->andWhere('f_pending.dateEcheance >= :today')
            ->andWhere('f_pending.statut != :statutPayee')
            ->andWhere('f_pending.statut != :statutBrouillon');

        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->exists(
                    $subQuery->getDQL()
                )
            )
            ->setParameter(
                'today',
                new \DateTimeImmutable('today')
            )
            ->setParameter(
                'statutPayee',
                StatutFacture::PAYEE
            )
            ->setParameter(
                'statutBrouillon',
                StatutFacture::BROUILLON
            );
    }

    /**
     * Client possédant au moins une facture impayée
     * dont l’échéance est dépassée.
     */
    private function applyLateFilter(QueryBuilder $queryBuilder): void
    {
        $subQuery = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\Facture', 'f_late')
            ->where('f_late.client = c')
            ->andWhere('f_late.dateEcheance < :today')
            ->andWhere('f_late.statut != :statutPayee');

        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->exists(
                    $subQuery->getDQL()
                )
            )
            ->setParameter(
                'today',
                new \DateTimeImmutable('today')
            )
            ->setParameter(
                'statutPayee',
                StatutFacture::PAYEE
            );
    }

    public function isSortFieldAllowed(string $tri): bool
    {
        return array_key_exists($tri, self::SORT_FIELDS);
    }

    public function isFilterAllowed(string $filtre): bool
    {
        return in_array(
            $filtre,
            self::ALLOWED_FILTERS,
            true
        );
    }

    /**
     * @return string[]
     */
    public function getAllowedSortFields(): array
    {
        return array_keys(self::SORT_FIELDS);
    }

    /**
     * @return string[]
     */
    public function getAllowedFilters(): array
    {
        return self::ALLOWED_FILTERS;
    }
}
