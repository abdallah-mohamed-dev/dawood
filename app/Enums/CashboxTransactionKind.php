<?php

namespace App\Enums;

enum CashboxTransactionKind: string
{
    case OpeningBalance = 'opening_balance';
    case CustomerPayment = 'customer_payment';
    case InventoryPurchase = 'inventory_purchase';
    case Expense = 'expense';
    case PartnerWithdrawal = 'partner_withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'رصيد افتتاحي',
            self::CustomerPayment => 'دفعة عميل',
            self::InventoryPurchase => 'شراء مخزون',
            self::Expense => 'مصروف إداري',
            self::PartnerWithdrawal => 'سحب شريك',
        };
    }
}
