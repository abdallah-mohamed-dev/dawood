<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the material category layer: a material is now just a name and a unit.
     *
     * Each step runs in its own Schema::table() call on purpose — SQLite refuses
     * to drop a column that an index still covers, so the composite unique index
     * has to be gone before the column is.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropUnique(['category_id', 'name']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::dropIfExists('categories');
    }

    /**
     * Rebuild the schema shape only — the category each material belonged to is
     * gone for good, so every material is reattached to one placeholder category.
     */
    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'غير مصنّف',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('materials', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('materials', function (Blueprint $table) use ($categoryId) {
            $table->foreignId('category_id')->default($categoryId)->constrained()->restrictOnDelete();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->unique(['category_id', 'name']);
        });
    }
};
