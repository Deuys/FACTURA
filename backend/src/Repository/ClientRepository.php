<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\StatutFacture;
use App\Entity\Facture;
use App\Entity\Paiement;
use App\Enum\StatutPaiement;
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
        'archives',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * @return array{
     *     clients: Client[],
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
        $ordre = strtoupper($ordre) === 'DESC' ? 'DESC' : 'ASC';

        $champTri = self::SORT_FIELDS[$tri]
            ?? self::SORT_FIELDS['nom'];

        $page = max(1, $page);
        $limit = max(1, min($limit, 100));

        $queryBuilder = $this
            ->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user);

        $this->applyArchiveFilter($queryBuilder, $filtre);
        $this->applySearch($queryBuilder, $recherche);
        $this->applyFilter($queryBuilder, $filtre);

        /*
     * On clone la requête avant d'ajouter le tri et la pagination.
     * Cette requête sert uniquement à compter les résultats.
     */
        $countQueryBuilder = clone $queryBuilder;

        $total = (int) $countQueryBuilder
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $queryBuilder
            ->orderBy($champTri, $ordre)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($tri !== 'nom') {
            $queryBuilder->addOrderBy('c.nom', 'ASC');
        }

        /** @var Client[] $clients */
        $clients = $queryBuilder
            ->getQuery()
            ->getResult();

        return [
            'clients' => $clients,
            'total' => $total,
        ];
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

    private function applyArchiveFilter(
        QueryBuilder $queryBuilder,
        string $filtre
    ): void {
        $queryBuilder
            ->andWhere('c.archivee = :clientArchivee')
            ->setParameter(
                'clientArchivee',
                $filtre === 'archives'
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
            ->andWhere('f_up_to_date.statut != :statutPayee')
            ->andWhere('f_up_to_date.archivee = :factureArchivee');

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
            )
            ->setParameter(
                'factureArchivee',
                false
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
            ->andWhere('f_pending.statut != :statutBrouillon')
            ->andWhere('f_pending.archivee = :factureArchivee');

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
            )
            ->setParameter(
                'factureArchivee',
                false
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
            ->andWhere('f_late.statut != :statutPayee')
            ->andWhere('f_late.archivee = :factureArchivee');

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
                'factureArchivee',
                false
            );
    }

    /**
     * @param Client[] $clients
     *
     * @return array<int, array{
     *     nombreFactures: int,
     *     chiffreAffaires: string,
     *     montantEnCours: string,
     *     statut: string
     * }>
     */
    public function getStatisticsForClients(
        User $user,
        array $clients
    ): array {
        $clientIds = [];

        foreach ($clients as $client) {
            $clientId = $client->getId();

            if ($clientId !== null) {
                $clientIds[] = $clientId;
            }
        }

        $clientIds = array_values(array_unique($clientIds));

        if ($clientIds === []) {
            return [];
        }

        $nombreFacturesByClient = [];
        $totalEnCoursByClient = [];
        $chiffreAffairesByClient = [];
        $paiementsEnCoursByClient = [];
        $statutsByClient = [];

        $statutsEnCours = [
            StatutFacture::EN_ATTENTE->value,
            StatutFacture::ENVOYEE->value,
            StatutFacture::EN_RETARD->value,
            StatutFacture::PARTIELLEMENT_PAYEE->value,
        ];

        $factureRows = $this->getEntityManager()
            ->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->select('IDENTITY(f.client) AS clientId')
            ->addSelect('COUNT(f.id) AS nombreFactures')
            ->addSelect(
                'COALESCE(
                SUM(
                    CASE
                        WHEN f.statut IN (:statutsEnCours)
                        THEN f.totalTTC
                        ELSE 0
                    END
                ),
                0
            ) AS totalEnCours'
            )
            ->andWhere('f.user = :user')
            ->andWhere('f.client IN (:clientIds)')
            ->andWhere('f.archivee = :archivee')
            ->setParameter('user', $user)
            ->setParameter('clientIds', $clientIds)
            ->setParameter('archivee', false)
            ->setParameter('statutsEnCours', $statutsEnCours)
            ->groupBy('f.client')
            ->getQuery()
            ->getArrayResult();

        foreach ($factureRows as $row) {
            $clientId = (int) $row['clientId'];

            $nombreFacturesByClient[$clientId] = (int) $row['nombreFactures'];
            $totalEnCoursByClient[$clientId] = (float) $row['totalEnCours'];
        }

        $chiffreAffairesRows = $this->getEntityManager()
            ->getRepository(Paiement::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.facture', 'f')
            ->select('IDENTITY(f.client) AS clientId')
            ->addSelect('COALESCE(SUM(p.montant), 0) AS chiffreAffaires')
            ->andWhere('f.user = :user')
            ->andWhere('f.client IN (:clientIds)')
            ->andWhere('f.archivee = :archivee')
            ->andWhere('p.statut = :statutConfirme')
            ->setParameter('user', $user)
            ->setParameter('clientIds', $clientIds)
            ->setParameter('archivee', false)
            ->setParameter('statutConfirme', StatutPaiement::CONFIRME)
            ->groupBy('f.client')
            ->getQuery()
            ->getArrayResult();

        foreach ($chiffreAffairesRows as $row) {
            $clientId = (int) $row['clientId'];

            $chiffreAffairesByClient[$clientId] = (float) $row['chiffreAffaires'];
        }

        $paiementsEnCoursRows = $this->getEntityManager()
            ->getRepository(Paiement::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.facture', 'f')
            ->select('IDENTITY(f.client) AS clientId')
            ->addSelect('COALESCE(SUM(p.montant), 0) AS paiementsEnCours')
            ->andWhere('f.user = :user')
            ->andWhere('f.client IN (:clientIds)')
            ->andWhere('f.archivee = :archivee')
            ->andWhere('f.statut IN (:statutsEnCours)')
            ->andWhere('p.statut = :statutConfirme')
            ->setParameter('user', $user)
            ->setParameter('clientIds', $clientIds)
            ->setParameter('archivee', false)
            ->setParameter('statutsEnCours', $statutsEnCours)
            ->setParameter('statutConfirme', StatutPaiement::CONFIRME)
            ->groupBy('f.client')
            ->getQuery()
            ->getArrayResult();

        foreach ($paiementsEnCoursRows as $row) {
            $clientId = (int) $row['clientId'];

            $paiementsEnCoursByClient[$clientId] = (float) $row['paiementsEnCours'];
        }

        $statutRows = $this->getEntityManager()
            ->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->select('IDENTITY(f.client) AS clientId')
            ->addSelect('f.statut AS statut')
            ->andWhere('f.user = :user')
            ->andWhere('f.client IN (:clientIds)')
            ->andWhere('f.archivee = :archivee')
            ->setParameter('user', $user)
            ->setParameter('clientIds', $clientIds)
            ->setParameter('archivee', false)
            ->getQuery()
            ->getArrayResult();

        foreach ($statutRows as $row) {
            $clientId = (int) $row['clientId'];
            $statut = $row['statut'];

            if ($statut instanceof StatutFacture) {
                $statut = $statut->value;
            }

            $statutsByClient[$clientId][] = (string) $statut;
        }

        $statutsEnAttente = [
            StatutFacture::EN_ATTENTE->value,
            StatutFacture::ENVOYEE->value,
            StatutFacture::PARTIELLEMENT_PAYEE->value,
        ];

        $statistics = [];

        foreach ($clientIds as $clientId) {
            $statuts = $statutsByClient[$clientId] ?? [];

            $estEnRetard = in_array(
                StatutFacture::EN_RETARD->value,
                $statuts,
                true
            );

            $estEnAttente = count(
                array_intersect($statuts, $statutsEnAttente)
            ) > 0;

            $totalEnCours = $totalEnCoursByClient[$clientId] ?? 0;
            $paiementsEnCours = $paiementsEnCoursByClient[$clientId] ?? 0;

            $statistics[$clientId] = [
                'nombreFactures' => $nombreFacturesByClient[$clientId] ?? 0,
                'chiffreAffaires' => number_format(
                    $chiffreAffairesByClient[$clientId] ?? 0,
                    2,
                    '.',
                    ''
                ),
                'montantEnCours' => number_format(
                    max(0, $totalEnCours - $paiementsEnCours),
                    2,
                    '.',
                    ''
                ),
                'statut' => $estEnRetard
                    ? 'En retard'
                    : ($estEnAttente ? 'En attente' : 'À jour'),
            ];
        }

        return $statistics;
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
