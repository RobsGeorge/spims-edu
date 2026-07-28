<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Paypal = 'PAYPAL';
    case Paymob = 'PAYMOB';
    case Cashier = 'CASHIER';
    case WalletMoney = 'WALLET_MONEY';
    case WalletPoints = 'WALLET_POINTS';
    case ManualCash = 'MANUAL_CASH';
    case ManualTransfer = 'MANUAL_TRANSFER';
    case ManualCheque = 'MANUAL_CHEQUE';
}
