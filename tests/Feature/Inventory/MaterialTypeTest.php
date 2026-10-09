<?php

use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use Database\Seeders\MaterialTypeSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->khama = MaterialType::query()->where('name', 'خامة')->sole();
    $this->accessory = MaterialType::query()->where('name', 'اكسسوار')->sole();
});

test('the seeder creates both types and running it twice adds nothing', function () {
    MaterialType::query()->delete();

    $this->seed(MaterialTypeSeeder::class);
    $this->seed(MaterialTypeSeeder::class);

    expect(MaterialType::query()->orderBy('position')->pluck('name')->all())->toBe(['خامة', 'اكسسوار']);
});

test('adding a material without a type is rejected with an Arabic message', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.materials.store'), ['name' => 'خشب بلوط', 'unit' => 'لوح'])
        ->assertSessionHasErrors(['material_type_id' => 'حقل نوع الخامة مطلوب.']);

    expect(Material::query()->where('name', 'خشب بلوط')->exists())->toBeFalse();
});

test('adding a material with a type that does not exist is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.materials.store'), ['name' => 'خشب بلوط', 'unit' => 'لوح', 'material_type_id' => 9999])
        ->assertSessionHasErrors(['material_type_id' => 'القيمة المختارة لحقل نوع الخامة غير موجودة.']);
});

test('a material saved with a type shows that type on the stock page', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory.materials.store'), [
            'name' => 'مفصلات',
            'unit' => 'قطعة',
            'material_type_id' => $this->accessory->id,
            'unit_price' => '18',
        ])
        ->assertRedirect(route('inventory.materials.index'));

    expect(Material::query()->where('name', 'مفصلات')->value('material_type_id'))->toBe($this->accessory->id);

    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index'))
        ->assertSeeHtml('<span x-show="! editing">اكسسوار</span>');
});

test('renaming a type from the settings page shows the new name on the stock page', function () {
    Material::factory()->create(['name' => 'خشب زان', 'material_type_id' => $this->khama->id]);

    $this->actingAs($this->admin)
        ->put(route('settings.material-types.update', $this->khama), ['name' => 'خامات الأخشاب'])
        ->assertRedirect();

    expect($this->khama->fresh()->name)->toBe('خامات الأخشاب');

    $this->actingAs($this->admin)
        ->get(route('inventory.materials.index'))
        ->assertSeeHtml('<span x-show="! editing">خامات الأخشاب</span>');
});

test('a duplicate type name is rejected under that type\'s own error bag', function () {
    $this->actingAs($this->admin)
        ->put(route('settings.material-types.update', $this->accessory), ['name' => 'خامة'])
        ->assertSessionHasErrors(
            ['name' => 'قيمة حقل الاسم مُستخدمة من قبل.'],
            null,
            'materialType_'.$this->accessory->id,
        );
});

test('the settings page shows one name form per type and no add or delete controls', function () {
    $response = $this->actingAs($this->admin)->get(route('settings.index'))->assertOk();

    $response->assertSee('خامة')->assertSee('اكسسوار');

    expect(substr_count($response->getContent(), 'name="name"'))->toBe(2);
});

test('the migration sets existing materials to the خامة type', function () {
    $migration = 'database/migrations/2026_10_04_100300_add_material_type_to_materials_table.php';

    Artisan::call('migrate:rollback', ['--path' => $migration]);

    DB::table('materials')->insert([
        'name' => 'مادة قديمة',
        'unit' => 'لوح',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => $migration]);

    expect(DB::table('materials')->where('name', 'مادة قديمة')->value('material_type_id'))->toBe($this->khama->id);
});
