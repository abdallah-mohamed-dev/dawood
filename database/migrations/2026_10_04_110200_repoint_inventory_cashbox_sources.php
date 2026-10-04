<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The purchase cashbox movements are keyed to an InventoryBatch, and that
     * table is about to be dropped — leaving them behind would orphan the
     * rows. Re-point every one of them at the batch's own `in` movement, which
     * is what 14.2 records against from now on.
     *
     * Only source_type/source_id are touched: no row is deleted and no amount,
     * type, kind or date is altered, so the cashbox balance is bit-for-bit the
     * same before and after.
     *
     * Batch ids and movement ids are written as plain strings, not ::class
     * constants, because InventoryBatch itself stops existing in 110400.
     */
    public function up(): void
    {
        DB::statement('
            UPDATE cashbox_transactions
            SET source_type = \'App\Models\InventoryMovement\',
                source_id = (
                    SELECT inventory_movements.id FROM inventory_movements
                    WHERE inventory_movements.batch_id = cashbox_transactions.source_id
                      AND inventory_movements.type = \'in\'
                    LIMIT 1
                )
            WHERE source_type = \'App\Models\InventoryBatch\'
              AND EXISTS (
                    SELECT 1 FROM inventory_movements
                    WHERE inventory_movements.batch_id = cashbox_transactions.source_id
                      AND inventory_movements.type = \'in\'
              )
        ');

        $orphans = (int) DB::table('cashbox_transactions')
            ->where('source_type', 'App\Models\InventoryBatch')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException(
                "{$orphans} cashbox transaction(s) still point at an InventoryBatch — their batch has no `in` movement to move to. Nothing was deleted; fix the data and re-run before dropping inventory_batches."
            );
        }
    }

    /**
     * Intentionally empty. A rollback re-creates an empty inventory_batches
     * table (110400), so the batch ids these rows used to reference no longer
     * mean anything — pointing them back would be a lie. The rows stay on the
     * InventoryMovement they were moved to, which is where 14.2 looks anyway.
     */
    public function down(): void
    {
        //
    }
};
