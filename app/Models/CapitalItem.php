<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\LogsActivity;
use Database\Factories\CapitalItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A capital line item, recorded for display only. Deliberately has no link
 * to the cashbox or to profit — see docs/capital.md.
 */
#[Fillable(['name', 'amount', 'occurred_at', 'note'])]
class CapitalItem extends Model
{
    /** @use HasFactory<CapitalItemFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'بند رأس مال';

    public function activityLabel(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'occurred_at' => 'date',
        ];
    }
}
