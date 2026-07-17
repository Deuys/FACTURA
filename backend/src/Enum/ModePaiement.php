<?php

namespace App\Enum;

enum ModePaiement: string
{
    case CARTE = 'Carte bancaire';
    case VIREMENT = 'Virement bancaire';
    case ESPECES = 'Espèces';
    case CHEQUE = 'Chèque';
    case PRELEVEMENT = 'Prélèvement bancaire';
    case PAYPAL = 'PayPal';
    case AUTRE = 'Autre';
}