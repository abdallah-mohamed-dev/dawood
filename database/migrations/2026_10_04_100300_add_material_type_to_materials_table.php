<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable at the database level so the column can be added to existing
     * rows; the requirement is enforced by the Form Requests instead.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('material_type_id')->nullable()->after('name')->constrained()->restrictOnDelete();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->index('material_type_id');
        });

        $this->seedTypes();

        // Every material that existed before this migration is a خامة.
        $materialTypeId = DB::table('material_types')->where('name', 'خامة')->value('id');
        DB::table('materials')->whereNull('material_type_id')->update(['material_type_id' => $materialTypeId]);
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex(['material_type_id']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_type_id');
        });
    }

    private function seedTypes(): void
    {
        $now = now();

        foreach (['خامة' => 1, 'اكسسوار' => 2] as $name => $position) {
            if (! DB::table('material_types')->where('name', $name)->exists()) {
                DB::table('material_types')->insert([
                    'name' => $name,
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
