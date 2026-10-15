<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Models\CapitalItem;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Partner;
use App\Models\Room;
use App\Models\Season;
use App\Services\CashboxService;
use App\Services\CustomerPaymentService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\PartnerService;
use App\Services\RoomCostService;
use App\Services\RoomMaterialService;
use App\Services\RoomPricingService;
use App\Services\RoomService;
use App\Services\SeasonService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use stdClass;

/**
 * Five and a half months of a small, profitable workshop, so every screen —
 * seasons included — has something real to show after `migrate:fresh --seed`.
 *
 *  - Season 1: May → July 2026, closed on 2026-07-31. Seven rooms completed,
 *    one cancelled, one still in progress at the close (carried forward).
 *  - Season 2: August 2026 → today, open. Rooms completed, in progress, and
 *    a priced draft whose wood is short in the warehouse.
 *
 * Every write goes through the real services, in date order, with the clock
 * moved to each event's date — so created_at, started_at, completed_at, the
 * cashbox, the stock and the profit all agree with each other.
 */
class DemoDataSeeder extends Seeder
{
    /** @var list<array{0: string, 1: int, 2: Closure}> */
    private array $events = [];

    /** @var array<string, Material> */
    private array $materials = [];

    /**
     * What each kind of room consumes, plus its usual labour and transport.
     * Material quantities are whole units (specs/023).
     */
    private const TEMPLATES = [
        'kitchen' => [
            'materials' => ['MDF 18 مم' => 14, 'كونتر 4 مم' => 6, 'فورمايكا' => 8, 'مفصلة هيدروليك' => 24, 'مجرى درج' => 10, 'مقبض' => 22, 'غراء' => 2],
            'labor' => 11_000, 'other' => 1_200, 'days' => 25,
        ],
        'bedroom' => [
            'materials' => ['خشب زان' => 8, 'MDF 18 مم' => 6, 'كونتر 4 مم' => 4, 'مفصلة هيدروليك' => 12, 'مجرى درج' => 6, 'مقبض' => 10, 'ورنيش' => 3, 'غراء' => 1],
            'labor' => 10_000, 'other' => 1_000, 'days' => 28,
        ],
        'wardrobe' => [
            'materials' => ['MDF 18 مم' => 8, 'كونتر 4 مم' => 3, 'مفصلة هيدروليك' => 8, 'مجرى درج' => 4, 'مقبض' => 6, 'غراء' => 1],
            'labor' => 4_500, 'other' => 600, 'days' => 14,
        ],
        'salon' => [
            'materials' => ['خشب موسكي' => 18, 'خشب زان' => 3, 'ورنيش' => 3, 'غراء' => 1],
            'labor' => 8_000, 'other' => 900, 'days' => 25,
        ],
        'library' => [
            'materials' => ['MDF 18 مم' => 5, 'كونتر 4 مم' => 2, 'مفصلة هيدروليك' => 4, 'مقبض' => 4, 'غراء' => 1],
            'labor' => 3_000, 'other' => 400, 'days' => 12,
        ],
    ];

    public function run(): void
    {
        try {
            $this->schedule();
            $this->play();
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    private function schedule(): void
    {
        $this->setup();
        $this->purchases();
        $this->rooms();
        $this->expenses();
        $this->partners();
        $this->debtsAndCapital();

        // Season 1 closes at the end of July. The close also takes the usual
        // database copy into storage/app/season-backups.
        $this->at('2026-07-31 23:00', fn () => app(SeasonService::class)->close('2026-07-31'));
    }

    private function setup(): void
    {
        // The first season is created by its migration, dated "today" on an
        // empty database — move its start back to where this story begins.
        Season::query()->where('number', 1)->update(['started_at' => '2026-05-01']);

        $this->at('2026-05-01 07:00', fn () => app(CashboxService::class)->setOpeningBalance(180_000, '2026-05-01'));

        $this->at('2026-05-01 07:30', function () {
            $wood = MaterialType::query()->where('name', 'خامة')->value('id');
            $accessory = MaterialType::query()->where('name', 'اكسسوار')->value('id');

            foreach ([
                ['MDF 18 مم', 'لوح', $wood], ['كونتر 4 مم', 'لوح', $wood], ['خشب زان', 'لوح', $wood],
                ['خشب موسكي', 'متر', $wood], ['فورمايكا', 'لوح', $wood],
                ['مفصلة هيدروليك', 'قطعة', $accessory], ['مجرى درج', 'طقم', $accessory], ['مقبض', 'قطعة', $accessory],
                ['غراء', 'جركن', $accessory], ['ورنيش', 'جالون', $accessory],
            ] as [$name, $unit, $type]) {
                $this->materials[$name] = Material::query()->create([
                    'name' => $name, 'unit' => $unit, 'material_type_id' => $type, 'unit_price' => 0, 'quantity' => 0,
                ]);
            }
        });
    }

    private function purchases(): void
    {
        // [quantity, unit price]. The September MDF price rise re-prices the
        // stock still in the warehouse — that is how the system works.
        $batches = [
            '2026-05-01' => ['MDF 18 مم' => [40, 950], 'كونتر 4 مم' => [20, 380], 'خشب زان' => [12, 2_200], 'خشب موسكي' => [20, 650], 'فورمايكا' => [16, 700], 'مفصلة هيدروليك' => [80, 45], 'مجرى درج' => [30, 120], 'مقبض' => [60, 35], 'غراء' => [8, 160], 'ورنيش' => [8, 420]],
            '2026-06-15' => ['MDF 18 مم' => [30, 950], 'كونتر 4 مم' => [12, 380], 'خشب زان' => [10, 2_200], 'خشب موسكي' => [20, 650], 'فورمايكا' => [10, 700], 'مفصلة هيدروليك' => [50, 45], 'مجرى درج' => [20, 120], 'مقبض' => [40, 35], 'غراء' => [5, 160], 'ورنيش' => [5, 420]],
            '2026-08-01' => ['MDF 18 مم' => [30, 950], 'كونتر 4 مم' => [12, 380], 'خشب زان' => [8, 2_200], 'فورمايكا' => [10, 700], 'مفصلة هيدروليك' => [40, 45], 'مجرى درج' => [15, 120], 'مقبض' => [40, 35], 'غراء' => [5, 160], 'ورنيش' => [3, 420]],
            '2026-09-10' => ['MDF 18 مم' => [25, 1_000], 'كونتر 4 مم' => [10, 380], 'خشب زان' => [6, 2_200], 'خشب موسكي' => [20, 650], 'فورمايكا' => [8, 700], 'مفصلة هيدروليك' => [30, 45], 'مجرى درج' => [15, 120], 'مقبض' => [30, 35], 'غراء' => [4, 160], 'ورنيش' => [4, 420]],
        ];

        $methods = [PaymentMethod::Cash, PaymentMethod::Instapay];

        foreach ($batches as $date => $items) {
            $this->at($date.' 08:00', function () use ($date, $items, $methods) {
                $i = 0;
                foreach ($items as $name => [$quantity, $price]) {
                    app(InventoryService::class)->addStock($this->materials[$name], $quantity, $price, $date, $methods[$i++ % 2]);
                }
            });
        }
    }

    private function rooms(): void
    {
        // customer, phone, area, template, room name, price, start, mid, end, options
        $rooms = [
            // ── Season 1 ──
            ['أحمد علي', '01001234567', 'مدينة نصر', 'kitchen', 'مطبخ', 75_000, '2026-05-03', '2026-05-15', '2026-05-27'],
            ['منى كمال', '01112345678', 'المعادي', 'wardrobe', 'دولاب', 28_000, '2026-05-06', '2026-05-13', '2026-05-20'],
            ['كريم حسن', '01223456789', 'الشيخ زايد', 'bedroom', 'غرفة نوم', 68_000, '2026-05-15', '2026-05-29', '2026-06-12'],
            ['سارة إبراهيم', '01534567890', 'مصر الجديدة', 'library', 'مكتبة', 17_000, '2026-06-02', '2026-06-08', '2026-06-14'],
            ['محمود عادل', '01045678901', 'الهرم', 'salon', 'صالون', 45_000, '2026-06-08', '2026-06-21', '2026-07-05'],
            // Completed in July but its last payment only comes in August (season 2).
            ['هبة سمير', '01156789012', 'التجمع الخامس', 'kitchen', 'مطبخ', 80_000, '2026-06-20', '2026-07-04', '2026-07-18', ['finalPaidAt' => '2026-08-05']],
            ['أحمد علي', '01001234567', 'مدينة نصر', 'wardrobe', 'دولاب أطفال', 30_000, '2026-07-06', '2026-07-14', '2026-07-22'],
            // Still in progress when season 1 closes — finishes in season 2.
            ['ياسر فتحي', '01267890123', 'حدائق الأهرام', 'bedroom', 'غرفة نوم', 70_000, '2026-07-15', '2026-07-28', '2026-08-20'],
            ['نادية رمضان', '01078901234', 'شبرا', 'salon', 'سفرة', 42_000, '2026-07-10', null, null, ['cancelAt' => '2026-07-14']],

            // ── Season 2 ──
            ['منى كمال', '01112345678', 'المعادي', 'kitchen', 'مطبخ', 82_000, '2026-08-03', '2026-08-16', '2026-08-29'],
            ['عمر خالد', '01189012345', 'العباسية', 'library', 'مكتبة', 18_000, '2026-08-10', '2026-08-15', '2026-08-21'],
            ['رانيا مصطفى', '01290123456', '6 أكتوبر', 'bedroom', 'غرفة نوم', 66_000, '2026-09-01', '2026-09-14', '2026-09-28'],
            ['كريم حسن', '01223456789', 'الشيخ زايد', 'wardrobe', 'دولاب', 29_000, '2026-09-07', '2026-09-13', '2026-09-19'],
            ['طارق نبيل', '01011223344', 'المقطم', 'salon', 'صالون', 47_000, '2026-09-15', '2026-09-30', null],
            ['شيماء أحمد', '01122334455', 'الرحاب', 'kitchen', 'مطبخ', 78_000, '2026-10-04', '2026-10-12', null, ['issue' => 'half']],
            // Priced and waiting: its beech is short in the warehouse.
            ['وليد جمال', '01233445566', 'الزمالك', 'bedroom', 'غرفة نوم ماستر', 72_000, '2026-10-11', null, null, ['draft' => true]],
        ];

        $methods = [PaymentMethod::Cash, PaymentMethod::Instapay, PaymentMethod::Wallet, PaymentMethod::Cash, PaymentMethod::Cheque];

        foreach ($rooms as $index => $row) {
            $this->room($row, $methods[$index % count($methods)], $index);
        }
    }

    /**
     * Schedules one room's whole life: created and priced at the start,
     * materials issued two days later, half the labour and a second payment
     * mid-way, the rest at the end.
     *
     * @param  array<int, mixed>  $row
     */
    private function room(array $row, PaymentMethod $method, int $index): void
    {
        [$customerName, $phone, $area, $kind, $roomType, $price, $start, $mid, $end] = $row;
        $options = $row[9] ?? [];
        $template = self::TEMPLATES[$kind];
        $ref = new stdClass;

        $this->at($start.' 09:00', function () use ($ref, $customerName, $phone, $area, $roomType, $price, $template, $index) {
            $customer = Customer::query()->firstOrCreate(['name' => $customerName], ['phone' => $phone, 'address' => $area]);
            $ref->room = Room::query()->create(['customer_id' => $customer->id, 'room_type' => $roomType, 'sale_price' => $price, 'status' => RoomStatus::Draft]);

            foreach ($template['materials'] as $name => $quantity) {
                $ref->{'req:'.$name} = app(RoomMaterialService::class)->addRequirement($ref->room, $this->materials[$name], $quantity);
            }

            // Estimates land a little over or under the real figures, so the
            // pricing comparison has something to compare.
            $drift = [96, 104, 108, 93, 101][$index % 5];
            [$wood, $accessories] = $this->materialCost($template['materials']);
            app(RoomPricingService::class)->save($ref->room, [
                'materials' => $this->roundTo100($wood * $drift / 100),
                'accessories' => $this->roundTo100($accessories * $drift / 100),
                'labor' => $this->roundTo100($template['labor'] * $drift / 100),
                'other' => $template['other'],
            ], $template['days'] + ($index % 3) - 1);
        });

        if ($options['draft'] ?? false) {
            $this->at($start.' 10:00', fn () => app(CustomerPaymentService::class)->create($ref->room, 20_000, $start, 'عربون', $method));

            return;
        }

        if (isset($options['cancelAt'])) {
            $this->at($options['cancelAt'].' 10:00', fn () => app(RoomService::class)->changeStatus($ref->room, RoomStatus::Cancelled));

            return;
        }

        $deposit = intdiv($price, 2);
        $second = intdiv($price * 3, 10);

        $this->at($start.' 09:30', fn () => app(RoomService::class)->changeStatus($ref->room, RoomStatus::InProgress));
        $this->at($start.' 10:00', fn () => app(CustomerPaymentService::class)->create($ref->room, $deposit, $start, 'مقدم', $method));

        $issueDate = CarbonImmutable::parse($start)->addDays(2)->toDateString();
        $this->at($issueDate.' 11:00', function () use ($ref, $template, $issueDate, $options) {
            foreach ($template['materials'] as $name => $quantity) {
                $issue = ($options['issue'] ?? 'all') === 'half' ? intdiv($quantity + 1, 2) : $quantity;
                app(RoomMaterialService::class)->issue($ref->{'req:'.$name}, $issue, $issueDate);
            }
        });

        $halfLabor = intdiv($template['labor'], 2);

        if ($mid !== null) {
            $this->at($mid.' 12:00', fn () => app(CustomerPaymentService::class)->create($ref->room, $second, $mid, 'دفعة تانية', $method));
            $this->at($mid.' 12:30', fn () => app(RoomCostService::class)->create($ref->room, RoomCostType::Labor, $halfLabor, $mid, 'نص المصنعية', PaymentMethod::Cash));
        }

        if ($end === null) {
            return;
        }

        $this->at($end.' 13:30', fn () => app(RoomCostService::class)->create($ref->room, RoomCostType::Labor, $template['labor'] - $halfLabor, $end, 'باقي المصنعية', PaymentMethod::Cash));
        $this->at($end.' 14:00', fn () => app(RoomCostService::class)->create($ref->room, RoomCostType::Other, $template['other'], $end, 'نقل وتركيب', PaymentMethod::Cash));
        $this->at($end.' 17:00', fn () => app(RoomService::class)->changeStatus($ref->room->fresh(), RoomStatus::Completed));

        $finalAt = $options['finalPaidAt'] ?? $end;
        $this->at($finalAt.' 13:00', fn () => app(CustomerPaymentService::class)->create($ref->room, $price - $deposit - $second, $finalAt, 'الدفعة الأخيرة', $method));
    }

    private function expenses(): void
    {
        $this->at('2026-05-01 07:45', function () {
            foreach (['إيجار الورشة', 'كهرباء', 'مواصلات', 'ضيافة', 'صيانة'] as $name) {
                ExpenseCategory::query()->create(['name' => $name]);
            }
        });

        $electricity = ['05' => 920, '06' => 1_050, '07' => 1_180, '08' => 1_240, '09' => 1_010, '10' => 960];

        foreach ($electricity as $month => $bill) {
            $this->expense("2026-$month-01", 'إيجار الورشة', 5_000, 'إيجار شهر '.(int) $month, PaymentMethod::Instapay);
            $this->expense("2026-$month-10", 'كهرباء', $bill, 'فاتورة الكهرباء', PaymentMethod::Wallet);

            if ($month !== '10') {
                $this->expense("2026-$month-15", 'مواصلات', 650, 'مشاوير شراء ومعاينات');
                $this->expense("2026-$month-20", 'ضيافة', 300, 'شاي وقهوة');
            }
        }

        $this->expense('2026-06-18', 'صيانة', 1_200, 'صيانة المنشار');
        $this->expense('2026-09-22', 'صيانة', 1_500, 'تغيير سير الكمبروسر');
    }

    private function expense(string $date, string $category, int $amount, string $description, PaymentMethod $method = PaymentMethod::Cash): void
    {
        $this->at($date.' 11:30', fn () => app(ExpenseService::class)->create(
            ExpenseCategory::query()->where('name', $category)->firstOrFail(), $amount, $date, $description, $method
        ));
    }

    private function partners(): void
    {
        $ref = new stdClass;

        $this->at('2026-05-01 07:50', function () use ($ref) {
            $ref->abdallah = Partner::query()->create(['name' => 'عبدالله محمد', 'percentage' => 5_000]);
            $ref->mohamed = Partner::query()->create(['name' => 'محمد سعيد', 'percentage' => 3_000]);
            $ref->khaled = Partner::query()->create(['name' => 'خالد يوسف', 'percentage' => 2_000]);
        });

        foreach ([
            '2026-06-30' => [12_000, 7_000, 5_000],
            '2026-07-25' => [13_000, 8_000, 5_000],
            '2026-09-30' => [15_000, 9_000, 6_000],
        ] as $date => [$a, $m, $k]) {
            $this->at($date.' 15:00', function () use ($ref, $date, $a, $m, $k) {
                app(PartnerService::class)->withdraw($ref->abdallah, $a, $date, 'سحب شهري', PaymentMethod::Instapay);
                app(PartnerService::class)->withdraw($ref->mohamed, $m, $date, 'سحب شهري');
                app(PartnerService::class)->withdraw($ref->khaled, $k, $date, 'سحب شهري', PaymentMethod::Wallet);
            });
        }
    }

    private function debtsAndCapital(): void
    {
        // Capital is a record only — no cashbox, no profit.
        foreach ([
            ['2026-04-20', 'منشار طاولة', 85_000, 'ماكينة التقطيع الأساسية'],
            ['2026-04-25', 'كمبروسر ومسدس دهان', 18_000, null],
            ['2026-04-28', 'عدة يدوية وشنيور', 9_500, null],
            ['2026-08-15', 'ماكينة شطف حروف', 32_000, 'اتشرت مستعملة'],
        ] as [$date, $name, $amount, $note]) {
            $this->at($date.' 12:00', fn () => CapitalItem::query()->create(['name' => $name, 'amount' => $amount, 'occurred_at' => $date, 'note' => $note]));
        }

        // A debt is a reminder, not a cash movement.
        $this->at('2026-06-10 12:00', fn () => Debt::query()->create(['creditor' => 'محل الإكسسوارات', 'amount' => 4_200, 'incurred_at' => '2026-06-10', 'due_at' => '2026-07-01', 'note' => 'مقابض ومفصلات']));
        $this->at('2026-07-01 12:00', fn () => Debt::query()->where('creditor', 'محل الإكسسوارات')->update(['is_paid' => true, 'paid_at' => '2026-07-01']));
        $this->at('2026-09-20 12:00', fn () => Debt::query()->create(['creditor' => 'الحاج سيد — مورد الخشب', 'amount' => 18_000, 'incurred_at' => '2026-09-20', 'due_at' => '2026-10-10', 'note' => 'باقي حساب شحنة الزان']));
        $this->at('2026-10-05 12:00', fn () => Debt::query()->create(['creditor' => 'ورشة الدهانات', 'amount' => 6_500, 'incurred_at' => '2026-10-05', 'due_at' => '2026-11-05']));
    }

    /**
     * @param  array<string, int>  $materials
     * @return array{0: int, 1: int} wood cost, accessories cost
     */
    private function materialCost(array $materials): array
    {
        $wood = 0;
        $accessories = 0;

        foreach ($materials as $name => $quantity) {
            $material = $this->materials[$name];
            $cost = $quantity * (int) $material->fresh()->getRawOriginal('unit_price');

            if ($material->materialType->name === 'خامة') {
                $wood += $cost;
            } else {
                $accessories += $cost;
            }
        }

        return [$wood, $accessories];
    }

    private function roundTo100(int|float $amount): int
    {
        return (int) (round($amount / 100) * 100);
    }

    private function at(string $when, Closure $action): void
    {
        $this->events[] = [strlen($when) === 10 ? $when.' 10:00' : $when, count($this->events), $action];
    }

    /**
     * Runs every scheduled event in date order (ties keep their scheduling
     * order), with the clock set to the event's moment.
     */
    private function play(): void
    {
        usort($this->events, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        foreach ($this->events as [$when, , $action]) {
            Carbon::setTestNow(Carbon::parse($when));
            CarbonImmutable::setTestNow(CarbonImmutable::parse($when));
            $action();
        }
    }
}
