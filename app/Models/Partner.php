<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'percentage'])]
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'شريك';

    public function activityLabel(): string
    {
        return $this->name;
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(PartnerWithdrawal::class);
    }

    public function partnerShares(): HasMany
    {
        return $this->hasMany(SeasonPartnerShare::class);
    }
}
