<?php

namespace App\Enum;

enum StatutPaiement: string
{
    case EN_ATTENTE = 'En attente';
    case CONFIRME = 'Confirmé';
    case ECHOUE = 'Échoué';
    case REMBOURSE = 'Remboursé';
    case ANNULE = 'Annulé';
}