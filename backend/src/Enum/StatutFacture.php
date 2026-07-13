<?php

namespace App\Enum;

enum StatutFacture: string
{
    case BROUILLON = 'Brouillon';
    case EN_ATTENTE = 'En attente';
    case ENVOYEE = 'Envoyée';
    case PAYEE = 'Payée';
    case EN_RETARD = 'En retard';
}