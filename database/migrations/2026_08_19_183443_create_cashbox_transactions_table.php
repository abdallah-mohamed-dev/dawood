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
        Schema::create('cashbox_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->bigInteger('amount');
            $table->nullableMorphs('source');
            $table->string('kind');
            $table->string('description')->nullable();
            $table->date('occurred_at');
            $table->timestamps();

            $table->index('type');
            $table->index('occurred_at');
            $table->index('kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashbox_transactions');
    }
};
