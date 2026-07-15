<?php

namespace App\Enum;

enum StatutPaiement: string
{
    case EN_ATTENTE = 'En attente';
    case VALIDE = 'Validé';
    case REJETE = 'Rejeté';
}