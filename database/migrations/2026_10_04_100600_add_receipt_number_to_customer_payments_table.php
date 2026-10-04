<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('receipt_number')->nullable()->after('paid_at');
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->unique('receipt_number');
        });

        // Existing payments get numbers 1, 2, 3… in the order they were recorded.
        $ids = DB::table('customer_payments')->orderBy('id')->pluck('id');

        foreach ($ids as $index => $id) {
            DB::table('customer_payments')->where('id', $id)->update(['receipt_number' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropColumn('receipt_number');
        });
    }
};
