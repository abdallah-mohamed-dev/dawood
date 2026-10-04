<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A material now carries its own stock level and its own single unit price
     * instead of deriving both from a table of batches — specs/014 §القواعد
     * الحاكمة (1). quantity is scaled ×1000 (QuantityCast), unit_price is
     * money in piastres ×100 (MoneyCast).
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->bigInteger('quantity')->default(0)->after('unit');
            $table->bigInteger('unit_price')->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit_price']);
        });
    }
};
