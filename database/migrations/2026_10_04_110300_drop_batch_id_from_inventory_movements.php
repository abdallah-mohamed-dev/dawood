<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A movement no longer belongs to a batch — the stock lives on the
     * material now. Each step runs in its own Schema::table() call because
     * SQLite refuses to drop a column that a foreign key or an index still
     * covers.
     */
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }

    /**
     * Rebuild the schema shape only — which batch a movement came from is
     * history the batches table no longer holds, so the restored column comes
     * back null rather than filled with a made-up id.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('material_id')->constrained('inventory_batches')->cascadeOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index('batch_id');
        });
    }
};
