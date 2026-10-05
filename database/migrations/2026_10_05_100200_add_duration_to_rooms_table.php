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
            $table->unsignedInteger('expected_duration_days')->nullable()->after('priced_at');
            $table->date('started_at')->nullable()->after('expected_duration_days');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->index('started_at');
        });

        // Approximation for rooms that already exist: the first time they
        // were worked on is taken as their creation date.
        DB::table('rooms')
            ->whereIn('status', ['in_progress', 'completed'])
            ->update(['started_at' => DB::raw('date(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['expected_duration_days', 'started_at']);
        });
    }
};
