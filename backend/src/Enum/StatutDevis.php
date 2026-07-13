<?php

namespace App\Enum;

enum StatutDevis: string
{
    case BROUILLON = 'Brouillon';
    case EN_ATTENTE = 'En attente';
    case ACCEPTE = 'Accepté';
    case REFUSE = 'Refusé';
    case EXPIRE = 'Expiré';
}
