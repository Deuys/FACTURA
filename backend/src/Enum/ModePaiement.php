<?php

namespace App\Enum;

enum ModePaiement: string
{
    case CARTE = 'Carte bancaire';
    case VIREMENT = 'Virement';
    case ESPECES = 'Espèces';
    case CHEQUE = 'Chèque';
}
