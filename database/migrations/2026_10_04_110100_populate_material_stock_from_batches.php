<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Carry the batches' data onto the material itself before the batches are
     * dropped:
     *   - quantity   = every batch's remaining_quantity (what is still in store)
     *   - unit_price = the newest batch's unit_cost, 0 when there is none
     * Raw SQL on purpose — this runs while InventoryBatch still exists but must
     * not depend on the model or its casts.
     */
    public function up(): void
    {
        DB::statement('
            UPDATE materials SET quantity = (
                SELECT COALESCE(SUM(remaining_quantity), 0)
                FROM inventory_batches
                WHERE inventory_batches.material_id = materials.id
            )
        ');

        DB::statement('
            UPDATE materials SET unit_price = COALESCE((
                SELECT unit_cost FROM inventory_batches
                WHERE inventory_batches.material_id = materials.id
                ORDER BY purchase_date DESC, id DESC
                LIMIT 1
            ), 0)
        ');
    }

    /**
     * The per-batch history is gone by the time this rolls back, so there is
     * nothing truthful to restore — the stock columns just go back to zero and
     * the batches come back empty (see 110400).
     */
    public function down(): void
    {
        DB::statement('UPDATE materials SET quantity = 0, unit_price = 0');
    }
};
