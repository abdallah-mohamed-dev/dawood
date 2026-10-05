<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_partner_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            // Percentage at close time, ×100 like partners.percentage (ق-8).
            $table->bigInteger('percentage');
            $table->bigInteger('share_amount');
            $table->bigInteger('carried_in');
            $table->bigInteger('withdrawn');
            $table->bigInteger('carried_out');
            $table->timestamps();

            $table->unique(['season_id', 'partner_id']);
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_partner_shares');
    }
};
