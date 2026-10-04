<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->date('completed_at')->nullable()->after('status');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->index('completed_at');
        });

        // Rooms already completed before this column existed get the date of
        // their last update as a close-enough estimate (test data only).
        DB::table('rooms')->where('status', 'completed')->update(['completed_at' => DB::raw('date(updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['completed_at']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
