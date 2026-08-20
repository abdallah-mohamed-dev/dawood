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
        Schema::create('partner_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->date('occurred_at');
            $table->string('note')->nullable();
            $table->timestamps();

            // SQLite does not auto-index FK columns; partner_id is filtered on
            // every partner detail page load.
            $table->index('partner_id');
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_withdrawals');
    }
};
