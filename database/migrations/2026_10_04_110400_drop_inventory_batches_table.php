<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Last of the FIFO teardown: with batch_id gone and the cashbox movements
     * re-pointed (110200), nothing references batches anymore.
     */
    public function up(): void
    {
        Schema::dropIfExists('inventory_batches');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->bigInteger('quantity');
            $table->bigInteger('remaining_quantity');
            $table->bigInteger('unit_cost');
            $table->date('purchase_date');
            $table->timestamps();

            $table->index(['material_id', 'purchase_date']);
        });
    }
};
