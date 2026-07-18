<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\Paiement;
use App\Entity\Produit;
use App\Entity\User;
use App\Enum\StatutDevis;
use App\Enum\StatutFacture;
use App\Enum\StatutPaiement;
use App\Entity\Activite;
use App\Repository\ActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiviteRepository $activiteRepository
    ) {}

    public function getDashboard(User $user): array
    {
        $factureRepository = $this->entityManager
            ->getRepository(Facture::class);

        $facturesPayees = $factureRepository->count([
            'user' => $user,
            'statut' => StatutFacture::PAYEE,
        ]);

        $facturesEnAttente = $factureRepository->count([
            'user' => $user,
            'statut' => StatutFacture::EN_ATTENTE,
        ]);

        $facturesEnRetard = $factureRepository->count([
            'user' => $user,
            'statut' => StatutFacture::EN_RETARD,
        ]);

        $devisEnAttente = $this->entityManager
            ->getRepository(Devis::class)
            ->count([
                'user' => $user,
                'statut' => StatutDevis::EN_ATTENTE,
            ]);

        $debutDuMois = new \DateTimeImmutable(
            'first day of this month 00:00:00'
        );

        $debutMoisSuivant = $debutDuMois->modify('+1 month');

        $nouveauxClients = (int) $this->entityManager
            ->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.user = :user')
            ->andWhere('c.createdAt >= :debutDuMois')
            ->andWhere('c.createdAt < :debutMoisSuivant')
            ->setParameter('user', $user)
            ->setParameter('debutDuMois', $debutDuMois)
            ->setParameter('debutMoisSuivant', $debutMoisSuivant)
            ->getQuery()
            ->getSingleScalarResult();

        $chiffreAffaires = (float) $this->entityManager
            ->getRepository(Paiement::class)
            ->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0)')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('p.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', StatutPaiement::CONFIRME)
            ->getQuery()
            ->getSingleScalarResult();

        $statutsAEncaisser = [
            StatutFacture::EN_ATTENTE,
            StatutFacture::ENVOYEE,
            StatutFacture::EN_RETARD,
        ];

        $totalFacturesAEncaisser = (float) $factureRepository
            ->createQueryBuilder('f')
            ->select('COALESCE(SUM(f.totalTTC), 0)')
            ->andWhere('f.user = :user')
            ->andWhere('f.statut IN (:statuts)')
            ->setParameter('user', $user)
            ->setParameter('statuts', $statutsAEncaisser)
            ->getQuery()
            ->getSingleScalarResult();

        $paiementsDejaRecus = (float) $this->entityManager
            ->getRepository(Paiement::class)
            ->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0)')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('f.statut IN (:statuts)')
            ->andWhere('p.statut = :statutPaiement')
            ->setParameter('user', $user)
            ->setParameter('statuts', $statutsAEncaisser)
            ->setParameter(
                'statutPaiement',
                StatutPaiement::CONFIRME
            )
            ->getQuery()
            ->getSingleScalarResult();

        $montantAEncaisser = max(
            0,
            $totalFacturesAEncaisser - $paiementsDejaRecus
        );

        return [
            'facturesPayees' => $facturesPayees,
            'facturesEnAttente' => $facturesEnAttente,
            'facturesEnRetard' => $facturesEnRetard,
            'chiffreAffaires' => number_format(
                $chiffreAffaires,
                2,
                '.',
                ''
            ),
            'montantAEncaisser' => number_format(
                $montantAEncaisser,
                2,
                '.',
                ''
            ),
            'devisEnAttente' => $devisEnAttente,
            'nouveauxClients' => $nouveauxClients,
        ];
    }

    public function getClientsDashboard(User $user): array
    {
        /** @var Client[] $clients */
        $clients = $this->entityManager
            ->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.factures', 'f')
            ->addSelect('f')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $debutDuMois = new \DateTimeImmutable(
            'first day of this month 00:00:00'
        );

        $debutMoisSuivant = $debutDuMois->modify('+1 month');

        $nouveauxClients = 0;
        $clientsEnAttente = 0;
        $clientsAJour = 0;
        $clientsEnRetard = 0;

        foreach ($clients as $client) {
            $createdAt = $client->getCreatedAt();

            if (
                $createdAt !== null
                && $createdAt >= $debutDuMois
                && $createdAt < $debutMoisSuivant
            ) {
                ++$nouveauxClients;
            }

            $possedeFactureEnRetard = false;
            $possedeFactureEnAttente = false;

            foreach ($client->getFactures() as $facture) {
                if ($facture->getStatut() === StatutFacture::EN_RETARD) {
                    $possedeFactureEnRetard = true;

                    break;
                }

                if (
                    $facture->getStatut() === StatutFacture::EN_ATTENTE
                    || $facture->getStatut() === StatutFacture::ENVOYEE
                ) {
                    $possedeFactureEnAttente = true;
                }
            }

            if ($possedeFactureEnRetard) {
                ++$clientsEnRetard;
            } elseif ($possedeFactureEnAttente) {
                ++$clientsEnAttente;
            } else {
                ++$clientsAJour;
            }
        }

        return [
            'totalClients' => count($clients),
            'nouveauxClients' => $nouveauxClients,
            'clientsEnAttente' => $clientsEnAttente,
            'clientsAJour' => $clientsAJour,
            'clientsEnRetard' => $clientsEnRetard,
        ];
    }

    public function getProduitsDashboard(User $user): array
    {
        $produitRepository = $this->entityManager
            ->getRepository(Produit::class);

        $totalProduits = $produitRepository->count([
            'user' => $user,
            'type' => 'produit',
        ]);

        $totalServices = $produitRepository->count([
            'user' => $user,
            'type' => 'service',
        ]);

        $produitsActifs = $produitRepository->count([
            'user' => $user,
            'type' => 'produit',
            'actif' => true,
        ]);

        $servicesActifs = $produitRepository->count([
            'user' => $user,
            'type' => 'service',
            'actif' => true,
        ]);

        $catalogueTotal = $produitRepository->count([
            'user' => $user,
        ]);

        return [
            'totalProduits' => $totalProduits,
            'totalServices' => $totalServices,
            'produitsActifs' => $produitsActifs,
            'servicesActifs' => $servicesActifs,
            'catalogueTotal' => $catalogueTotal,
        ];
    }

    public function getDevisDashboard(User $user): array
    {
        $devisRepository = $this->entityManager
            ->getRepository(Devis::class);

        return [
            'totalDevis' => $devisRepository->count([
                'user' => $user,
            ]),
            'devisBrouillons' => $devisRepository->count([
                'user' => $user,
                'statut' => StatutDevis::BROUILLON,
            ]),
            'devisEnAttente' => $devisRepository->count([
                'user' => $user,
                'statut' => StatutDevis::EN_ATTENTE,
            ]),
            'devisEnvoyes' => $devisRepository->count([
                'user' => $user,
                'statut' => StatutDevis::ENVOYE,
            ]),
            'devisAcceptes' => $devisRepository->count([
                'user' => $user,
                'statut' => StatutDevis::ACCEPTE,
            ]),
            'devisRefuses' => $devisRepository->count([
                'user' => $user,
                'statut' => StatutDevis::REFUSE,
            ]),
            'devisExpires' => $devisRepository->count([
                'user' => $user,
                'statut' => StatutDevis::EXPIRE,
            ]),
            'devisTransformes' => $devisRepository->count([
                'user' => $user,
                'statut' => StatutDevis::TRANSFORME,
            ]),
        ];
    }

    public function getPaiementsDashboard(User $user): array
    {
        $paiementRepository = $this->entityManager
            ->getRepository(Paiement::class);

        $debutDuMois = new \DateTimeImmutable(
            'first day of this month 00:00:00'
        );

        $debutMoisSuivant = $debutDuMois->modify('+1 month');

        $paiementsEnregistres = (int) $paiementRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $montantEncaisse = (float) $paiementRepository
            ->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0)')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('p.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', StatutPaiement::CONFIRME)
            ->getQuery()
            ->getSingleScalarResult();

        $paiementsDuMois = (int) $paiementRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('p.datePaiement >= :debutDuMois')
            ->andWhere('p.datePaiement < :debutMoisSuivant')
            ->setParameter('user', $user)
            ->setParameter('debutDuMois', $debutDuMois)
            ->setParameter('debutMoisSuivant', $debutMoisSuivant)
            ->getQuery()
            ->getSingleScalarResult();

        $paiementsEnAttente = (int) $paiementRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('p.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', StatutPaiement::EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult();

        $paiementsValides = (int) $paiementRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('p.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', StatutPaiement::CONFIRME)
            ->getQuery()
            ->getSingleScalarResult();

        $paiementMoyen = $paiementsValides > 0
            ? $montantEncaisse / $paiementsValides
            : 0;

        return [
            'paiementsEnregistres' => $paiementsEnregistres,
            'montantEncaisse' => number_format(
                $montantEncaisse,
                2,
                '.',
                ''
            ),
            'paiementsDuMois' => $paiementsDuMois,
            'paiementsEnAttente' => $paiementsEnAttente,
            'paiementMoyen' => number_format(
                $paiementMoyen,
                2,
                '.',
                ''
            ),
        ];
    }

    public function getFacturesDashboard(User $user): array
    {
        $factureRepository = $this->entityManager
            ->getRepository(Facture::class);

        $totalFactures = $factureRepository->count([
            'user' => $user,
        ]);

        $facturesPayees = $factureRepository->count([
            'user' => $user,
            'statut' => StatutFacture::PAYEE,
        ]);

        $facturesEnAttente = $factureRepository->count([
            'user' => $user,
            'statut' => StatutFacture::EN_ATTENTE,
        ]);

        $facturesEnRetard = $factureRepository->count([
            'user' => $user,
            'statut' => StatutFacture::EN_RETARD,
        ]);

        $chiffreAffairesEncaisse = (float) $this->entityManager
            ->getRepository(Paiement::class)
            ->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0)')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('p.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', StatutPaiement::CONFIRME)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalFactures' => $totalFactures,
            'facturesPayees' => $facturesPayees,
            'facturesEnAttente' => $facturesEnAttente,
            'facturesEnRetard' => $facturesEnRetard,
            'chiffreAffairesEncaisse' => number_format(
                $chiffreAffairesEncaisse,
                2,
                '.',
                ''
            ),
        ];
    }

    public function getActiviteRecente(
        User $user,
        int $limite = 5
    ): array {
        $activites = $this->activiteRepository
            ->findLatestForUser($user, $limite);

        return array_map(
            static fn(Activite $activite): array => [
                'id' => $activite->getId(),
                'type' => $activite->getType(),
                'titre' => $activite->getTitre(),
                'description' => $activite->getDescription(),
                'date' => $activite
                    ->getCreatedAt()
                    ?->format(DATE_ATOM),
            ],
            $activites
        );
    }



    public function getDernieresFactures(User $user): array
    {
        $factures = $this->entityManager
            ->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->leftJoin('f.client', 'c')
            ->addSelect('c')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $resultat = [];

        foreach ($factures as $facture) {
            $resultat[] = [
                'numero' => $facture->getNumero(),
                'client' => $facture->getClient()?->getEntreprise()
                    ?? trim(
                        ($facture->getClient()?->getPrenom() ?? '')
                            . ' '
                            . ($facture->getClient()?->getNom() ?? '')
                    ),
                'date' => $facture->getDateEmission()?->format('Y-m-d'),
                'montant' => $facture->getTotalTTC(),
                'statut' => $facture->getStatut()->value,
            ];
        }

        return $resultat;
    }

    public function getFacturesAEcheance(User $user): array
    {
        $aujourdhui = new \DateTimeImmutable('today');

        $statutsAEcheance = [
            StatutFacture::PLANIFIEE,
            StatutFacture::EN_ATTENTE,
            StatutFacture::ENVOYEE,
            StatutFacture::PARTIELLEMENT_PAYEE,
        ];

        $factures = $this->entityManager
            ->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->leftJoin('f.client', 'c')
            ->addSelect('c')
            ->andWhere('f.user = :user')
            ->andWhere('f.dateEcheance >= :aujourdhui')
            ->andWhere('f.statut IN (:statuts)')
            ->setParameter('user', $user)
            ->setParameter('aujourdhui', $aujourdhui)
            ->setParameter('statuts', $statutsAEcheance)
            ->orderBy('f.dateEcheance', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $resultat = [];

        foreach ($factures as $facture) {
            $resultat[] = [
                'numero' => $facture->getNumero(),
                'client' => $facture->getClient()?->getEntreprise()
                    ?? trim(
                        ($facture->getClient()?->getPrenom() ?? '')
                            . ' '
                            . ($facture->getClient()?->getNom() ?? '')
                    ),
                'dateEcheance' => $facture
                    ->getDateEcheance()
                    ?->format('Y-m-d'),
                'montant' => $facture->getTotalTTC(),
                'statut' => $facture->getStatut()->value,
            ];
        }

        return $resultat;
    }

    public function getEvolutionChiffreAffaires(
        User $user,
        int $annee
    ): array {
        $debutAnnee = new \DateTimeImmutable(
            sprintf('%d-01-01 00:00:00', $annee)
        );

        $debutAnneeSuivante = $debutAnnee->modify('+1 year');

        $paiements = $this->entityManager
            ->getRepository(Paiement::class)
            ->createQueryBuilder('p')
            ->select('p.montant', 'p.datePaiement')
            ->innerJoin('p.facture', 'f')
            ->andWhere('f.user = :user')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.datePaiement >= :debutAnnee')
            ->andWhere('p.datePaiement < :debutAnneeSuivante')
            ->setParameter('user', $user)
            ->setParameter('statut', StatutPaiement::CONFIRME)
            ->setParameter('debutAnnee', $debutAnnee)
            ->setParameter('debutAnneeSuivante', $debutAnneeSuivante)
            ->getQuery()
            ->getArrayResult();

        $mois = [
            1 => 'Jan',
            2 => 'Fév',
            3 => 'Mar',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juil',
            8 => 'Août',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Déc',
        ];

        $totauxMensuels = array_fill(1, 12, 0.0);

        foreach ($paiements as $paiement) {
            $datePaiement = $paiement['datePaiement'];

            if (!$datePaiement instanceof \DateTimeInterface) {
                continue;
            }

            $numeroMois = (int) $datePaiement->format('n');

            $totauxMensuels[$numeroMois] += (float) $paiement['montant'];
        }

        $resultat = [];

        foreach ($mois as $numero => $libelle) {
            $resultat[] = [
                'mois' => $libelle,
                'numeroMois' => $numero,
                'montant' => number_format(
                    $totauxMensuels[$numero],
                    2,
                    '.',
                    ''
                ),
            ];
        }

        return [
            'annee' => $annee,
            'totalAnnuel' => number_format(
                array_sum($totauxMensuels),
                2,
                '.',
                ''
            ),
            'donnees' => $resultat,
        ];
    }

    public function getRepartitionFactures(User $user): array
    {
        $factureRepository = $this->entityManager
            ->getRepository(Facture::class);

        $totalFactures = $factureRepository->count([
            'user' => $user,
        ]);

        $repartition = [];

        foreach (StatutFacture::cases() as $statut) {
            $nombre = $factureRepository->count([
                'user' => $user,
                'statut' => $statut,
            ]);

            $repartition[] = [
                'statut' => $statut->value,
                'nombre' => $nombre,
                'pourcentage' => $totalFactures > 0
                    ? round(($nombre / $totalFactures) * 100, 2)
                    : 0,
            ];
        }

        return [
            'totalFactures' => $totalFactures,
            'repartition' => $repartition,
        ];
    }
}
