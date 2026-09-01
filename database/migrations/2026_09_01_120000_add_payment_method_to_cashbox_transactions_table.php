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
        Schema::table('cashbox_transactions', function (Blueprint $table) {
            // Nullable because rows recorded before this column existed have
            // no method, and the cashbox page must keep rendering them.
            $table->string('payment_method')->nullable()->after('kind');
        });

        Schema::table('cashbox_transactions', function (Blueprint $table) {
            $table->index(['type', 'payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbox_transactions', function (Blueprint $table) {
            $table->dropIndex(['type', 'payment_method']);
        });

        Schema::table('cashbox_transactions', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
