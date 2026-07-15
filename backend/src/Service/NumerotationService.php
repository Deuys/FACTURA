<?php

namespace App\Service;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\User;
use App\Repository\DevisRepository;
use App\Repository\FactureRepository;

final class NumerotationService
{
    public function __construct(
        private readonly FactureRepository $factureRepository,
        private readonly DevisRepository $devisRepository
    ) {}

    public function genererNumeroFacture(User $user): string
    {
        $annee = (int) date('Y');
        $prefixe = $user->getEntreprise()?->getPrefixeFacture() ?? 'FAC';

        $dernierNumero = $this->trouverDernierNumeroFacture(
            $user,
            $prefixe,
            $annee
        );

        return $this->genererNumeroSuivant(
            $dernierNumero,
            $prefixe,
            $annee
        );
    }

    public function genererNumeroDevis(User $user): string
    {
        $annee = (int) date('Y');
        $prefixe = $user->getEntreprise()?->getPrefixeDevis() ?? 'DV';

        $dernierNumero = $this->trouverDernierNumeroDevis(
            $user,
            $prefixe,
            $annee
        );

        return $this->genererNumeroSuivant(
            $dernierNumero,
            $prefixe,
            $annee
        );
    }

    private function trouverDernierNumeroFacture(
        User $user,
        string $prefixe,
        int $annee
    ): ?string {
        /** @var Facture|null $facture */
        $facture = $this->factureRepository
            ->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.numero LIKE :format')
            ->setParameter('user', $user)
            ->setParameter('format', sprintf('%s-%d-%%', $prefixe, $annee))
            ->orderBy('f.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $facture?->getNumero();
    }

    private function trouverDernierNumeroDevis(
        User $user,
        string $prefixe,
        int $annee
    ): ?string {
        /** @var Devis|null $devis */
        $devis = $this->devisRepository
            ->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.numero LIKE :format')
            ->setParameter('user', $user)
            ->setParameter('format', sprintf('%s-%d-%%', $prefixe, $annee))
            ->orderBy('d.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $devis?->getNumero();
    }

    private function genererNumeroSuivant(
        ?string $dernierNumero,
        string $prefixe,
        int $annee
    ): string {
        $prochainCompteur = 1;

        if ($dernierNumero !== null) {
            $parties = explode('-', $dernierNumero);
            $dernierCompteur = (int) end($parties);
            $prochainCompteur = $dernierCompteur + 1;
        }

        return sprintf(
            '%s-%d-%06d',
            strtoupper(trim($prefixe)),
            $annee,
            $prochainCompteur
        );
    }
}
