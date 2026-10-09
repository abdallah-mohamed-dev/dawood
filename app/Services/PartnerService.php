<?php

namespace App\Services;

use App\Enums\CashboxTransactionKind;
use App\Enums\PaymentMethod;
use App\Enums\SeasonStatus;
use App\Exceptions\SeasonClosedException;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Season;
use App\Models\SeasonPartnerShare;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Owns partners' profit shares and their withdrawals. The share is computed
 * live from ProfitService::distributableProfit() (the open season's profit after
 * any rounded-over loss — specs/012): share = round(profit_piastres × percentage / 10000).
 * Nothing about the share is stored while a season is open.
 */
class PartnerService
{
    public function __construct(
        private readonly CashboxService $cashbox,
        private readonly ProfitService $profit,
    ) {}

    /**
     * Share of this open season's distributable profit. The formula is the one
     * this service always used — only its input changed (specs/012 §3.4-ب).
     */
    public function share(Partner $partner): int
    {
        return self::shareOf($this->profit->distributableProfit(), $partner->percentage);
    }

    /**
     * What the partner was owed out of the last closed season and did not take.
     * Negative when they took more than they were owed.
     */
    public function carriedIn(Partner $partner): int
    {
        return (int) SeasonPartnerShare::query()
            ->join('seasons', 'seasons.id', '=', 'season_partner_shares.season_id')
            ->where('seasons.status', SeasonStatus::Closed)
            ->where('season_partner_shares.partner_id', $partner->id)
            ->orderByDesc('seasons.number')
            ->value('season_partner_shares.carried_out') ?? 0;
    }

    public function totalWithdrawn(Partner $partner): int
    {
        return (int) PartnerWithdrawal::query()
            ->where('partner_id', $partner->id)
            ->whereNull('season_id')
            ->sum('amount');
    }

    /**
     * Withdrawals for one season instead of the open one. 'open' is what
     * totalWithdrawn() reports and what every page shows; a season id reads a
     * sealed season, and null reads every season at once. Used by the CSV
     * export so its "المسحوب" column follows the same season filter as the
     * rest of the file, instead of always reporting the open season.
     */
    public function totalWithdrawnForSeason(Partner $partner, int|string|null $season): int
    {
        return (int) PartnerWithdrawal::query()
            ->where('partner_id', $partner->id)
            ->when($season === 'open', fn ($query) => $query->whereNull('season_id'))
            ->when(is_int($season), fn ($query) => $query->where('season_id', $season))
            ->sum('amount');
    }

    public function remaining(Partner $partner): int
    {
        return $this->carriedIn($partner) + $this->share($partner) - $this->totalWithdrawn($partner);
    }

    /**
     * round(profit × percentage / 10000), half up. Always zero or positive.
     */
    public static function shareOf(int $profit, int $percentage): int
    {
        if ($profit <= 0) {
            return 0;
        }

        $numerator = $profit * $percentage;

        return intdiv($numerator, 10_000) + (($numerator % 10_000 >= 5_000) ? 1 : 0);
    }

    public function withdraw(Partner $partner, int $amount, DateTimeInterface|string $date, ?string $note = null, PaymentMethod $method = PaymentMethod::Cash): PartnerWithdrawal
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be greater than zero.');
        }

        if (! Season::query()->where('status', SeasonStatus::Open)->exists()) {
            throw new RuntimeException('مفيش موسم مفتوح دلوقتي.');
        }

        return DB::transaction(function () use ($partner, $amount, $date, $note, $method) {
            $withdrawal = PartnerWithdrawal::query()->create([
                'partner_id' => $partner->id,
                'amount' => $amount,
                'occurred_at' => $date,
                'note' => $note,
            ]);

            $this->cashbox->recordOut($withdrawal, $amount, CashboxTransactionKind::PartnerWithdrawal, $date, method: $method);

            return $withdrawal;
        });
    }

    public function deleteWithdrawal(PartnerWithdrawal $withdrawal): void
    {
        if ($withdrawal->season_id !== null) {
            throw new SeasonClosedException;
        }

        DB::transaction(function () use ($withdrawal) {
            $this->cashbox->removeFor($withdrawal);
            $withdrawal->delete();
        });
    }
}
