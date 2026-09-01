<?php

namespace App\Enums;

enum CashboxTransactionKind: string
{
    case OpeningBalance = 'opening_balance';
    case CustomerPayment = 'customer_payment';
    case InventoryPurchase = 'inventory_purchase';
    case Expense = 'expense';
    case PartnerWithdrawal = 'partner_withdrawal';
    case RoomLabor = 'room_labor';
    case RoomExpense = 'room_expense';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'رصيد افتتاحي',
            self::CustomerPayment => 'دفعة عميل',
            self::InventoryPurchase => 'شراء مخزون',
            self::Expense => 'مصروف إداري',
            self::PartnerWithdrawal => 'سحب شريك',
            self::RoomLabor => 'مصنعية غرفة',
            self::RoomExpense => 'مصروف غرفة',
        };
    }
}
