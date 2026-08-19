<?php

namespace App\Services;

use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Models\CashboxTransaction;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The single writer for cashbox_transactions. Every real cashbox movement
 * must go through this service — no controller or model should create a
 * CashboxTransaction directly (see business-rules.md §5). The only row
 * without a source is the single opening balance, handled separately here.
 */
class CashboxService
{
    public function recordIn(Model $source, int $amount, CashboxTransactionKind $kind, DateTimeInterface|string $date, ?string $description = null): CashboxTransaction
    {
        return $this->record(CashboxTransactionType::In, $source, $amount, $kind, $date, $description);
    }

    public function recordOut(Model $source, int $amount, CashboxTransactionKind $kind, DateTimeInterface|string $date, ?string $description = null): CashboxTransaction
    {
        return $this->record(CashboxTransactionType::Out, $source, $amount, $kind, $date, $description);
    }

    public function removeFor(Model $source): void
    {
        CashboxTransaction::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->delete();
    }

    public function updateFor(Model $source, int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Cashbox transaction amount must be greater than zero.');
        }

        $updated = CashboxTransaction::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->update(['amount' => $amount]);

        if ($updated === 0) {
            throw new RuntimeException(
                'No cashbox transaction found for '.$source::class.' #'.$source->getKey().'.'
            );
        }
    }

    public function setOpeningBalance(int $amount, DateTimeInterface|string $date): CashboxTransaction
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('The opening balance cannot be negative.');
        }

        // Wrapped in a transaction so the find-then-write below is atomic:
        // combined with the sqlite "IMMEDIATE" transaction_mode (config/database.php),
        // a concurrent call blocks until this one commits, instead of both
        // racing past the SELECT and inserting two opening_balance rows.
        return DB::transaction(fn () => CashboxTransaction::query()->updateOrCreate(
            ['kind' => CashboxTransactionKind::OpeningBalance],
            [
                'type' => CashboxTransactionType::In,
                'amount' => $amount,
                'source_type' => null,
                'source_id' => null,
                'occurred_at' => $date,
            ],
        ));
    }

    public function balance(): int
    {
        return $this->totalIn() - $this->totalOut();
    }

    public function totalIn(): int
    {
        return (int) CashboxTransaction::query()->where('type', CashboxTransactionType::In)->sum('amount');
    }

    public function totalOut(): int
    {
        return (int) CashboxTransaction::query()->where('type', CashboxTransactionType::Out)->sum('amount');
    }

    /**
     * Same numbers as totalIn() + totalOut() + balance(), but in a single
     * aggregate query instead of three — for callers (like the cashbox page)
     * that need all three together on every page load.
     *
     * @return array{total_in: int, total_out: int, balance: int}
     */
    public function summary(): array
    {
        $totals = CashboxTransaction::query()
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as total_in, SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as total_out',
                [CashboxTransactionType::In->value, CashboxTransactionType::Out->value],
            )
            ->first();

        $totalIn = (int) ($totals?->total_in ?? 0);
        $totalOut = (int) ($totals?->total_out ?? 0);

        return [
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'balance' => $totalIn - $totalOut,
        ];
    }

    private function record(CashboxTransactionType $type, Model $source, int $amount, CashboxTransactionKind $kind, DateTimeInterface|string $date, ?string $description): CashboxTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Cashbox transaction amount must be greater than zero.');
        }

        return CashboxTransaction::query()->create([
            'type' => $type,
            'amount' => $amount,
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'kind' => $kind,
            'description' => $description,
            'occurred_at' => $date,
        ]);
    }
}
