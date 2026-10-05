<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each sealed table gets its own Schema::table() call: SQLite will not
 * drop or alter a column that a foreign key or index still covers, and
 * keeping them apart makes the down() order obvious.
 */
return new class extends Migration
{
    private const TABLES = ['rooms', 'expenses', 'room_costs', 'partner_withdrawals'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // null = the open season.
                $blueprint->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index('season_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['season_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('season_id');
            });
        }
    }
};
