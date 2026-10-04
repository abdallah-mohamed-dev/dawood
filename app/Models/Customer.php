<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'address'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, LogsActivity;

    protected static string $activityTypeLabel = 'عميل';

    public function activityLabel(): string
    {
        return $this->name;
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
