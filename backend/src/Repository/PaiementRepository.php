<?php

namespace App\Repository;

use App\Entity\Paiement;
use App\Entity\User;
use App\Enum\ModePaiement;
use App\Enum\StatutPaiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paiement>
 */
class PaiementRepository extends ServiceEntityRepository
{
    private const SORT_FIELDS = [
        'datePaiement' => 'p.datePaiement',
        'montant' => 'p.montant',
        'modePaiement' => 'p.modePaiement',
        'statut' => 'p.statut',
        'reference' => 'p.reference',
        'facture' => 'f.numero',
        'client' => 'c.nom',
        'createdAt' => 'p.createdAt',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

    /**
     * @return Paiement[]
     */
    public function findForUserWithFilters(
        User $user,
        ?string $recherche = null,
        ?ModePaiement $modePaiement = null,
        ?StatutPaiement $statut = null,
        string $tri = 'datePaiement',
        string $ordre = 'DESC'
    ): array {
        $champTri = self::SORT_FIELDS[$tri]
            ?? self::SORT_FIELDS['datePaiement'];

        $ordre = strtoupper($ordre) === 'ASC'
            ? 'ASC'
            : 'DESC';

        $queryBuilder = $this
            ->createQueryBuilder('p')
            ->innerJoin('p.facture', 'f')
            ->addSelect('f')
            ->leftJoin('f.client', 'c')
            ->addSelect('c')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user);

        if ($modePaiement !== null) {
            $queryBuilder
                ->andWhere('p.modePaiement = :modePaiement')
                ->setParameter('modePaiement', $modePaiement);
        }

        if ($statut !== null) {
            $queryBuilder
                ->andWhere('p.statut = :statut')
                ->setParameter('statut', $statut);
        }

        $recherche = trim((string) $recherche);

        if ($recherche !== '') {
            $conditions = [
                'LOWER(p.reference) LIKE :recherche',
                'LOWER(p.commentaire) LIKE :recherche',
                'LOWER(f.numero) LIKE :recherche',
                'LOWER(c.nom) LIKE :recherche',
                'LOWER(c.prenom) LIKE :recherche',
                'LOWER(c.entreprise) LIKE :recherche',
            ];

            $queryBuilder->setParameter(
                'recherche',
                '%' . mb_strtolower($recherche) . '%'
            );

            $montantRecherche = str_replace(
                [' ', ','],
                ['', '.'],
                $recherche
            );

            if (is_numeric($montantRecherche)) {
                $conditions[] = 'p.montant = :montantRecherche';

                $queryBuilder->setParameter(
                    'montantRecherche',
                    number_format(
                        (float) $montantRecherche,
                        2,
                        '.',
                        ''
                    )
                );
            }

            $queryBuilder->andWhere(
                '(' . implode(' OR ', $conditions) . ')'
            );
        }

        $queryBuilder->orderBy($champTri, $ordre);

        if ($tri !== 'datePaiement') {
            $queryBuilder->addOrderBy(
                'p.datePaiement',
                'DESC'
            );
        }

        $queryBuilder->addOrderBy(
            'p.createdAt',
            'DESC'
        );

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function isSortFieldAllowed(string $tri): bool
    {
        return array_key_exists($tri, self::SORT_FIELDS);
    }

    /**
     * @return string[]
     */
    public function getAllowedSortFields(): array
    {
        return array_keys(self::SORT_FIELDS);
    }
}
