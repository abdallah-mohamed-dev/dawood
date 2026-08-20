<?php

namespace App\Services;

use App\Enums\CashboxTransactionKind;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns partners' profit shares and their withdrawals. The share is computed
 * live from ProfitService::netProfit() (accrual profit, never a stored number):
 * share = round(netProfit_piastres × percentage / 10000) — see
 * docs/tasks.md Task 9 for the exact cross-scale formula.
 */
class PartnerService
{
    public function __construct(
        private readonly CashboxService $cashbox,
        private readonly ProfitService $profit,
    ) {}

    public function share(Partner $partner): int
    {
        $netProfit = $this->profit->netProfit();

        if ($netProfit <= 0) {
            return 0;
        }

        $numerator = $netProfit * $partner->percentage;

        return intdiv($numerator, 10_000) + (($numerator % 10_000 >= 5_000) ? 1 : 0);
    }

    public function totalWithdrawn(Partner $partner): int
    {
        return (int) PartnerWithdrawal::query()->where('partner_id', $partner->id)->sum('amount');
    }

    public function remaining(Partner $partner): int
    {
        return $this->share($partner) - $this->totalWithdrawn($partner);
    }

    public function withdraw(Partner $partner, int $amount, DateTimeInterface|string $date, ?string $note = null): PartnerWithdrawal
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be greater than zero.');
        }

        return DB::transaction(function () use ($partner, $amount, $date, $note) {
            $withdrawal = PartnerWithdrawal::query()->create([
                'partner_id' => $partner->id,
                'amount' => $amount,
                'occurred_at' => $date,
                'note' => $note,
            ]);

            $this->cashbox->recordOut($withdrawal, $amount, CashboxTransactionKind::PartnerWithdrawal, $date);

            return $withdrawal;
        });
    }

    public function deleteWithdrawal(PartnerWithdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            $this->cashbox->removeFor($withdrawal);
            $withdrawal->delete();
        });
    }
}
