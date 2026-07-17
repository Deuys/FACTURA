<?php

namespace App\Enum;

enum StatutFacture: string
{
    case BROUILLON = 'Brouillon';
    case PLANIFIEE = 'Planifiée';
    case EN_ATTENTE = 'En attente';
    case ENVOYEE = 'Envoyée';
    case PARTIELLEMENT_PAYEE = 'Partiellement payée';
    case PAYEE = 'Payée';
    case EN_RETARD = 'En retard';
}
