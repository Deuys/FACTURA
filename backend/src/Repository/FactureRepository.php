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
    private const SORT_FIELDS = [
        'numero' => 'f.numero',
        'dateEmission' => 'f.dateEmission',
        'dateEcheance' => 'f.dateEcheance',
        'totalTTC' => 'f.totalTTC',
        'statut' => 'f.statut',
        'client' => 'c.nom',
    ];

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
        ?string $recherche = null,
        string $tri = 'dateEmission',
        string $ordre = 'DESC',
        ?bool $archivee = false
    ): array {
        $ordre = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $champTri = self::SORT_FIELDS[$tri]
            ?? self::SORT_FIELDS['dateEmission'];

        $queryBuilder = $this
            ->createQueryBuilder('f')
            ->leftJoin('f.client', 'c')
            ->addSelect('c')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user);

        if ($archivee !== null) {
            $queryBuilder
                ->andWhere('f.archivee = :archivee')
                ->setParameter('archivee', $archivee);
        }

        if ($statut !== null) {
            $queryBuilder
                ->andWhere('f.statut = :statut')
                ->setParameter('statut', $statut);
        }

        $recherche = trim((string) $recherche);

        if ($recherche !== '') {
            $conditions = [
                'LOWER(f.numero) LIKE :recherche',
                'LOWER(c.nom) LIKE :recherche',
                'LOWER(c.prenom) LIKE :recherche',
                'LOWER(c.entreprise) LIKE :recherche',
                'LOWER(f.commentaire) LIKE :recherche',
            ];

            $queryBuilder->setParameter(
                'recherche',
                '%' . mb_strtolower($recherche) . '%'
            );

            /*
             * Lorsqu'une valeur numérique est saisie, on recherche aussi
             * une correspondance exacte sur le montant TTC.
             *
             * Exemples acceptés :
             * 9648
             * 9648.00
             * 9648,00
             */
            $montantRecherche = str_replace(
                [' ', ','],
                ['', '.'],
                $recherche
            );

            if (is_numeric($montantRecherche)) {
                $conditions[] = 'f.totalTTC = :montantRecherche';

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

        /*
         * En cas de valeurs identiques, ce second tri garantit
         * un ordre stable et prévisible.
         */
        if ($tri !== 'dateEmission') {
            $queryBuilder->addOrderBy('f.dateEmission', 'DESC');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * Indique si un champ de tri est autorisé.
     */
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
