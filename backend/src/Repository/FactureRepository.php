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

    /**
     * @return Facture[]
     */
    public function findFacturesAMettreEnRetard(
        \DateTimeImmutable $aujourdhui
    ): array {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.client', 'c')
            ->addSelect('c')
            ->leftJoin('f.user', 'u')
            ->addSelect('u')
            ->andWhere('f.archivee = :archivee')
            ->andWhere('f.dateEcheance < :aujourdhui')
            ->andWhere('f.statut IN (:statuts)')
            ->setParameter('archivee', false)
            ->setParameter('aujourdhui', $aujourdhui)
            ->setParameter('statuts', [
                StatutFacture::EN_ATTENTE->value,
                StatutFacture::ENVOYEE->value,
                StatutFacture::PARTIELLEMENT_PAYEE->value,
            ])
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Facture[]
     */
    public function findFacturesARelancer(
        \DateTimeImmutable $aujourdhui,
        int $delaiEntreRelances = 7,
        int $nombreMaximumRelances = 3
    ): array {
        $dateLimiteDerniereRelance = $aujourdhui->modify(
            sprintf('-%d days', $delaiEntreRelances)
        );

        return $this->createQueryBuilder('f')
            ->leftJoin('f.client', 'c')
            ->addSelect('c')
            ->leftJoin('f.user', 'u')
            ->addSelect('u')
            ->andWhere('f.archivee = :archivee')
            ->andWhere('f.statut = :statut')
            ->andWhere('f.dateEcheance < :aujourdhui')
            ->andWhere('f.nombreRelances < :nombreMaximumRelances')
            ->andWhere('c.email IS NOT NULL')
            ->andWhere("TRIM(c.email) != ''")
            ->andWhere(
                'f.derniereRelanceAt IS NULL
            OR f.derniereRelanceAt <= :dateLimiteDerniereRelance'
            )
            ->setParameter('archivee', false)
            ->setParameter(
                'statut',
                StatutFacture::EN_RETARD->value
            )
            ->setParameter('aujourdhui', $aujourdhui)
            ->setParameter(
                'nombreMaximumRelances',
                $nombreMaximumRelances
            )
            ->setParameter(
                'dateLimiteDerniereRelance',
                $dateLimiteDerniereRelance
            )
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
