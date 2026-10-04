<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\LogsActivity;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A debt the workshop owes to someone. Recorded and reminded about only —
 * a debt never creates a cashbox movement and never touches profit.
 */
#[Fillable(['creditor', 'amount', 'incurred_at', 'due_at', 'is_paid', 'paid_at', 'note'])]
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'دين';

    public function activityLabel(): string
    {
        return $this->creditor;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'incurred_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'date',
            'is_paid' => 'boolean',
        ];
    }

    public function isOverdue(): bool
    {
        // Compared against the start of today: a debt due today is not overdue yet.
        return ! $this->is_paid && $this->due_at !== null && $this->due_at->lt(today());
    }
}
