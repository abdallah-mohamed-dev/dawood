<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quantities used to be stored as thousandths (QuantityCast ×1000) so a
 * material could sit on the shelf as "2.5 metres". The workshop never counts
 * half a plank, and the fraction only ever surfaced as "14.000" in the edit
 * form, so quantities become whole units — the same move money already made
 * when it dropped piastres.
 *
 * Money columns (unit_price, cost) are untouched: they are already whole pounds.
 */
return new class extends Migration
{
    /**
     * Every column cast through App\Casts\QuantityCast, as table => columns.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'materials' => ['quantity'],
        'inventory_movements' => ['quantity'],
        'room_materials' => ['required_quantity', 'issued_quantity'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                // Integer division, rounded half up: 2500 → 3, 2400 → 2. Each
                // row rounds on its own, so a material's stock may drift by a
                // unit from the sum of its movements — acceptable, because the
                // only data that exists at this point is test data.
                DB::table($table)->whereNotNull($column)->update([
                    $column => DB::raw("($column + 500) / 1000"),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                // Lossy on purpose — the fraction rounded away in up() is gone.
                DB::table($table)->whereNotNull($column)->update([
                    $column => DB::raw("$column * 1000"),
                ]);
            }
        }
    }
};
