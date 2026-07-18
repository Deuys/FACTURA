<?php

namespace App\Enum;

enum OriginePaiement: string
{
    case MANUEL = 'manuel';
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case MOLLIE = 'mollie';
}