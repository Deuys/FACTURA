<?php

namespace App\Service;

use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\LigneDevis;
use App\Entity\LigneFacture;

final class CalculTotauxService
{
    public function calculerLigneFacture(LigneFacture $ligne): void
    {
        [$totalHT, $totalTVA, $totalTTC] = $this->calculerLigne(
            (float) ($ligne->getQuantite() ?? 0),
            (float) ($ligne->getPrixUnitaireHT() ?? 0),
            (float) ($ligne->getTva() ?? 0),
            (float) ($ligne->getRemise() ?? 0)
        );

        $ligne->setTotalHT($totalHT);
        $ligne->setTotalTVA($totalTVA);
        $ligne->setTotalTTC($totalTTC);
    }

    public function calculerLigneDevis(LigneDevis $ligne): void
    {
        [$totalHT, $totalTVA, $totalTTC] = $this->calculerLigne(
            (float) ($ligne->getQuantite() ?? 0),
            (float) ($ligne->getPrixUnitaireHT() ?? 0),
            (float) ($ligne->getTva() ?? 0),
            (float) ($ligne->getRemise() ?? 0)
        );

        $ligne->setTotalHT($totalHT);
        $ligne->setTotalTVA($totalTVA);
        $ligne->setTotalTTC($totalTTC);
    }

    public function recalculerFacture(Facture $facture): void
    {
        $totalHT = 0.0;
        $totalTVA = 0.0;
        $totalTTC = 0.0;

        foreach ($facture->getLigneFactures() as $ligne) {
            $this->calculerLigneFacture($ligne);

            $totalHT += (float) ($ligne->getTotalHT() ?? 0);
            $totalTVA += (float) ($ligne->getTotalTVA() ?? 0);
            $totalTTC += (float) ($ligne->getTotalTTC() ?? 0);
        }

        $facture->setTotalHT($this->formaterMontant($totalHT));
        $facture->setTotalTVA($this->formaterMontant($totalTVA));
        $facture->setTotalTTC($this->formaterMontant($totalTTC));
    }

    public function recalculerDevis(Devis $devis): void
    {
        $totalHT = 0.0;
        $totalTVA = 0.0;
        $totalTTC = 0.0;

        foreach ($devis->getLigneDevis() as $ligne) {
            $this->calculerLigneDevis($ligne);

            $totalHT += (float) ($ligne->getTotalHT() ?? 0);
            $totalTVA += (float) ($ligne->getTotalTVA() ?? 0);
            $totalTTC += (float) ($ligne->getTotalTTC() ?? 0);
        }

        $devis->setTotalHT($this->formaterMontant($totalHT));
        $devis->setTotalTVA($this->formaterMontant($totalTVA));
        $devis->setTotalTTC($this->formaterMontant($totalTTC));
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function calculerLigne(
        float $quantite,
        float $prixUnitaireHT,
        float $tauxTva,
        float $tauxRemise
    ): array {
        $montantBrutHT = $quantite * $prixUnitaireHT;
        $montantRemise = $montantBrutHT * ($tauxRemise / 100);
        $totalHT = $montantBrutHT - $montantRemise;
        $totalTVA = $totalHT * ($tauxTva / 100);
        $totalTTC = $totalHT + $totalTVA;

        return [
            $this->formaterMontant($totalHT),
            $this->formaterMontant($totalTVA),
            $this->formaterMontant($totalTTC),
        ];
    }

    private function formaterMontant(float $montant): string
    {
        return number_format(round($montant, 2), 2, '.', '');
    }
}
