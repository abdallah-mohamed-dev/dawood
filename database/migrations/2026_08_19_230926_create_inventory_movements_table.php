<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('inventory_batches')->cascadeOnDelete();
            $table->string('type');
            $table->bigInteger('quantity');
            $table->bigInteger('cost');
            $table->nullableMorphs('related');
            $table->date('occurred_at');
            $table->timestamps();

            // SQLite (unlike MySQL/InnoDB) does not auto-index FK columns,
            // and batch_id in particular is scanned on every cascade delete
            // from inventory_batches (InventoryService::deletePurchase()).
            $table->index('batch_id');
            $table->index(['material_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
