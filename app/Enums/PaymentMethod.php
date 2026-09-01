<?php

namespace App\Enums;

/**
 * How a cashbox movement actually changed hands. Stored on
 * cashbox_transactions only — see docs/cashbox.md: it is a property of the
 * movement, and every business event that touches money writes exactly one
 * cashbox row, so duplicating the column onto all five source tables would
 * buy nothing but five chances to drift.
 *
 * A cheque is treated exactly like cash on the day it is recorded; the
 * system deliberately models no "awaiting clearance" state.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Wallet = 'wallet';
    case Instapay = 'instapay';
    case Cheque = 'cheque';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'كاش',
            self::Wallet => 'محفظة',
            self::Instapay => 'انستاباي',
            self::Cheque => 'شيك',
            self::Card => 'فيزا',
        };
    }
}
