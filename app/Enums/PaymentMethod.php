<?php

namespace App\Enums;

/**
 * Payments are not processed: the method is recorded so the client can show it.
 */
enum PaymentMethod: string
{
    case CashOnService = 'cash_on_service';
    case DemoWallet = 'demo_wallet';
}
