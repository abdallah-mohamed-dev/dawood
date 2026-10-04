<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->string('creditor');
            $table->bigInteger('amount');
            $table->date('incurred_at');
            $table->date('due_at')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->date('paid_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('is_paid');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
