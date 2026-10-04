<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'بند مصروف';

    public function activityLabel(): string
    {
        return $this->name;
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
