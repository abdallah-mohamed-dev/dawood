<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Partner;
use App\Models\Room;
use App\Services\CustomerPaymentService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\PartnerService;
use App\Services\RoomCostService;
use App\Services\RoomMaterialService;
use App\Services\RoomService;
use Illuminate\Database\Seeder;

/**
 * A small, realistic set of records so every screen has something to show
 * right after `migrate:fresh --seed`. Money goes through the real services,
 * so the cashbox, stock and profit all agree with each other.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $wood = MaterialType::query()->where('name', 'خامة')->value('id');
        $accessory = MaterialType::query()->where('name', 'اكسسوار')->value('id');

        $inventory = app(InventoryService::class);
        $roomMaterials = app(RoomMaterialService::class);
        $payments = app(CustomerPaymentService::class);
        $expenses = app(ExpenseService::class);
        $partners = app(PartnerService::class);
        $costs = app(RoomCostService::class);
        $rooms = app(RoomService::class);

        $oak = Material::query()->create(['name' => 'خشب بلوط', 'unit' => 'لوح', 'material_type_id' => $wood, 'unit_price' => 0, 'quantity' => 0]);
        $pine = Material::query()->create(['name' => 'خشب زان', 'unit' => 'لوح', 'material_type_id' => $wood, 'unit_price' => 0, 'quantity' => 0]);
        $hinge = Material::query()->create(['name' => 'مفصلة نحاس', 'unit' => 'قطعة', 'material_type_id' => $accessory, 'unit_price' => 0, 'quantity' => 0]);

        // Stock: quantities are ×1000, prices are piastres.
        $inventory->addStock($oak, 20_000, 55_000, now()->subMonths(2)->toDateString(), PaymentMethod::Cash);
        $inventory->addStock($pine, 10_000, 30_000, now()->subMonths(2)->toDateString(), PaymentMethod::Wallet);
        $inventory->addStock($hinge, 40_000, 2_500, now()->subMonths(2)->toDateString(), PaymentMethod::Cash);

        $ahmed = Customer::query()->create(['name' => 'أحمد علي', 'phone' => '01000000001', 'address' => 'القاهرة']);
        $mona = Customer::query()->create(['name' => 'منى كمال', 'phone' => '01000000002', 'address' => 'الجيزة']);

        // Completed room: sold, fully built, paid in full.
        $kitchen = Room::query()->create(['customer_id' => $ahmed->id, 'room_type' => 'مطبخ', 'sale_price' => 1_500_000, 'status' => RoomStatus::Draft]);
        $rooms->changeStatus($kitchen, RoomStatus::InProgress);
        $oakReq = $roomMaterials->addRequirement($kitchen, $oak, 6_000);
        $roomMaterials->issue($oakReq, 6_000, now()->subMonth()->toDateString());
        $hingeReq = $roomMaterials->addRequirement($kitchen, $hinge, 8_000);
        $roomMaterials->issue($hingeReq, 8_000, now()->subMonth()->toDateString());
        $costs->create($kitchen, RoomCostType::Labor, 250_000, now()->subMonth()->toDateString());
        $payments->create($kitchen, 1_000_000, now()->subMonths(2)->toDateString());
        $payments->create($kitchen, 500_000, now()->subMonth()->toDateString(), 'الدفعة الأخيرة');
        $rooms->changeStatus($kitchen->fresh(), RoomStatus::Completed);

        // In progress room: partly paid, still being built.
        $bedroom = Room::query()->create(['customer_id' => $mona->id, 'room_type' => 'غرفة نوم', 'sale_price' => 900_000, 'status' => RoomStatus::Draft]);
        $rooms->changeStatus($bedroom, RoomStatus::InProgress);
        $pineReq = $roomMaterials->addRequirement($bedroom, $pine, 4_000);
        $roomMaterials->issue($pineReq, 2_000, now()->subWeek()->toDateString());
        $roomMaterials->addRequirement($bedroom, $oak, 3_000);
        $costs->create($bedroom, RoomCostType::Other, 40_000, now()->subDays(3)->toDateString(), 'نقل خشب');
        $payments->create($bedroom, 300_000, now()->subDays(5)->toDateString());

        // Draft room: nothing bought yet, shows the "short" state on the shortages page.
        $salon = Room::query()->create(['customer_id' => $mona->id, 'room_type' => 'صالون', 'sale_price' => 700_000, 'status' => RoomStatus::Draft]);
        $roomMaterials->addRequirement($salon, $pine, 5_000);

        // Admin expenses.
        $electricity = ExpenseCategory::query()->create(['name' => 'كهرباء']);
        $rent = ExpenseCategory::query()->create(['name' => 'إيجار الورشة']);
        $expenses->create($electricity, 45_000, now()->subWeeks(2)->toDateString(), 'فاتورة الكهرباء');
        $expenses->create($rent, 300_000, now()->subMonth()->toDateString(), 'إيجار الشهر');

        // Partners with a share, one withdrawal already taken.
        $partner = Partner::query()->create(['name' => 'محمد سعيد', 'percentage' => 2_000]);
        Partner::query()->create(['name' => 'خالد يوسف', 'percentage' => 1_500]);
        $partners->withdraw($partner, 50_000, now()->subDays(10)->toDateString(), 'سحب شخصي');
    }
}
