<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estimates are nullable on purpose: a room that was never priced has
     * no estimate, and zero means "estimated at zero", not "not priced".
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->bigInteger('estimated_materials')->nullable()->after('sale_price');
            $table->bigInteger('estimated_accessories')->nullable()->after('estimated_materials');
            $table->bigInteger('estimated_labor')->nullable()->after('estimated_accessories');
            $table->bigInteger('estimated_other')->nullable()->after('estimated_labor');
            $table->date('priced_at')->nullable()->after('estimated_other');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['estimated_materials', 'estimated_accessories', 'estimated_labor', 'estimated_other', 'priced_at']);
        });
    }
};
