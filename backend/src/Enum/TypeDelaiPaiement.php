<?php

namespace App\Enum;

enum TypeDelaiPaiement: string
{
    case COMPTANT = 'Comptant';
    case RECEPTION = 'À réception';
    case JOURS_NETS = 'Jours nets';
    case FIN_DE_MOIS = 'Fin de mois';
    case JOURS_FIN_DE_MOIS = 'Jours fin de mois';
    case PERSONNALISE = 'Personnalisé';
}