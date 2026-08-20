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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('room_type');
            $table->bigInteger('sale_price');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index('status');
            // SQLite (unlike MySQL/InnoDB) does not auto-index FK columns —
            // CustomerController::show() filters rooms by customer_id on
            // every visit.
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
