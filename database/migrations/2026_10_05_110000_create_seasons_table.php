<?php

use App\Enums\SeasonStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number');
            $table->string('name')->nullable();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->string('status');
            $table->timestamp('closed_at')->nullable();

            // Snapshot: written once at close, read-only after (specs/012 ق-7).
            $table->bigInteger('revenue')->nullable();
            $table->bigInteger('cost_of_materials')->nullable();
            $table->bigInteger('room_costs')->nullable();
            $table->bigInteger('cancelled_room_costs')->nullable();
            $table->bigInteger('admin_expenses')->nullable();
            $table->bigInteger('net_profit')->nullable();

            // Rounded-over loss (ق-13). Always positive or zero.
            $table->bigInteger('loss_carried_in')->default(0);
            $table->bigInteger('loss_carried_out')->nullable();
            $table->bigInteger('distributable_profit')->nullable();

            // Reference only — never part of any calculation.
            $table->bigInteger('cashbox_balance_at_close')->nullable();
            $table->bigInteger('stock_value_at_close')->nullable();
            $table->bigInteger('wip_carried_forward')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        // The first season exists from the start, so every record has a season
        // to belong to once it is closed.
        $earliest = DB::table('rooms')->min('created_at')
            ?? DB::table('expenses')->min('created_at')
            ?? now()->toDateTimeString();

        DB::table('seasons')->insert([
            'number' => 1,
            'started_at' => substr((string) $earliest, 0, 10),
            'status' => SeasonStatus::Open->value,
            'loss_carried_in' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
