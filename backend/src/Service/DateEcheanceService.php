<?php

namespace App\Service;

use App\Enum\TypeDelaiPaiement;

final class DateEcheanceService
{
    public function calculate(
        \DateTimeImmutable $dateEmission,
        TypeDelaiPaiement $type,
        int $nombreJours
    ): \DateTimeImmutable {
        if ($nombreJours < 0) {
            throw new \InvalidArgumentException(
                'Le nombre de jours ne peut pas être négatif.'
            );
        }

        return match ($type) {
            TypeDelaiPaiement::COMPTANT,
            TypeDelaiPaiement::RECEPTION =>
            $dateEmission,

            TypeDelaiPaiement::JOURS_NETS =>
            $dateEmission->modify(
                sprintf('+%d days', $nombreJours)
            ),

            TypeDelaiPaiement::FIN_DE_MOIS =>
            $dateEmission->modify('last day of this month'),

            TypeDelaiPaiement::JOURS_FIN_DE_MOIS =>
            $dateEmission
                ->modify('last day of this month')
                ->modify(
                    sprintf('+%d days', $nombreJours)
                ),

            TypeDelaiPaiement::PERSONNALISE =>
            throw new \LogicException(
                'Une date d’échéance explicite est requise.'
            ),
        };
    }
}
