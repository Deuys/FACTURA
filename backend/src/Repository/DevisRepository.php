<?php

namespace App\Repository;

use App\Entity\Devis;
use App\Entity\User;
use App\Enum\StatutDevis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Devis>
 */
class DevisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Devis::class);
    }

    /**
     * Recherche, filtre, trie et pagine les devis d'un utilisateur.
     *
     * @return array{devis: Devis[], total: int}
     */
    public function findForUserWithFilters(
        User $user,
        ?string $recherche = null,
        ?StatutDevis $statut = null,
        string $tri = 'dateEmission',
        string $ordre = 'DESC',
        int $page = 1,
        int $limit = 20
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.client', 'c')
            ->addSelect('c')
            ->andWhere('d.user = :user')
            ->setParameter('user', $user);

        if ($recherche !== null && trim($recherche) !== '') {
            $recherche = trim($recherche);

            $qb
                ->andWhere(
                    'LOWER(d.numero) LIKE LOWER(:recherche)
                    OR LOWER(c.nom) LIKE LOWER(:recherche)
                    OR LOWER(c.prenom) LIKE LOWER(:recherche)
                    OR LOWER(c.entreprise) LIKE LOWER(:recherche)'
                )
                ->setParameter('recherche', '%' . $recherche . '%');
        }

        if ($statut !== null) {
            $qb
                ->andWhere('d.statut = :statut')
                ->setParameter('statut', $statut);
        }

        $trisAutorises = [
            'numero' => 'd.numero',
            'dateEmission' => 'd.dateEmission',
            'dateValidite' => 'd.dateValidite',
            'totalHT' => 'd.totalHT',
            'totalTTC' => 'd.totalTTC',
            'statut' => 'd.statut',
            'client' => 'c.nom',
        ];

        $champTri = $trisAutorises[$tri] ?? 'd.dateEmission';
        $ordre = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $total = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $devis = $qb
            ->orderBy($champTri, $ordre)
            ->addOrderBy('d.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'devis' => $devis,
            'total' => $total,
        ];
    }
}
