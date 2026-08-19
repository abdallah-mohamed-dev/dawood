<?php

namespace App\Enums;

enum CashboxTransactionType: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'داخل',
            self::Out => 'خارج',
        };
    }
}
