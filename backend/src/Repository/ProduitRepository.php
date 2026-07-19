<?php

namespace App\Repository;

use App\Entity\Produit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    private const SORT_FIELDS = [
        'nom' => 'p.nom',
        'reference' => 'p.reference',
        'type' => 'p.type',
        'prixHT' => 'p.prixHT',
        'tva' => 'p.tva',
        'createdAt' => 'p.createdAt',
    ];

    private const ALLOWED_FILTERS = [
        'tous',
        'produits',
        'services',
        'actifs',
        'inactifs',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * @return array{
     *     produits: Produit[],
     *     total: int
     * }
     */
    public function findForUserWithFilters(
        User $user,
        ?string $recherche = null,
        string $filtre = 'tous',
        string $tri = 'nom',
        string $ordre = 'ASC',
        int $page = 1,
        int $limit = 20
    ): array {
        $champTri = self::SORT_FIELDS[$tri]
            ?? self::SORT_FIELDS['nom'];

        $ordre = strtoupper($ordre) === 'DESC'
            ? 'DESC'
            : 'ASC';

        $page = max(1, $page);
        $limit = max(1, min($limit, 100));

        $queryBuilder = $this
            ->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user);

        $this->applySearch($queryBuilder, $recherche);
        $this->applyFilter($queryBuilder, $filtre);

        $countQueryBuilder = clone $queryBuilder;

        $total = (int) $countQueryBuilder
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $queryBuilder
            ->orderBy($champTri, $ordre)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($tri !== 'nom') {
            $queryBuilder->addOrderBy('p.nom', 'ASC');
        }

        /** @var Produit[] $produits */
        $produits = $queryBuilder
            ->getQuery()
            ->getResult();

        return [
            'produits' => $produits,
            'total' => $total,
        ];
    }

    public function referenceExistsForUser(
        User $user,
        string $reference,
        ?int $excludedProductId = null
    ): bool {
        $queryBuilder = $this
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.user = :user')
            ->andWhere('LOWER(p.reference) = :reference')
            ->setParameter('user', $user)
            ->setParameter('reference', mb_strtolower($reference));

        if ($excludedProductId !== null) {
            $queryBuilder
                ->andWhere('p.id != :excludedProductId')
                ->setParameter('excludedProductId', $excludedProductId);
        }

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult() > 0;
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
                    'LOWER(p.nom) LIKE :recherche',
                    'LOWER(p.description) LIKE :recherche',
                    'LOWER(p.reference) LIKE :recherche',
                    'LOWER(p.unite) LIKE :recherche'
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
            'produits' => $queryBuilder
                ->andWhere('LOWER(p.type) = :typeProduit')
                ->setParameter('typeProduit', 'produit'),

            'services' => $queryBuilder
                ->andWhere('LOWER(p.type) = :typeService')
                ->setParameter('typeService', 'service'),

            'actifs' => $queryBuilder
                ->andWhere('p.actif = :actif')
                ->setParameter('actif', true),

            'inactifs' => $queryBuilder
                ->andWhere('p.actif = :actif')
                ->setParameter('actif', false),

            default => null,
        };
    }

    public function isSortFieldAllowed(string $tri): bool
    {
        return array_key_exists($tri, self::SORT_FIELDS);
    }

    public function isFilterAllowed(string $filtre): bool
    {
        return in_array($filtre, self::ALLOWED_FILTERS, true);
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
