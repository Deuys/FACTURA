<?php

namespace App\Enum;

enum StatutDevis: string
{
    case BROUILLON = 'Brouillon';
    case EN_ATTENTE = 'En attente';
    case ENVOYE = 'Envoyé';
    case ACCEPTE = 'Accepté';
    case REFUSE = 'Refusé';
    case EXPIRE = 'Expiré';
    case TRANSFORME = 'Transformé';
}
